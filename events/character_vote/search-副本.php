<?php
// ==================================================
// === 智能检索投票站 搜索结果页 (search.php) V1.1 ===
// ==================================================

// --- 获取用户从主页传来的搜索关键词 ---
$keyword = $_GET['keyword'] ?? '';

if (empty($keyword)) {
    header('Location: /'); // 使用绝对路径跳转回主页
    exit;
}

// --- 调用VNDB API进行模糊搜索 ---
$api_url = 'https://api.vndb.org/kana/character';
$characters_from_api = [];

$post_data = [
    'filters' => ['and', ['sex', '=', 'f'], ['search', '=', $keyword]],
    'fields'  => 'id, name, original, vns.title, image.url',
    'sort'    => 'searchrank',
    'results' => 25
];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($post_data ),
        'timeout' => 15
    ],
];
$context  = stream_context_create($options);
$response = @file_get_contents($api_url, false, $context); // 使用@抑制可能的警告

if ($response !== FALSE) {
    $data = json_decode($response);
    if (isset($data->results)) {
        $characters_from_api = $data->results;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>“<?php echo htmlspecialchars($keyword); ?>”的搜索结果</title>
    <style>
        body { font-family: sans-serif; background-color: #f0f2f5; color: #333; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .title { text-align: center; }
        .title a { text-decoration: none; color: #2563eb; font-weight: normal; font-size: 16px; }
        .character-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; }
        .character-card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; padding: 15px; transition: transform 0.2s, box-shadow 0.2s; }
        .character-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .character-card img { width: 100%; height: 250px; object-fit: cover; border-radius: 4px; background-color: #eee; }
        .character-card h3 { font-size: 16px; margin: 10px 0 5px; height: 40px; overflow: hidden; }
        .vote-button { background-color: #42b72a; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 10px; font-weight: bold; width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">
            “<?php echo htmlspecialchars($keyword); ?>” 的搜索结果
              

            <a href="/">返回首页</a>
        </h1>

        <div class="character-grid">
            <?php if (empty($characters_from_api)): ?>
                <p style="grid-column: 1 / -1; text-align:center; color:#999;">
                    未能找到相关的女性角色。请尝试更换关键词或检查拼写。
                </p>
            <?php else: ?>
                <?php foreach ($characters_from_api as $char): ?>
                    <div class="character-card">
                        <img src="<?php echo htmlspecialchars($char->image->url ?? ''); ?>" alt="<?php echo htmlspecialchars($char->name ?? ''); ?>">
                        <h3><?php echo htmlspecialchars($char->original ?? $char->name); ?></h3>
                        <button class="vote-button" onclick="voteForCharacter('<?php echo $char->id; ?>', this)">
                            为她投票
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

<script>
function voteForCharacter(vndbId, buttonElement) {
    buttonElement.disabled = true;
    buttonElement.textContent = '处理中...';

    const formData = new FormData();
    formData.append('vndb_id', vndbId);

    fetch('vote_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('投票成功！感谢您的支持！');
            buttonElement.textContent = '投票成功';
            buttonElement.style.backgroundColor = '#28a745';
        } else {
            alert('投票失败：' + data.message);
            buttonElement.textContent = '投票失败';
            setTimeout(() => { // 2秒后恢复按钮
                buttonElement.disabled = false;
                buttonElement.textContent = '为她投票';
                buttonElement.style.backgroundColor = '#42b72a';
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('发生网络错误，请稍后重试。');
        buttonElement.disabled = false;
        buttonElement.textContent = '为她投票';
    });
}
</script>

</body>
</html>
