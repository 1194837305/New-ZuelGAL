<?php
// =================================================================
// === 投票网站主页 (V2.5 - 校徽最终版) ===
// =================================================================

// --- 1. 彩蛋显示逻辑判断 (保持不变) ---
$db_host = 'localhost';
$db_name = 'zqz_voting';
$db_user = 'zqz_voting';
$db_pass = '789789';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
// ▼▼▼ 修改点 1：用一个更强大的变量来代替 $showEasterEgg ▼▼▼
$easterEggType = 0; // 0:隐藏, 1:显示普通彩蛋, 2:显示高级彩蛋

if ($conn->connect_error) {
    // ...
} else {
    // ...
    $sql = "SELECT id, vndb_id, original_name, game_title, image_url, votes FROM characters ORDER BY votes DESC LIMIT 5";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        // ▼▼▼ 修改点 2：用更高效的方式检查，并实现优先级判断 ▼▼▼
        $top5_rows = $result->fetch_all(MYSQLI_ASSOC);
        $top5_characters = $top5_rows; // 赋值给页面显示用
        
        $top5_ids = array_column($top5_rows, 'vndb_id');

        // 优先级判断
        if (in_array('c12973', $top5_ids)) {
            $easterEggType = 2; // 最高优先级
        } elseif (in_array('c10973', $top5_ids)) {
            $easterEggType = 1; // 第二优先级
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>猪栏菜Galgame交牛群角色人气总选举</title>
    <style>
        /* ========== 新增：引入“得意黑”网络字体 ========== */
        /* ====================================================== */
        @import url('https://cdn.jsdelivr.net/npm/lxgw-wenkai-screen-webfont@1.6.0/style.css' );
        /* --- 所有原有样式 (V2.2 + V2.4) 保持不变 --- */
        .background-slider { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; overflow: hidden; }
        .bg-slide { position: absolute; width: 100%; height: 100%; background-size: cover; background-position: center center; opacity: 0; transition: opacity 1.5s ease-in-out; transform: scale(1.1); filter: blur(2px); }
        .bg-slide:nth-child(1) { background-image: url('bg1.jpg'); animation: kenBurns 20s linear infinite; }
        .bg-slide:nth-child(2) { background-image: url('bg2.jpg'); animation: kenBurns-alt 20s linear infinite; }
        .bg-slide:nth-child(3) { background-image: url('bg3.jpg'); animation: kenBurns 20s linear infinite reverse; }
        .bg-slide:nth-child(4) { background-image: url('bg4.jpg'); animation: kenBurns-alt 20s linear infinite reverse; }
        .bg-slide:nth-child(5) { background-image: url('bg5.jpg'); animation: kenBurns 20s linear infinite; }
        .bg-slide.active { opacity: 1; }
        @keyframes kenBurns { 0% { transform: scale(1.1) translate(0, 0); } 50% { transform: scale(1.2) translate(-5%, 5%); } 100% { transform: scale(1.1) translate(0, 0); } }
        @keyframes kenBurns-alt { 0% { transform: scale(1.1) translate(0, 0); } 50% { transform: scale(1.2) translate(5%, -5%); } 100% { transform: scale(1.1) translate(0, 0); } }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', sans-serif; margin: 0; padding: 40px 20px; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; box-sizing: border-box; }
        .container { width: 100%; max-width: 800px; border-radius: 16px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2); padding: 30px; box-sizing: border-box; position: relative; z-index: 1; background-color: rgba(255, 255, 255, 0.75); backdrop-filter: blur(12px) saturate(110%); -webkit-backdrop-filter: blur(12px) saturate(110%); border: 1px solid rgba(255, 255, 255, 0.2); }
        
        /* ======================================= */
        /* ========== 新增：校徽样式 ========== */
        /* ======================================= */
        .emblem-container {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            margin-top: -80px; /* 调整此值以控制悬浮高度 */
            margin-bottom: 15px; /* 增加与下方标题的间距 */
            position: relative;
            z-index: 2;
        }
        .emblem-container img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: #fff;
            border: 4px solid white;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        }
        
        .header { text-align: center; /* margin-bottom: 30px; (由校徽的margin-bottom控制) */ }
        h1 { font-family: 'LXGW WenKai Screen', sans-serif;font-size: 28px; color: #2c3e50; margin-bottom: 10px; }
        p { color: #555; font-size: 16px; }
        .search-box { display: flex; margin-bottom: 30px; }
        #search-input { flex-grow: 1; padding: 12px 15px; font-size: 16px; border: 1px solid #ddd; border-radius: 8px 0 0 8px; outline: none; transition: border-color 0.3s; background-color: rgba(255,255,255,0.8); }
        #search-input:focus { border-color: #3498db; }
        #search-button { padding: 12px 25px; font-size: 16px; background-color: #3498db; color: white; border: none; border-radius: 0 8px 8px 0; cursor: pointer; transition: background-color 0.3s; }
        #search-button:hover { background-color: #2980b9; }
        .leaderboard { margin-top: 40px; }
        .leaderboard h2 { text-align: center; font-size: 24px; margin-bottom: 20px; color: #34495e; }
        .leaderboard-item { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(0, 0, 0, 0.05); }
        .leaderboard-item:last-child { border-bottom: none; }
        .rank { font-size: 24px; font-weight: bold; color: #aaa; width: 50px; text-align: center; }
        .rank.top-1 { color: #ffd700; } .rank.top-2 { color: #c0c0c0; } .rank.top-3 { color: #cd7f32; }
        .char-image { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; margin-right: 15px; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .char-info { flex-grow: 1; padding-left: 15px; }
        .char-info .name { font-weight: bold; font-size: 18px; color: #333; }
        .char-info .game { font-size: 14px; color: #666; }
        .votes { font-size: 20px; font-weight: bold; color: #e74c3c; }
        .empty-state { text-align: center; padding: 40px; color: #95a5a6; background-color: rgba(250, 250, 250, 0.8); border-radius: 8px; }
        .nickname-container { margin: 20px 0; padding: 15px; background-color: rgba(234, 245, 255, 0.8); border: 1px solid #bde0ff; border-radius: 8px; text-align: center; font-size: 16px; }
        .nickname-container input { padding: 8px; border-radius: 4px; border: 1px solid #ccc; width: 200px; background-color: rgba(255,255,255,0.8); }
        .nickname-container button { padding: 8px 15px; margin-left: 8px; border: none; border-radius: 4px; background-color: #007bff; color: white; cursor: pointer; }
        .nickname-container strong { color: #0056b3; }
        #countdown-timer { background-color: #e74c3c; color: white; padding: 15px 20px; border-radius: 8px; text-align: center; margin: 25px 0; font-size: 1.5em; font-weight: bold; letter-spacing: 2px; box-shadow: 0 4px 10px rgba(231, 76, 60, 0.4); }
        #countdown-timer span { margin: 0 8px; }
        #music-player { position: fixed; bottom: 25px; right: 25px; width: 50px; height: 50px; background-color: rgba(0, 0, 0, 0.5); border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); transition: transform 0.3s ease, background-color 0.3s ease; z-index: 999; }
        #music-player:hover { transform: scale(1.1); background-color: rgba(0, 0, 0, 0.7); }
        #music-player::before { content: '🎵'; font-size: 24px; color: white; }
        #music-player.playing::before { animation: spin 4s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        #easter-egg-player { position: fixed; bottom: 25px; left: 25px; width: 60px; height: 60px; cursor: pointer; transition: transform 0.3s ease, opacity 0.5s ease; z-index: 998; }
        #easter-egg-player img { width: 100%; height: 100%; border-radius: 50%; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
        #easter-egg-player:hover { transform: scale(1.15); }
        #easter-egg-player.hidden { opacity: 0; pointer-events: none; }
         .help-button {
        position: fixed;
        top: 25px;
        left: 25px;
        width: 45px;
        height: 45px;
        background-color: rgba(0, 0, 0, 0.4);
        color: white;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 24px;
        font-weight: bold;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        transition: transform 0.3s ease, background-color 0.3s ease;
        z-index: 1000;
    }

    .help-button:hover {
        transform: scale(1.1);
        background-color: rgba(0, 0, 0, 0.6);
    }
    /* ====================================================== */
/* ========== 新增：移动端响应式布局优化 ========== */
/* ====================================================== */

/* 当屏幕宽度小于或等于 768px 时 (覆盖大部分手机和平板竖屏) */
@media (max-width: 768px) {
    
    /* 1. 缩小主容器的边距，让内容区域更宽 */
    body {
        padding: 20px 10px;
    }
    .container {
        padding: 20px;
    }

    /* 2. 调整排行榜项目的布局和字体大小 */
    .leaderboard-item {
        padding: 8px 0; /* 减小上下间距 */
    }
    
    .char-image {
        width: 50px;  /* 缩小角色头像 */
        height: 50px;
        margin-right: 10px; /* 减小头像和文字的间距 */
    }

    .char-info .name {
        font-size: 16px; /* 缩小角色名字体 */
    }

    .char-info .game {
        font-size: 12px; /* 缩小作品名字体 */
    }

    .votes {
        font-size: 16px; /* 缩小票数字体 */
        min-width: 50px; /* 给票数一个最小宽度，防止被挤压 */
        text-align: right;
    }

    /* 3. 核心：限制作品标题长度并显示省略号 */
    .char-info .game {
        white-space: nowrap;      /* 强制文本不换行 */
        overflow: hidden;         /* 隐藏超出的部分 */
        text-overflow: ellipsis;  /* 将超出的部分显示为省略号 (...) */
        max-width: 150px;         /* 设定一个最大宽度，可以根据实际效果微调 */
    }
}
    </style>
</head>
<body>
    <!-- ========== 新增：帮助按钮HTML ========== -->
<!-- ======================================= -->
    <a href="help.html" class="help-button" title="帮助文档">?</a>
    <!-- 背景幻灯片HTML (保持不变) -->
    <div class="background-slider">
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
    </div>

    <!-- 页面主体HTML (修改：添加校徽) -->
    <div class="container">
        <div class="header">
            <!-- ========== 新增：校徽HTML ========== -->
            <div class="emblem-container">
                <!-- ▼▼▼ 请在这里替换您的校徽图片路径 ▼▼▼ -->
                <img src="toutu.png" alt="Site Emblem">
                <!-- ▲▲▲ 请在这里替换您的校徽图片路径 ▲▲▲ -->
            </div>
            
            <h1>猪栏菜Galgame交牛群角色人气总选举</h1>
            <div id="countdown-timer">正在计算时间...</div>
            <div class="nickname-container">
                <label for="nickname">泥事群友吗🧐填CN喵:</label>
                <input type="text" id="nickname" name="nickname" placeholder="请输入您在群里的昵称">
                <button onclick="saveNickname()">确认</button>
            </div>
            <p>请输入角色名进行搜索，并为她投票！</p>
        </div>
        <div class="search-box">
            <input type="text" id="search-input" placeholder="例如: murasame">
            <button id="search-button" onclick="searchCharacter()">搜索</button>
        </div>
        <div class="leaderboard">
            <h2>🏆 人气排行榜 TOP 5 🏆</h2>
            <?php $db_error = isset($db_error) ? $db_error : ''; ?>
            <?php if ($db_error): ?>
                <div class="empty-state"><?php echo htmlspecialchars($db_error); ?></div>
            <?php elseif (empty($top5_characters)): ?>
                <div class="empty-state">排行榜暂无数据，快去为你喜欢的角色投票吧！</div>
            <?php else: ?>
                <?php foreach ($top5_characters as $index => $char): ?>
                    <div class="leaderboard-item">
                        <div class="rank top-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></div>
                        <img class="char-image" src="<?php echo htmlspecialchars($char['image_url'] ?: 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($char['original_name']); ?>">
                        <div class="char-info">
                            <div class="name"><?php echo htmlspecialchars($char['original_name']); ?></div>
                            <div class="game"><?php echo htmlspecialchars($char['game_title']); ?></div>
                        </div>
                        <div class="votes"><?php echo htmlspecialchars($char['votes']); ?> 票</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 播放器HTML (保持不变) -->
    <audio id="bg-music" loop>
        <source src="kamiu.mp3" type="audio/mpeg">
    </audio>
    <div id="music-player" title="点击播放/暂停背景音乐"></div>
    <audio id="easter-egg-music">
        <source src="nuoshen.mp3" type="audio/mpeg">
    </audio>
    <div id="easter-egg-player" class="<?php echo ($easterEggType > 0) ? '' : 'hidden'; ?>" title="这是什么？点一下试试？">
        <img src="<?php
            if ($easterEggType == 2) {
                echo 'new_easter_egg.jpg'; // c12973 的新图片
            } else {
                echo 'nuoshen.jpg'; // c10973 的旧图片
            }
        ?>" alt="Easter Egg">
    </div>

    <!-- 完整的JavaScript (保持不变) -->
    <script>
        function backgroundSlider() {
            const slides = document.querySelectorAll('.bg-slide');
            let currentSlide = 0;
            const slideOrder = Array.from({length: slides.length}, (_, i) => i).sort(() => Math.random() - 0.5);
            function showSlide(index) {
                slides.forEach((slide) => slide.classList.remove('active'));
                slides[slideOrder[index]].classList.add('active');
            }
            showSlide(currentSlide);
            setInterval(() => {
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
            }, 7000);
        }
        function startCountdown() {
            const countdownElement = document.getElementById('countdown-timer');
            const deadline = new Date('2025-09-26T00:00:00').getTime();
            const interval = setInterval(() => {
                const now = new Date().getTime();
                const distance = deadline - now;
                if (distance < 0) {
                    clearInterval(interval);
                    countdownElement.innerHTML = "投票已截止";
                    return;
                }
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                countdownElement.innerHTML = `投票截止倒计时: <span>${days}</span>天 <span>${hours}</span>时 <span>${minutes}</span>分 <span>${seconds}</span>秒`;
            }, 1000);
        }
        function initMusicPlayers() {
            const mainPlayerBtn = document.getElementById('music-player');
            const mainAudio = document.getElementById('bg-music');
            const eggPlayerBtn = document.getElementById('easter-egg-player');
            const eggAudio = document.getElementById('easter-egg-music');
            let isMainPlaying = false;
            let isEggPlaying = false;
            function playMainMusic() {
                if (isEggPlaying) { eggAudio.pause(); isEggPlaying = false; }
                mainAudio.play();
                mainPlayerBtn.classList.add('playing');
                isMainPlaying = true;
            }
            function pauseMainMusic() {
                mainAudio.pause();
                mainPlayerBtn.classList.remove('playing');
                isMainPlaying = false;
            }
            function firstPlay() {
                if (!isMainPlaying && !isEggPlaying) {
                    playMainMusic();
                    document.body.removeEventListener('click', firstPlay);
                    document.body.removeEventListener('keydown', firstPlay);
                }
            }
            document.body.addEventListener('click', firstPlay);
            document.body.addEventListener('keydown', firstPlay);
            mainPlayerBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                isMainPlaying ? pauseMainMusic() : playMainMusic();
            });
            eggPlayerBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                if (isEggPlaying) {
                    eggAudio.pause();
                    isEggPlaying = false;
                } else {
                    if (isMainPlaying) pauseMainMusic();
                    eggAudio.play();
                    isEggPlaying = true;
                }
            });
        }
        function saveNickname() {
            const nicknameInput = document.getElementById('nickname');
            const nickname = nicknameInput.value.trim();
            if (nickname) {
                localStorage.setItem('voter_nickname', nickname);
                alert('昵称已保存！');
                location.reload();
            } else {
                alert('请输入一个有效的昵称！');
            }
        }
        function clearNickname() {
            localStorage.removeItem('voter_nickname');
            location.reload();
        }
        function searchCharacter() {
            const nickname = localStorage.getItem('voter_nickname');
            if (!nickname) {
                alert('在搜索前，请先设置您的群内昵称！');
                return;
            }
            const keyword = document.getElementById('search-input').value;
            if (keyword) {
                window.location.href = `search.php?keyword=${encodeURIComponent(keyword)}&nickname=${encodeURIComponent(nickname)}`;
            }
        }
        window.onload = function() {
            startCountdown();
            backgroundSlider();
            initMusicPlayers();
            const savedNickname = localStorage.getItem('voter_nickname');
            if (savedNickname) {
                document.getElementById('nickname').value = savedNickname;
                const container = document.querySelector('.nickname-container');
                if(container) {
                    container.innerHTML = `欢迎回来，<strong>${savedNickname}</strong>！ <button onclick="clearNickname()">更换昵称</button>`;
                }
            }
            document.getElementById('search-input').addEventListener('keyup', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    document.getElementById('search-button').click();
                }
            });
        };
    </script>
    

</body>
</html>
