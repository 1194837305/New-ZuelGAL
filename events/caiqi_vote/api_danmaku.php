<?php
// === api_danmaku.php 战术弹幕通信总线 (实名制认证版) ===
ini_set('display_errors', 0);
error_reporting(E_ALL);
date_default_timezone_set('PRC');
header('Content-Type: application/json; charset=utf-8');

$config_path = '../../config.php'; 
if (!file_exists($config_path)) {
    die(json_encode(['status' => 'error', 'message' => '通信链路断开']));
}
require_once $config_path;

// 🟢 确保 Session 开启，以便读取登录状态
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = db_connect(); // 这里连接的自然是你的 zqz_voting 数据库
    
    // 💡 新增：在这里加上自动建表代码，如果表不存在它就会自己建！
    $sql_create_danmaku = "CREATE TABLE IF NOT EXISTS event_caiqi_danmaku (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_type VARCHAR(20) NOT NULL,
        user_id INT NOT NULL,
        voter_name VARCHAR(100) NOT NULL,
        content VARCHAR(200) NOT NULL,
        ip_address VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql_create_danmaku);
    
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    if (!$data || empty($data['action'])) {
        die(json_encode(['status' => 'error', 'message' => '非法信号']));
    }

    $action = $data['action'];
    $page_type = $conn->real_escape_string($data['page_type'] ?? 'general');
    $ip = $_SERVER['REMOTE_ADDR'];

    // --- 获取弹幕 (任何人都可以看，不需要拦截) ---
    if ($action === 'get') {
        $last_id = intval($data['last_id'] ?? 0);
        $sql = "SELECT id, voter_name, content FROM event_caiqi_danmaku 
                WHERE page_type = '$page_type' AND id > $last_id 
                ORDER BY id ASC LIMIT 50";
        $res = $conn->query($sql);
        
        $danmaku_list = [];
        $new_last_id = $last_id;
        
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $danmaku_list[] = [
                    'id' => $row['id'],
                    'name' => htmlspecialchars($row['voter_name'], ENT_QUOTES),
                    'text' => htmlspecialchars($row['content'], ENT_QUOTES)
                ];
                $new_last_id = $row['id'];
            }
        }
        echo json_encode(['status' => 'success', 'data' => $danmaku_list, 'last_id' => $new_last_id]);
        exit;
    }

    // --- 🟢 发送弹幕 (实名制严格拦截) ---
    if ($action === 'send') {
        
        // 1. 第一步：先从前端数据里把内容摘出来，并去掉首尾空格
        $content = trim($data['content'] ?? '');
        $len = mb_strlen($content, 'utf-8');

        // 2. 第二步：最严格的长度拦截（即使是异常编码也会被拦住）
        if ($content === '' || $len === false || $len > 12) {
            // 注意：这里必须返回 error，前端 JS 才能认出来并弹窗报错
            die(json_encode(['status' => 'error', 'message' => "发射失败：弹幕限12字内 (当前检测长度: {$len})"]));
        }

        // 3. 第三步：登录鉴权
        if (!isset($_SESSION['user_id'])) {
            die(json_encode(['status' => 'error', 'message' => '未登录：只有系统正式社员才能发送弹幕！']));
        }

        // 4. 第四步：身份提取
        $user_id = intval($_SESSION['user_id']);
        $voter_name = $conn->real_escape_string($_SESSION['nickname'] ?? '未知社员');

        // 5. 第五步：冷却机制 (5秒)
        $cool_down = 5; 
        $check_spam = $conn->query("SELECT id FROM event_caiqi_danmaku 
                                    WHERE user_id = $user_id 
                                    AND created_at >= NOW() - INTERVAL $cool_down SECOND");
        if ($check_spam && $check_spam->num_rows > 0) {
            die(json_encode(['status' => 'error', 'message' => "武器过热，请等待 {$cool_down} 秒冷却！"]));
        }

        // 6. 第六步：安全入库
        $safe_content = $conn->real_escape_string($content);
        $conn->query("INSERT INTO event_caiqi_danmaku (page_type, user_id, voter_name, content, ip_address) 
                      VALUES ('$page_type', $user_id, '$voter_name', '$safe_content', '$ip')");
                      
        echo json_encode(['status' => 'success']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '服务器开小差了']);
}