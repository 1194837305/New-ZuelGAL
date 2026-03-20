<?php
// === api_vote_tierlist.php 终极战火版 ===
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('PRC'); // 强制中国时区
header('Content-Type: application/json');

$config_path = '../../config.php';
if (!file_exists($config_path)) {
    die(json_encode(['status' => 'error', 'message' => '系统核心丢失！']));
}
require_once $config_path;

try {
    $conn = db_connect();
    
    // 1. 自动建表：淘汰赛开火记录表
    $sql_tier_votes = "CREATE TABLE IF NOT EXISTS event_caiqi_tier_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ymgal_id INT NOT NULL,
        voter_name VARCHAR(100) NOT NULL,
        ip_address VARCHAR(50) NOT NULL,
        vote_type VARCHAR(20) NOT NULL, /* 'love' 或 'normal' */
        vote_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql_tier_votes);

    // 2. 接收并解析指令
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);

    if (!$data || empty($data['ymgal_id']) || empty($data['voter_name']) || empty($data['vote_type'])) {
        die(json_encode(['status' => 'error', 'message' => '开火指令破损！']));
    }

    $work_id = intval($data['ymgal_id']);
    $voter_name = $conn->real_escape_string(trim($data['voter_name']));
    $vote_type = $conn->real_escape_string($data['vote_type']); 
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $today = date('Y-m-d');

    // 3. 时间轴物理封锁
    $shrink_time = strtotime("2026-04-27 00:00:00"); // 48小时缩圈
    $end_time    = strtotime("2026-04-28 00:00:00"); // 72小时总截止
    $now = time();

    if ($now > $end_time) {
        die(json_encode(['status' => 'error', 'message' => '大逃杀已圆满结束，火力已停止。']));
    }

    if ($now > $shrink_time) {
        // 【核心排名判定】
        // 规则：比我分高的，或者分一样但得票时间比我晚（更热乎）的，排在我前面
        $rank_sql = "SELECT COUNT(*) + 1 as rank_pos FROM event_caiqi_works 
                     WHERE tier_votes > (SELECT tier_votes FROM event_caiqi_works WHERE ymgal_id = $work_id)
                     OR (tier_votes = (SELECT tier_votes FROM event_caiqi_works WHERE ymgal_id = $work_id) 
                         AND last_voted_at > (SELECT last_voted_at FROM event_caiqi_works WHERE ymgal_id = $work_id))";
        
        $rank_res = $conn->query($rank_sql);
        $rank = $rank_res->fetch_assoc()['rank_pos'];

        if ($rank > 12) {
            die(json_encode(['status' => 'error', 'message' => '【缩圈拦截】该作品已被挤出前 12 名，无法再注入战力！']));
        }
    }

    // 4. 检查同游戏重复开火（每日每人每作限1发）
    $check_dup = $conn->query("SELECT id FROM event_caiqi_tier_votes WHERE ymgal_id = $work_id AND vote_date = '$today' AND (voter_name = '$voter_name' OR ip_address = '$ip_address')");
    if ($check_dup && $check_dup->num_rows > 0) {
        die(json_encode(['status' => 'error', 'message' => '你今天已经为这部作品投过票了，请把火力分散给其他作品！']));
    }

    // 5. 检查每日弹药余量
    if ($vote_type === 'love') {
        $used_love = $conn->query("SELECT COUNT(*) FROM event_caiqi_tier_votes WHERE vote_type = 'love' AND vote_date = '$today' AND (voter_name = '$voter_name' OR ip_address = '$ip_address')")->fetch_row()[0];
        if ($used_love >= 1) {
            die(json_encode(['status' => 'error', 'message' => '你今天的【真爱票】已经打光了！']));
        }
        $points = 2;
    } else {
        $used_normal = $conn->query("SELECT COUNT(*) FROM event_caiqi_tier_votes WHERE vote_type = 'normal' AND vote_date = '$today' AND (voter_name = '$voter_name' OR ip_address = '$ip_address')")->fetch_row()[0];
        if ($used_normal >= 5) {
            die(json_encode(['status' => 'error', 'message' => '你今天的 5 发【普通票】已经全部打光！']));
        }
        $points = 1;
    }

    // 6. 事务处理：正式注能
    $conn->begin_transaction();
    try {
        // 记录投票日志
        $conn->query("INSERT INTO event_caiqi_tier_votes (ymgal_id, voter_name, ip_address, vote_type, vote_date) VALUES ($work_id, '$voter_name', '$ip_address', '$vote_type', '$today')");

        // 更新作品票数，同时刷新 last_voted_at 触发“后来者居上”
        $conn->query("UPDATE event_caiqi_works SET tier_votes = tier_votes + $points, last_voted_at = CURRENT_TIMESTAMP WHERE ymgal_id = $work_id");

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "注入成功！已为作品提供 **$points 点** 战力支援。"]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    $conn->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '系统故障：' . $e->getMessage()]);
}