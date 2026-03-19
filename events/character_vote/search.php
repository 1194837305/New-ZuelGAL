<?php
// ==================================================
// === 智能检索投票站 搜索结果页 (安全计票最终版) ===
// ==================================================

// --- (原有PHP逻辑，获取VNDB数据) ---
$keyword = $_GET['keyword'] ?? '';
if (empty($keyword)) {
    header('Location: /');
    exit;
}
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
$response = @file_get_contents($api_url, false, $context);
if ($response !== FALSE) {
    $data = json_decode($response);
    if (isset($data->results)) {
        $characters_from_api = $data->results;
    }
}

// ======================================================
// ========== 1. 新增：安全地查询本地票数 ==========
// ======================================================
$local_votes = []; // 创建一个空数组，用于存储本地票数
if (!empty($characters_from_api)) {
    // 提取所有 vndb_id
    $vndb_ids = array_map(function($char) {
        return $char->id;
    }, $characters_from_api);

    // 只有在ID列表不为空时才进行数据库操作
    if (!empty($vndb_ids)) {
        // --- 数据库连接信息 ---
        $db_host = '127.0.0.1';
        $db_name = 'zqz_voting';
        $db_user = 'zqz_voting';
        $db_pass = '789789';
        
        // 使用@抑制连接错误，我们自己处理
        $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

        // 健壮性检查：确保即使数据库连接失败，后续页面也能正常渲染
        if ($conn && !$conn->connect_error) {
            $conn->set_charset("utf8mb4");
            
            // 为了安全，对每个ID进行转义处理
            $escaped_ids = array_map([$conn, 'real_escape_string'], $vndb_ids);
            $in_clause = "'" . implode("','", $escaped_ids) . "'";
            
            $sql = "SELECT vndb_id, votes FROM characters WHERE vndb_id IN ($in_clause)";
            $result = $conn->query($sql);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $local_votes[$row['vndb_id']] = $row['votes'];
                }
            }
            // 无论如何，最后都关闭连接
            $conn->close();
        }
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
        /* (所有CSS样式保持不变) */
        .background-slider { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; overflow: hidden; }
        .bg-slide { position: absolute; width: 100%; height: 100%; background-size: cover; background-position: center center; opacity: 0; transition: opacity 1.5s ease-in-out; transform: scale(1.1); filter: blur(8px); }
        .bg-slide:nth-child(1) { background-image: url('bg1.jpg'); animation: kenBurns 20s linear infinite; }
        .bg-slide:nth-child(2) { background-image: url('bg2.jpg'); animation: kenBurns-alt 20s linear infinite; }
        .bg-slide:nth-child(3) { background-image: url('bg3.jpg'); animation: kenBurns 20s linear infinite reverse; }
        .bg-slide:nth-child(4) { background-image: url('bg4.jpg'); animation: kenBurns-alt 20s linear infinite reverse; }
        .bg-slide:nth-child(5) { background-image: url('bg5.jpg'); animation: kenBurns 20s linear infinite; }
        .bg-slide.active { opacity: 1; }
        @keyframes kenBurns { 0% { transform: scale(1.1) translate(0, 0); } 50% { transform: scale(1.2) translate(-5%, 5%); } 100% { transform: scale(1.1) translate(0, 0); } }
        @keyframes kenBurns-alt { 0% { transform: scale(1.1) translate(0, 0); } 50% { transform: scale(1.2) translate(5%, -5%); } 100% { transform: scale(1.1) translate(0, 0); } }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', sans-serif; margin: 0; padding: 40px 20px; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; box-sizing: border-box; }
        .container { width: 100%; max-width: 1200px; border-radius: 16px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2); padding: 30px; box-sizing: border-box; position: relative; z-index: 1; background-color: rgba(255, 255, 255, 0.75); backdrop-filter: blur(12px) saturate(110%); -webkit-backdrop-filter: blur(12px) saturate(110%); border: 1px solid rgba(255, 255, 255, 0.2); }
        #music-player { position: fixed; bottom: 25px; right: 25px; width: 50px; height: 50px; background-color: rgba(0, 0, 0, 0.5); border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); transition: transform 0.3s ease, background-color 0.3s ease; z-index: 999; }
        #music-player:hover { transform: scale(1.1); background-color: rgba(0, 0, 0, 0.7); }
        #music-player::before { content: '🎵'; font-size: 24px; color: white; }
        #music-player.playing::before { animation: spin 4s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .title { text-align: center; color: #2c3e50; font-size: 28px; }
        .title a { text-decoration: none; color: #3498db; font-weight: bold; font-size: 16px; margin-left: 15px; }
        .character-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; }
        .character-card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; padding: 15px; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; }
        .character-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .character-card img { width: 100%; height: 250px; object-fit: cover; border-radius: 4px; background-color: #eee; }
        .character-card h3 { font-size: 16px; margin: 10px 0 5px; height: 40px; overflow: hidden; color: #333; flex-grow: 1; }
        .vote-button { background-color: #42b72a; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 10px; font-weight: bold; width: 100%; }
        
        /* ====================================================== */
        /* ========== 2. 新增：票数显示样式 ========== */
        /* ====================================================== */
        .current-votes {
            font-size: 18px;
            font-weight: bold;
            color: #e74c3c; /* 红色，与主页排行榜票数颜色一致 */
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- (背景和播放器HTML保持不变) -->
    <div class="background-slider">
        <div class="bg-slide"></div><div class="bg-slide"></div><div class="bg-slide"></div><div class="bg-slide"></div><div class="bg-slide"></div>
    </div>
    <audio id="bg-music" loop><source src="kamiu.mp3" type="audio/mpeg"></audio>
    <div id="music-player" title="点击播放/暂停背景音乐"></div>

    <!-- 页面主体 -->
    <div class="container">
        <h1 class="title">
            “<?php echo htmlspecialchars($keyword); ?>” 的搜索结果
            <a href="/">返回首页</a>
        </h1>

        <div class="character-grid">
            <?php if (empty($characters_from_api)): ?>
                <p style="grid-column: 1 / -1; text-align:center; color:#888; padding: 40px; font-size: 18px;">
                    未能找到相关的女性角色。请尝试更换关键词或检查拼写。
                </p>
            <?php else: ?>
                <?php foreach ($characters_from_api as $char): ?>
                    <div class="character-card">
                        <img src="<?php echo htmlspecialchars($char->image->url ?? 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($char->name ?? ''); ?>">
                        <h3><?php echo htmlspecialchars($char->original ?? $char->name); ?></h3>
                        
                        <!-- ====================================================== -->
                        <!-- ========== 3. 新增：显示票数 ========== -->
                        <!-- ====================================================== -->
                        <div class="current-votes">
                            当前票数: <?php echo $local_votes[$char->id] ?? 0; ?>
                        </div>

                        <button class="vote-button" onclick="voteForCharacter('<?php echo $char->id; ?>', this)">
                            为她投票
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

<script>
    // (所有JS代码保持不变)
    function backgroundSlider() {
        const slides = document.querySelectorAll('.bg-slide');
        let currentSlide = 0;
        const slideOrder = Array.from({length: slides.length}, (_, i) => i).sort(() => Math.random() - 0.5);
        function showSlide(index) { slides.forEach((slide) => slide.classList.remove('active')); slides[slideOrder[index]].classList.add('active'); }
        showSlide(currentSlide);
        setInterval(() => { currentSlide = (currentSlide + 1) % slides.length; showSlide(currentSlide); }, 7000);
    }
    function initMusicPlayer() {
        const playerButton = document.getElementById('music-player');
        const audio = document.getElementById('bg-music');
        let isPlaying = false;
        function playMusic() { if(audio.paused) { audio.play().then(() => { isPlaying = true; playerButton.classList.add('playing'); }).catch(error => {}); } }
        function pauseMusic() { audio.pause(); isPlaying = false; playerButton.classList.remove('playing'); }
        playMusic();
        document.body.addEventListener('click', playMusic, { once: true });
        document.body.addEventListener('keydown', playMusic, { once: true });
        playerButton.addEventListener('click', (event) => { event.stopPropagation(); isPlaying ? pauseMusic() : playMusic(); });
    }
    window.onload = function() { backgroundSlider(); initMusicPlayer(); };
    function voteForCharacter(vndbId, buttonElement) {
        const nickname = localStorage.getItem('voter_nickname');
        if (!nickname) { alert('错误：无法获取到您的昵称信息，请返回首页确认是否已设置昵称。'); return; }
        buttonElement.disabled = true;
        buttonElement.textContent = '处理中...';
        const formData = new FormData();
        formData.append('vndb_id', vndbId);
        formData.append('nickname', nickname);
        fetch('vote_handler.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('投票成功！感谢您的支持！');
                buttonElement.textContent = '投票成功';
                buttonElement.style.backgroundColor = '#28a745';
                // 投票成功后，可以考虑在这里局部刷新票数，但为了简单，暂时不加
            } else {
                alert('投票失败：' + data.message);
                buttonElement.textContent = '投票失败';
                setTimeout(() => { buttonElement.disabled = false; buttonElement.textContent = '为她投票'; buttonElement.style.backgroundColor = '#42b72a'; }, 2000);
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
