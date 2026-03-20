<?php
// ======================================================
// === 投票核心处理器 (vote_handler.php) V1.5 拼写修正终极版 ===
// ======================================================

// 开启错误显示，方便调试
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- 1. 设置投票截止日期 ---
// strtotime 将日期字符串转换为计算机可以比较的时间戳
$deadline = strtotime('2025-09-26 00:00:00');
$now = time(); // 获取当前时间的时间戳

if ($now > $deadline) {
    echo json_encode(['success' => false, 'message' => '抱歉，投票已经截止。']);
    exit;
}

// --- 数据库连接信息 ---
$db_host = '127.0.0.1';
$db_name = 'zqz_voting';
$db_user = 'zqz_voting';
$db_pass = '789789'; // !! 确保密码正确 !!

// --- 封装一个统一的JSON响应函数 ---
function send_response($status, $message, $data = []) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

// --- 连接数据库 ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    send_response('error', '数据库连接失败: ' . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ======================= 终极保险措施 (V1.5 拼写修正) =======================
// 检查并创建 characters 表
$conn->query("
    CREATE TABLE IF NOT EXISTS `characters` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `vndb_id` varchar(20) NOT NULL,
      `name` varchar(255) NOT NULL,
      `original_name` varchar(255) DEFAULT NULL, -- !! 核心修正：已将 orginal_name 改为 original_name !!
      `game_title` varchar(255) DEFAULT NULL,
      `image_url` varchar(512) DEFAULT NULL,
      `votes` int(11) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `vndb_id` (`vndb_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 检查并创建 ip_votes 表
$conn->query("
    CREATE TABLE IF NOT EXISTS `ip_votes` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ip_address` varchar(45) NOT NULL,
      `voted_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `ip_address` (`ip_address`),
      KEY `voted_at` (`voted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
// ========================================================================


// --- 1. 接收并验证前端传来的角色ID ---
if (!isset($_POST['vndb_id']) || empty($_POST['vndb_id'])) {
    send_response('error', '无效的请求，未提供角色ID。');
}
$vndb_id_to_vote = $_POST['vndb_id'];

// --- 2. 检查IP地址，防止重复投票 ---
$user_ip = $_SERVER['REMOTE_ADDR'];
$today_start = date('Y-m-d 00:00:00');
$check_ip_stmt = $conn->prepare("SELECT id FROM ip_votes WHERE ip_address = ? AND voted_at >= ?");
if ($check_ip_stmt === false) {
    send_response('error', '数据库预处理语句失败 (检查IP): ' . $conn->error);
}
$check_ip_stmt->bind_param("ss", $user_ip, $today_start);
$check_ip_stmt->execute();
$check_ip_result = $check_ip_stmt->get_result();

if ($check_ip_result->num_rows > 0) {
    $check_ip_stmt->close();
    $conn->close();
    send_response('error', '您今天已经投过票了，请明天再来！');
}
$check_ip_stmt->close();

// --- 3. 检查/插入角色信息 ---
$char_id_in_db = null;
$stmt = $conn->prepare("SELECT id FROM characters WHERE vndb_id = ?");
if ($stmt === false) {
    send_response('error', '数据库预处理语句失败 (查找角色): ' . $conn->error);
}
$stmt->bind_param("s", $vndb_id_to_vote);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $char_id_in_db = $row['id'];
} else {
    $api_url = 'https://api.vndb.org/kana/character';
    $post_data = ['filters' => ['id', '=', $vndb_id_to_vote], 'fields'  => 'id, name, original, vns.title, image.url'];
    $options = ['http' => ['header' => "Content-type: application/json\r\n", 'method' => 'POST', 'content' => json_encode($post_data ), 'timeout' => 15]];
    $context  = stream_context_create($options);
    $response = @file_get_contents($api_url, false, $context);

    if ($response !== FALSE) {
        $data = json_decode($response);
        if (isset($data->results[0])) {
            $char_data = $data->results[0];
            $name = $char_data->name ?? '';
            $original_name = $char_data->original ?? $name;
            $game_title = $char_data->vns[0]->title ?? '未知作品';
            $image_url = $char_data->image->url ?? '';

            $insert_stmt = $conn->prepare("INSERT INTO characters (vndb_id, name, original_name, game_title, image_url, votes) VALUES (?, ?, ?, ?, ?, 0)");
            if ($insert_stmt === false) {
                send_response('error', '数据库预处理语句失败 (插入角色): ' . $conn->error);
            }
            $insert_stmt->bind_param("sssss", $vndb_id_to_vote, $name, $original_name, $game_title, $image_url);
            if ($insert_stmt->execute()) {
                $char_id_in_db = $conn->insert_id;
            }
            $insert_stmt->close();
        }
    }
}
$stmt->close();

// --- 4. 执行投票并记录IP ---
if ($char_id_in_db) {
    $update_stmt = $conn->prepare("UPDATE characters SET votes = votes + 1 WHERE id = ?");
    $update_stmt->bind_param("i", $char_id_in_db);
    $update_stmt->execute();
    $update_stmt->close();

    $log_ip_stmt = $conn->prepare("INSERT INTO ip_votes (ip_address) VALUES (?)");
    $log_ip_stmt->bind_param("s", $user_ip);
    $log_ip_stmt->execute();
    $log_ip_stmt->close();

    send_response('success', '投票成功！感谢您的支持！');
} else {
    send_response('error', '处理投票失败，无法从VNDB获取该角色信息或存入数据库。');
}

$conn->close();
?>
