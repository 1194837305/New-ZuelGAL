<?php
// update_daily_summary.php

// 数据库连接配置
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'zqz_voting');
define('DB_PASSWORD', '789789'); // 替换为你的密码
define('DB_NAME', 'zqz_voting');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 决定要处理的日期。如果URL参数传了date, 就用那个日期, 否则默认处理昨天。
// 例如，访问 update_daily_summary.php?date=2025-09-18 就可以手动生成9月18号的快照
$target_date_str = $_GET['date'] ?? date('Y-m-d', strtotime('-1 day'));
$target_date = date('Y-m-d', strtotime($target_date_str));
$target_timestamp_end_of_day = strtotime($target_date . ' 23:59:59');

echo "开始为日期: {$target_date} 生成票数快照...\n";

// 查询 ip_votes 表，统计出截至目标日期（含）为止，每个角色的总票数
$sql = "
    SELECT voted_for_vndb_id, COUNT(*) as total_votes
    FROM ip_votes
    WHERE voted_at <= ?
    GROUP BY voted_for_vndb_id
";

$stmt = $conn->prepare($sql);
$target_datetime_str = $target_date . ' 23:59:59';
$stmt->bind_param("s", $target_datetime_str);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $conn->begin_transaction();
    try {
        $insert_stmt = $conn->prepare(
            "INSERT INTO daily_votes (character_vndb_id, vote_date, total_votes_on_date) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE total_votes_on_date = VALUES(total_votes_on_date)"
        );

        while($row = $result->fetch_assoc()) {
            $vndb_id = $row['voted_for_vndb_id'];
            $total_votes = $row['total_votes'];
            
            $insert_stmt->bind_param("ssi", $vndb_id, $target_date, $total_votes);
            $insert_stmt->execute();
            echo "角色 {$vndb_id} 在 {$target_date} 的总票数快照为: {$total_votes}\n";
        }
        
        $insert_stmt->close();
        $conn->commit();
        echo "\n日期 {$target_date} 的票数快照更新成功！\n";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        die("更新失败: " . $exception->getMessage());
    }
} else {
    echo "在 {$target_date} 或之前没有任何投票记录。\n";
}

$stmt->close();
$conn->close();
?>
