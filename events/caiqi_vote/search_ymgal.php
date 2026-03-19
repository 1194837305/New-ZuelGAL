<?php
// === 月幕API 数据库缓存版 ===
header('Content-Type: application/json;charset=utf-8');
require_once '../../config.php'; // 引入数据库配置

$keyword = $_GET['keyword'] ?? '';
if (empty($keyword)) {
    echo json_encode(['status' => 'error', 'message' => '搜索关键词不能为空']);
    exit;
}

try {
    $conn = db_connect();
    
    // 确保设置表存在
    $conn->query("CREATE TABLE IF NOT EXISTS event_caiqi_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    function getYmgalToken($conn) {
        // 1. 先看数据库里有没有牌子，且没过期
        $res = $conn->query("SELECT setting_value FROM event_caiqi_settings WHERE setting_key = 'ymgal_token_expire'");
        if ($res && $res->num_rows > 0) {
            $expire_time = intval($res->fetch_row()[0]);
            if (time() < ($expire_time - 60)) {
                $token_res = $conn->query("SELECT setting_value FROM event_caiqi_settings WHERE setting_key = 'ymgal_token'");
                return $token_res->fetch_row()[0];
            }
        }

        // 2. 如果没有或过期了，重新去月幕网申请
        $auth_url = "https://www.ymgal.games/oauth/token?grant_type=client_credentials&client_id=ymgal&client_secret=luna0327&scope=public";
        $auth_response = @file_get_contents($auth_url);
        
        if ($auth_response) {
            $data = json_decode($auth_response, true);
            if (isset($data['access_token'])) {
                $token = $data['access_token'];
                $expire = time() + $data['expires_in'];
                
                // 3. 把新牌子存进数据库
                $conn->query("INSERT INTO event_caiqi_settings (setting_key, setting_value) VALUES ('ymgal_token', '$token') ON DUPLICATE KEY UPDATE setting_value = '$token'");
                $conn->query("INSERT INTO event_caiqi_settings (setting_key, setting_value) VALUES ('ymgal_token_expire', '$expire') ON DUPLICATE KEY UPDATE setting_value = '$expire'");
                
                return $token;
            }
        }
        return false;
    }

    $access_token = getYmgalToken($conn);
    if (!$access_token) {
        die(json_encode(['status' => 'error', 'message' => '无法获取月幕API通行证，请重试']));
    }

    // 发起搜索
    $search_url = "https://www.ymgal.games/open/archive/search-game?mode=list&pageNum=1&pageSize=12&keyword=" . urlencode($keyword);
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json;charset=utf-8\r\nAuthorization: Bearer " . $access_token . "\r\nversion: 1\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $search_response = @file_get_contents($search_url, false, $context);

    if ($search_response === false) {
        die(json_encode(['status' => 'error', 'message' => '请求月幕数据库超时']));
    }

    $response_data = json_decode($search_response, true);
    if (isset($response_data['success']) && $response_data['success'] === true) {
        $games_list = $response_data['data']['result'] ?? [];
        $formatted_results = [];
        foreach ($games_list as $game) {
            $formatted_results[] = [
                'ymgal_id'     => $game['id'],
                'title_cn'     => $game['chineseName'] ?: $game['name'],
                'cover_url'    => $game['mainImg'],
                'release_date' => $game['releaseDate'] ?? '未知发售日'
            ];
        }
        echo json_encode(['status' => 'success', 'data' => $formatted_results]);
    } else {
        echo json_encode(['status' => 'error', 'message' => '月幕服务器未找到数据']);
    }
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '服务器错误']);
}
?>