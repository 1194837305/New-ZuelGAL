<?php
// === api_vote_tierlist.php 终极防弹重构版 ===
ini_set('display_errors', 0); // 生产环境关闭错误回显，防止破坏 JSON 格式
error_reporting(E_ALL);
date_default_timezone_set('PRC'); 
header('Content-Type: application/json; charset=utf-8');

$config_path = '../../config.php';
if (!file_exists($config_path)) {
    die(json_encode(['status' => 'error', 'message' => '系统核心丢失！']));
}
require_once $config_path;

try {
    $conn = db_connect();
    
    // 1. 自动建表保护
    $sql_tier_votes = "CREATE TABLE IF NOT EXISTS event_caiqi_tier_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ymgal_id INT NOT NULL,
        voter_name VARCHAR(100) NOT NULL,
        ip_address VARCHAR(50) NOT NULL,
        vote_type VARCHAR(20) NOT NULL, 
        vote_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql_tier_votes);

    // 2. 接收数据
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);

    if (!$data) {
        die(json_encode(['status' => 'error', 'message' => '数据格式致命错误！']));
    }

    // 🟢 VIP通道：拦截前端“动态查询剩余弹药”的请求（必须放在投票安检前面！）
    if (isset($data['action']) && $data['action'] === 'get_ammo') {
        $voter_name = $conn->real_escape_string(trim($data['voter_name'] ?? ''));
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $today = date('Y-m-d');
        
        $love_res = $conn->query("SELECT COUNT(*) as cnt FROM event_caiqi_tier_votes WHERE vote_type = 'love' AND vote_date = '$today' AND (voter_name = '$voter_name' OR ip_address = '$ip_address')");
        $used_love = $love_res ? (int)$love_res->fetch_assoc()['cnt'] : 0;
        
        $normal_res = $conn->query("SELECT COUNT(*) as cnt FROM event_caiqi_tier_votes WHERE vote_type = 'normal' AND vote_date = '$today' AND (voter_name = '$voter_name' OR ip_address = '$ip_address')");
        $used_normal = $normal_res ? (int)$normal_res->fetch_assoc()['cnt'] : 0;
        
        echo json_encode([
            'status' => 'success',
            'remain_love' => max(0, 1 - $used_love),
            'remain_normal' => max(0, 5 - $used_normal)
        ]);
        $conn->close();
        exit; // 查完票数直接退出，不要往下走
    }

    // 🔴 严格安检门：下面是真正的“开火投票”逻辑，必须要作品ID和票种
    if (empty($data['ymgal_id']) || empty($data['voter_name']) || empty($data['vote_type'])) {
        die(json_encode(['status' => 'error', 'message' => '开火指令破损，拒绝执行！']));
    }

    $work_id = intval($data['ymgal_id']);
    $voter_name = $conn->real_escape_string(trim($data['voter_name']));
    $vote_type = $conn->real_escape_string(trim($data['vote_type'])); 
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $today = date('Y-m-d');

    // 3. 时间轴物理封锁
    $shrink_time = strtotime("2026-04-13 00:00:00"); 
    $end_time    = strtotime("2026-04-14 00:00:00"); 
    $now = time();

    if ($now > $end_time) {
        die(json_encode(['status' => 'error', 'message' => '大逃杀已圆满结束，火力已停止。']));
    }

    // 4. 修复：安全获取目标作品的当前战力数据，防备 NULL 黑洞
    $target_votes = 0;
    $target_time = "'1970-01-01 00:00:00'";
    $target_res = $conn->query("SELECT tier_votes, last_voted_at FROM event_caiqi_works WHERE ymgal_id = $work_id");
    if ($target_res && $target_res->num_rows > 0) {
        $row = $target_res->fetch_assoc();
        $target_votes = (int)$row['tier_votes'];
        if (!empty($row['last_voted_at'])) {
            $target_time = "'" . $row['last_voted_at'] . "'";
        }
    }

    if ($now > $shrink_time) {
        // 修复：使用 IFNULL 安全处理零票作品的排名对比
        $rank_sql = "SELECT COUNT(*) + 1 as rank_pos FROM event_caiqi_works 
                     WHERE tier_votes > $target_votes
                     OR (tier_votes = $target_votes AND IFNULL(last_voted_at, '1970-01-01 00:00:00') > $target_time)";
        
        $rank_res = $conn->query($rank_sql);
        if ($rank_res) {
            $rank = $rank_res->fetch_assoc()['rank_pos'];
            if ($rank > 12) {
                die(json_encode(['status' => 'error', 'message' => '【缩圈拦截】该作品已被挤出前 12 名，无法再注入战力！']));
            }
        }
    }

    // 5. 铁壁防御：检查同游戏重复开火（每日每人或每IP 限1发）
    // 必须用 OR：只要这个名字今天投过，或者这个 IP 今天投过，一律拦截！
    $check_dup_sql = "SELECT id FROM event_caiqi_tier_votes WHERE ymgal_id = $work_id AND vote_date = '$today' AND (voter_name = '$voter_name' OR ip_address = '$ip_address')";
    $check_dup = $conn->query($check_dup_sql);
    if ($check_dup && $check_dup->num_rows > 0) {
        die(json_encode(['status' => 'error', 'message' => '你（或该IP）今天已经为这部作品投过票了，请把火力分散给其他作品！']));
    }

    // 6. 铁壁防御：安全统计弹药余量（IP和代号双重锁定）
    $points = 0;
    if ($vote_type === 'love') {
        $love_res = $conn->query("SELECT COUNT(*) as cnt FROM event_caiqi_tier_votes WHERE vote_type = 'love' AND vote_date = '$today' AND (voter_name = '$voter_name' OR ip_address = '$ip_address')");
        $used_love = $love_res ? (int)$love_res->fetch_assoc()['cnt'] : 0;
        
        if ($used_love >= 1) {
            die(json_encode(['status' => 'error', 'message' => '你今天的【真爱票】已经打光了！']));
        }
        $points = 2;
    } else {
        $normal_res = $conn->query("SELECT COUNT(*) as cnt FROM event_caiqi_tier_votes WHERE vote_type = 'normal' AND vote_date = '$today' AND (voter_name = '$voter_name' OR ip_address = '$ip_address')");
        $used_normal = $normal_res ? (int)$normal_res->fetch_assoc()['cnt'] : 0;
        
        if ($used_normal >= 5) {
            die(json_encode(['status' => 'error', 'message' => "你今天的 5 发【普通票】已经全部打光！(已用: $used_normal)"]));
        }
        $points = 1;
    }

    // 7. 事务处理：保证数据一致性
    $conn->begin_transaction();
    try {
        $conn->query("INSERT INTO event_caiqi_tier_votes (ymgal_id, voter_name, ip_address, vote_type, vote_date) VALUES ($work_id, '$voter_name', '$ip_address', '$vote_type', '$today')");

        $conn->query("UPDATE event_caiqi_works SET tier_votes = tier_votes + $points, last_voted_at = CURRENT_TIMESTAMP WHERE ymgal_id = $work_id");

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "注入成功！已为作品提供 **$points 点** 战力支援。"]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    $conn->close();
} catch (Exception $e) {
    // 捕获所有异常并返回标准 JSON
    echo json_encode(['status' => 'error', 'message' => '系统数据库故障，请稍后再试。']);
}

