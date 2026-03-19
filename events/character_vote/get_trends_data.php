<?php
// get_trends_data.php (Final Production Version)

// 1. 数据库连接配置
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'zqz_voting');
define('DB_PASSWORD', '789789'); // 别忘了填回你的密码
define('DB_NAME', 'zqz_voting');

header('Content-Type: application/json');

// 2. 预定义颜色
$color_palette = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF', '#F7464A', '#46BFBD', '#FDB45C'];
$color_index = 0;

// 3. 获取前端请求
$vndb_ids = json_decode(file_get_contents('php://input'), true);
if (empty($vndb_ids) || !is_array($vndb_ids)) {
    echo json_encode(['labels' => [], 'datasets' => []]);
    exit;
}

// 4. 连接数据库
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500 );
    // 在生产环境中，不暴露详细错误信息给前端
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['error' => '服务器内部错误']);
    exit;
}
$conn->set_charset("utf8mb4");

// 5. 准备数据结构
$response_data = ['labels' => [], 'datasets' => []];
$all_dates = [];
$character_data = [];

// 6. 查询历史数据 (daily_votes)
$placeholders = implode(',', array_fill(0, count($vndb_ids), '?'));
$sql_history = "SELECT character_vndb_id, vote_date, total_votes_on_date FROM daily_votes WHERE character_vndb_id IN ($placeholders)";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param(str_repeat('s', count($vndb_ids)), ...$vndb_ids);
$stmt_history->execute();
$result_history = $stmt_history->get_result();
while ($row = $result_history->fetch_assoc()) {
    $all_dates[$row['vote_date']] = true;
    $character_data[$row['character_vndb_id']]['votes'][$row['vote_date']] = (int)$row['total_votes_on_date'];
}
$stmt_history->close();

// 7. 查询今天的实时数据和角色名 (characters)
$sql_names = "SELECT vndb_id, name FROM characters WHERE vndb_id IN ($placeholders)";
$stmt_names = $conn->prepare($sql_names);
$stmt_names->bind_param(str_repeat('s', count($vndb_ids)), ...$vndb_ids);
$stmt_names->execute();
$result_names = $stmt_names->get_result();
while ($row = $result_names->fetch_assoc()) {
    $character_data[$row['vndb_id']]['name'] = $row['name'];
}
$stmt_names->close();

// 8. 构建最终的返回数据
if (!empty($all_dates)) {
    $response_data['labels'] = array_keys($all_dates);
    sort($response_data['labels']);
}

foreach ($vndb_ids as $vndb_id) {
    if (!isset($character_data[$vndb_id])) continue;

    $dataset = ['label' => $character_data[$vndb_id]['name'], 'data' => [], 'borderColor' => $color_palette[$color_index % count($color_palette)], 'fill' => false, 'tension' => 0.1];
    $last_votes = 0;
    foreach ($response_data['labels'] as $date) {
        $last_votes = $character_data[$vndb_id]['votes'][$date] ?? $last_votes;
        $dataset['data'][] = $last_votes;
    }
    $response_data['datasets'][] = $dataset;
    $color_index++;
}

// 9. 输出JSON
echo json_encode($response_data);
?>
