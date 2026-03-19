<?php
// =================================================================
// == VNDB 角色数据获取与入库脚本 (PHP 起重机) ==
// =================================================================

// --- 第一部分：数据库连接信息 (请在此处填写您的“钥匙”) ---
$db_host = 'localhost';      // 数据库服务器地址，保持默认的 '127.0.0.1' 即可
$db_name = 'zqz_voting';     // 您创建的数据库名
$db_user = 'zqz_voting';     // 您创建的数据库用户名
$db_pass = '789789'; // !! 请在这里替换成您自己的数据库密码 !!

// --- 第二部分：连接到您的数据库 ---
// 使用 mysqli 扩展连接数据库，这是PHP推荐的方式
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 检查连接是否成功
if ($conn->connect_error) {
    // 如果连接失败，打印错误信息并停止运行
    die("数据库连接失败: " . $conn->connect_error);
}
echo "✅ 数据库连接成功！  
";

// 设置字符集为 utf8mb4，以支持各种特殊字符和Emoji
$conn->set_charset("utf8mb4");


// --- 第三部分：从 VNDB API 获取角色数据 ---
$api_url = 'https://api.vndb.org/kana/character';

// 定义我们要发送给 API 的请求内容 (Body )
$post_data = [
    // "filters" 用于筛选，我们暂时不过滤，获取默认的角色
    "filters" => ["id", ">=", "c1"], 
    
    // "fields" 指定我们需要哪些信息
    "fields" => "id, name, image.url, vns.title",
    
    // "results" 指定我们想获取多少条数据 (API上限是100)
    "results" => 20 // 先获取20个作为测试
];

// 将我们的请求数据编码成 JSON 字符串
$json_post_data = json_encode($post_data);

// 初始化一个 cURL 会话，这是PHP里用来发送HTTP请求的强大工具
$ch = curl_init($api_url);

// 设置 cURL 选项
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 设置为 true，表示请求结果将作为字符串返回，而不是直接输出
curl_setopt($ch, CURLOPT_POST, true);             // 设置为 POST 请求
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_post_data); // 设置 POST 请求的 Body 内容
curl_setopt($ch, CURLOPT_HTTPHEADER, [          // 设置 HTTP 请求头
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_post_data)
]);

// 执行 cURL 请求，并获取返回的 JSON 字符串
$response_json = curl_exec($ch);

// 关闭 cURL 会话
curl_close($ch);

// 检查是否成功获取到数据
if (!$response_json) {
    die("从 VNDB API 获取数据失败！");
}
echo "✅ 成功从 VNDB API 获取到数据！  
";

// 将返回的 JSON 字符串解码成 PHP 能理解的对象
$response_data = json_decode($response_json);


// --- 第四部分：将获取到的数据存入我们的数据库 ---

// 准备一个 SQL 插入语句的“模板”
// 我们使用“预处理语句”，这是防止SQL注入攻击的最安全方式
// INSERT INTO characters ... ON DUPLICATE KEY UPDATE ... 
// 这句SQL的意思是：尝试插入一条新数据。如果 `vndb_id` 这个唯一的键已经存在了，那就不要报错，而是更新这条记录。
// 这样做可以让我们反复运行这个脚本，而不会因为重复数据而出错。
$stmt = $conn->prepare("
    INSERT INTO characters (vndb_id, name, game_title, image_url) 
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        name = VALUES(name), 
        game_title = VALUES(game_title), 
        image_url = VALUES(image_url)
");

$imported_count = 0; // 初始化一个计数器

// 遍历从 API 获取到的每一个角色数据
foreach ($response_data->results as $character) {
    
    // 从复杂的数据结构中，提取我们需要的信息
    $vndb_id = $character->id;
    $name = $character->name;
    
    // 游戏标题可能在 vns 数组的第一个元素里，需要做个判断
    $game_title = '未知作品'; // 给一个默认值
    if (!empty($character->vns)) {
        $game_title = $character->vns[0]->title;
    }
    
    // 图片URL也可能不存在，给个判断
    $image_url = ''; // 默认空
    if (isset($character->image) && isset($character->image->url)) {
        $image_url = $character->image->url;
    }

    // 将我们提取好的变量，绑定到 SQL 模板的问号上
    // "ssss" 表示我们将要绑定的4个变量都是字符串(String)类型
    // ==================== 终极调试：照妖镜 ====================
if ($stmt === false) {
    die("致命错误：prepare() 失败！MySQL返回的错误是: " . $conn->error);
}
// =======================================================

    $stmt->bind_param("ssss", $vndb_id, $name, $game_title, $image_url);

    // 执行这条准备好的 SQL 语句
    if ($stmt->execute()) {
        $imported_count++; // 如果执行成功，计数器+1
    }
}

echo "✅ 数据处理完成！共导入或更新了 " . $imported_count . " 条角色数据。  
";

// 关闭预处理语句和数据库连接
$stmt->close();
$conn->close();

echo "🎉 全部任务完成！";

?>
