<?php
// ====================================================================
// === 投票核心处理器 (V3.1 - 昵称逻辑最终修正版) ===
// ====================================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- 截止日期 (不变) ---
$deadline = strtotime('2025-09-26 00:00:00');
if (time() > $deadline) {
    echo json_encode(['status' => 'error', 'message' => '抱歉，投票已经截止。']);
    exit;
}

// --- 数据库连接信息 (不变) ---
$db_host = 'localhost';
$db_name = 'zqz_voting';
$db_user = 'zqz_voting';
$db_pass = '789789';

// --- 统一响应函数 (不变) ---
function send_response($status, $message, $data = []) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

// --- 连接数据库 (不变) ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    send_response('error', '数据库连接失败: ' . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- 自动维护表结构 (不变) ---
$result_nick = $conn->query("SHOW COLUMNS FROM `ip_votes` LIKE 'nickname'");
if ($result_nick->num_rows == 0) {
    $conn->query("ALTER TABLE `ip_votes` ADD `nickname` VARCHAR(255) NOT NULL AFTER `ip_address`");
}
$result_char = $conn->query("SHOW COLUMNS FROM `ip_votes` LIKE 'voted_for_vndb_id'");
if ($result_char->num_rows == 0) {
    $conn->query("ALTER TABLE `ip_votes` ADD `voted_for_vndb_id` VARCHAR(20) NOT NULL AFTER `nickname`");
}

// --- 1. 接收并验证前端传来的数据 (不变) ---
if (!isset($_POST['vndb_id']) || empty($_POST['vndb_id'])) {
    send_response('error', '无效的请求，未提供角色ID。');
}
if (!isset($_POST['nickname']) || empty(trim($_POST['nickname']))) {
    send_response('error', '无效的请求，未提供昵称。');
}
$vndb_id_to_vote = $_POST['vndb_id'];
$nickname = trim($_POST['nickname']);

// --- 2. 全新防刷票检查 (逻辑修正) ---
$user_ip = $_SERVER['REMOTE_ADDR'];
$today_start = date('Y-m-d 00:00:00');

// 准备一个查询，获取今天该IP的所有投票记录
$stmt = $conn->prepare("SELECT nickname, voted_for_vndb_id FROM ip_votes WHERE ip_address = ? AND voted_at >= ?");
$stmt->bind_param("ss", $user_ip, $today_start);
$stmt->execute();
$result = $stmt->get_result();
$todays_votes_by_ip = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 检查1：总票数是否已达上限
if (count($todays_votes_by_ip) >= 5) {
    send_response('error', '您今天已经投满5票了，感谢您的热情参与！');
}

// 检查2：今天是否已投过此角色
$voted_chars_today = array_column($todays_votes_by_ip, 'voted_for_vndb_id');
if (in_array($vndb_id_to_vote, $voted_chars_today)) {
    send_response('error', '您今天已经为这位角色投过票了，请把机会留给其他人吧！');
}

// ======================================================
// ========== ▼▼▼ 核心逻辑修正点 ▼▼▼ ==========
// ======================================================
// 检查3：昵称使用规则
if (!empty($todays_votes_by_ip)) {
    // 如果今天已经投过票了 (不是第一票)
    $first_vote_nickname = $todays_votes_by_ip[0]['nickname'];
    if ($nickname !== $first_vote_nickname) {
        // 检查当前提交的昵称是否与今天第一次投票时使用的昵称一致
        send_response('error', "您今天已使用昵称 '{$first_vote_nickname}' 进行过投票，请勿更换昵称！");
    }
} else {
    // 如果今天是该IP的第一票，则检查该昵称是否已被其他IP使用
    $check_nick_stmt = $conn->prepare("SELECT id FROM ip_votes WHERE nickname = ? AND voted_at >= ?");
    $check_nick_stmt->bind_param("ss", $nickname, $today_start);
    $check_nick_stmt->execute();
    if ($check_nick_stmt->get_result()->num_rows > 0) {
        $check_nick_stmt->close();
        send_response('error', '这个昵称今天已经被其他用户使用了，请更换一个！');
    }
    $check_nick_stmt->close();
}
// ======================================================
// ========== ▲▲▲ 核心逻辑修正点 ▲▲▲ ==========
// ======================================================


// --- 3. 检查/插入角色信息 (不变) ---
$char_id_in_db = null;
// (此部分代码与之前版本完全相同，为简洁省略，实际代码中是完整的)
$stmt_find = $conn->prepare("SELECT id FROM characters WHERE vndb_id = ?");
if ($stmt_find === false) { send_response('error', '数据库预处理语句失败 (查找角色): ' . $conn->error); }
$stmt_find->bind_param("s", $vndb_id_to_vote);
$stmt_find->execute();
$result_find = $stmt_find->get_result();
if ($result_find->num_rows > 0) {
    $char_id_in_db = $result_find->fetch_assoc()['id'];
} else {
    $api_url = 'https://api.vndb.org/kana/character';
    $post_data = ['filters' => ['id', '=', $vndb_id_to_vote], 'fields'  => 'id, name, original, vns.title, image.url'];
    $options = ['http' => ['header' => "Content-type: application/json\r\n", 'method' => 'POST', 'content' => json_encode($post_data  ), 'timeout' => 15]];
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
            if ($insert_stmt === false) { send_response('error', '数据库预处理语句失败 (插入角色): ' . $conn->error); }
            $insert_stmt->bind_param("sssss", $vndb_id_to_vote, $name, $original_name, $game_title, $image_url);
            if ($insert_stmt->execute()) { $char_id_in_db = $conn->insert_id; }
            $insert_stmt->close();
        }
    }
}
$stmt_find->close();


// --- 4. 执行投票并记录IP、昵称和投票对象 (不变) ---
if ($char_id_in_db) {
    $conn->begin_transaction();
    try {
        $update_stmt = $conn->prepare("UPDATE characters SET votes = votes + 1 WHERE id = ?");
        $update_stmt->bind_param("i", $char_id_in_db);
        $update_stmt->execute();
        $update_stmt->close();

        $log_stmt = $conn->prepare("INSERT INTO ip_votes (ip_address, nickname, voted_for_vndb_id) VALUES (?, ?, ?)");
        $log_stmt->bind_param("sss", $user_ip, $nickname, $vndb_id_to_vote);
        $log_stmt->execute();
        $log_stmt->close();

        $conn->commit();
        send_response('success', '投票成功！感谢您的支持！');

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        send_response('error', '数据库事务处理失败: ' . $exception->getMessage());
    }
} else {
    send_response('error', '处理投票失败，无法从VNDB获取该角色信息或存入数据库。');
}

$conn->close();
?>
