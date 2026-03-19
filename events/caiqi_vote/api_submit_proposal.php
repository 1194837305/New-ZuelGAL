<?php
// 打开报错，方便调试
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// 引入配置文件
$config_path = '../../config.php';
if (!file_exists($config_path)) {
    die(json_encode(['status' => 'error', 'message' => '找不到配置文件！']));
}
require_once $config_path;

try {
    $conn = db_connect();
    
    $deadline = strtotime("2026-04-20 23:59:59");
if (time() > $deadline) {
    die(json_encode(['status' => 'error', 'message' => '海选档案已封存，无法再进行提案或投票。']));
}
    
    // 获取用户物理 IP
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // 1. 自动建表：盲盒提案库 (新增了 ip_address 字段)
    $sql_proposals = "CREATE TABLE IF NOT EXISTS event_caiqi_proposals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        author_name VARCHAR(100) NOT NULL,
        ip_address VARCHAR(50) NOT NULL,
        games_data TEXT NOT NULL, 
        total_games INT NOT NULL,
        public_games INT NOT NULL,
        votes INT DEFAULT 0, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql_proposals);

    // 2. 自动建表：作品总图鉴库 (用来记录每个游戏的真实被提名次数)
    $sql_works = "CREATE TABLE IF NOT EXISTS event_caiqi_works (
        ymgal_id INT PRIMARY KEY,
        title_cn VARCHAR(255) NOT NULL,
        cover_url TEXT,
        nomination_count INT DEFAULT 1 /* 被放入盲盒的次数 */
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql_works);

    // 接收前端数据
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);

    if (!$data || empty($data['author_name']) || empty($data['proposal_data'])) {
        die(json_encode(['status' => 'error', 'message' => '数据破损，请检查提案内容！']));
    }

    $author = $conn->real_escape_string(trim($data['author_name']));
    $games_json = $conn->real_escape_string(json_encode($data['proposal_data'], JSON_UNESCAPED_UNICODE));
    $total = intval($data['total_games']);
    
    if ($total > 12 || $total < 1) {
        die(json_encode(['status' => 'error', 'message' => '系统警报：作品数量异常，突破12部限制！']));
    }
    
    $public_count = 0;
    foreach ($data['proposal_data'] as $game) {
        if ($game['isPublic']) $public_count++;
    }
    $max_public = floor($total / 2);
    if ($public_count > $max_public) {
        die(json_encode(['status' => 'error', 'message' => '系统警报：曝光数量违反了不过半规则！']));
    }

    // 🚨 终极防刷屏：同时拦截代号和物理 IP！
    $check_sql = "SELECT id FROM event_caiqi_proposals WHERE author_name = '$author' OR ip_address = '$ip_address'";
    $check_res = $conn->query($check_sql);
    if ($check_res && $check_res->num_rows > 0) {
        die(json_encode(['status' => 'error', 'message' => '绝密警告：你所在的网络或该代号已经发布过提案，每人限发一份！']));
    }

    // 开启事务处理，确保提案入库和作品记账同时成功或同时失败
    $conn->begin_transaction();

    // 3. 正式把提案数据封存入库！
    $insert_proposal = "INSERT INTO event_caiqi_proposals (author_name, ip_address, games_data, total_games, public_games)
                        VALUES ('$author', '$ip_address', '$games_json', $total, $public_count)";
    $conn->query($insert_proposal);

    // 4. 核心补丁：遍历盲盒里的游戏，写入总作品库！
    foreach ($data['proposal_data'] as $game) {
        $g_id = intval($game['id']);
        $g_title = $conn->real_escape_string($game['title']);
        $g_cover = $conn->real_escape_string($game['cover']);

        // 神奇的 ON DUPLICATE KEY 语法：如果库里没这个游戏就插入，有的话就让 nomination_count 加 1
        $upsert_sql = "INSERT INTO event_caiqi_works (ymgal_id, title_cn, cover_url, nomination_count)
                       VALUES ($g_id, '$g_title', '$g_cover', 1)
                       ON DUPLICATE KEY UPDATE nomination_count = nomination_count + 1";
        $conn->query($upsert_sql);
    }

    $conn->commit(); // 提交修改

    echo json_encode(['status' => 'success', 'message' => '提案封存成功！全域档案库已更新。']);
    $conn->close();

} catch (Exception $e) {
    if(isset($conn)) $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => '服务器崩溃：' . $e->getMessage()]);
}
?>