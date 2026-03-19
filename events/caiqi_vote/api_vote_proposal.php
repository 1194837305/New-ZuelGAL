<?php
// === api_vote_proposal.php (终极防作弊版) ===
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$config_path = '../../config.php';
if (!file_exists($config_path)) {
    die(json_encode(['status' => 'error', 'message' => '系统错误：配置文件丢失']));
}
require_once $config_path;

try {
    $conn = db_connect();

    $deadline = strtotime("2026-04-20 23:59:59");
if (time() > $deadline) {
    die(json_encode(['status' => 'error', 'message' => '海选档案已封存，无法再进行提案或投票。']));
}

    // 自动建表（确保投票记录表存在）
    $sql_votes = "CREATE TABLE IF NOT EXISTS event_caiqi_proposal_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proposal_id INT NOT NULL,
        voter_name VARCHAR(100) NOT NULL,
        ip_address VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql_votes);

    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);

    if (!$data || empty($data['proposal_id']) || empty($data['voter_name'])) {
        die(json_encode(['status' => 'error', 'message' => '数据破损，请重新尝试！']));
    }

    $proposal_id = intval($data['proposal_id']);
    $voter_name = $conn->real_escape_string(trim($data['voter_name']));
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // 🚨 终极防线一：绝对禁止“自产自销”（不能投自己的盲盒）
    // 我们先去提案库里查一下，现在要投的这个提案，它的作者是谁？IP 是多少？
    $check_self_sql = "SELECT author_name, ip_address FROM event_caiqi_proposals WHERE id = $proposal_id";
    $self_res = $conn->query($check_self_sql);
    if ($self_res && $self_res->num_rows > 0) {
        $proposal_info = $self_res->fetch_assoc();
        
        // 如果投票人的名字和提案作者一样，或者投票人的 IP 和提案提交时的 IP 一样，直接按“给自己投票”处理！
        if (strtolower($proposal_info['author_name']) === strtolower($voter_name) || $proposal_info['ip_address'] === $ip_address) {
            die(json_encode(['status' => 'error', 'message' => '防作弊拦截：你不能把神圣的一票投给【自己】的盲盒！']));
        }
    } else {
        die(json_encode(['status' => 'error', 'message' => '档案丢失：该提案可能已被管理员抹除。']));
    }

    // 🚨 终极防线二：每人（每IP）整个海选期仅限 1 票
    $check_sql = "SELECT id FROM event_caiqi_proposal_votes WHERE voter_name = '$voter_name' OR ip_address = '$ip_address'";
    $check_res = $conn->query($check_sql);
    
    if ($check_res && $check_res->num_rows > 0) {
        die(json_encode(['status' => 'error', 'message' => '系统安检：你或你所在的网络已经投过票了，请勿重复刷票！']));
    }

    // 开启事务，保证计票绝对安全
    $conn->begin_transaction();

    // 1. 记录投票人信息
    $insert_vote = "INSERT INTO event_caiqi_proposal_votes (proposal_id, voter_name, ip_address) VALUES ($proposal_id, '$voter_name', '$ip_address')";
    $conn->query($insert_vote);

    // 2. 给该提案增加 1 票
    $update_proposal = "UPDATE event_caiqi_proposals SET votes = votes + 1 WHERE id = $proposal_id";
    $conn->query($update_proposal);

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => '投票成功！战局已被改写。']);
    
    $conn->close();
} catch (Exception $e) {
    if(isset($conn)) $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => '服务器崩溃：' . $e->getMessage()]);
}
?>