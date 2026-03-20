<?php
// ==================================================
// === 投票网站主页 (index.php) V1.6 最终整合版 ===
// ==================================================

// --- 数据库连接信息 ---
$db_host = '127.0.0.1';
$db_name = 'zqz_voting';
$db_user = 'zqz_voting';
$db_pass = '789789'; // !! 确保密码正确 !!

// --- 连接数据库 ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 检查连接
if ($conn->connect_error) {
    // 如果连接失败，不直接崩溃，而是优雅地处理
    $top5_characters = []; // 给一个空数组，防止后续代码报错
    $db_error = "数据库连接失败: " . $conn->connect_error;
} else {
    $conn->set_charset("utf8mb4");
    $db_error = null;

    // --- 获取排行榜TOP 5数据 ---
    $top5_characters = [];
    // 从我们的'characters'表中，按票数(votes)降序排列，只取前5条
    $sql = "SELECT id, original_name, game_title, image_url, votes FROM characters ORDER BY votes DESC LIMIT 5";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $top5_characters[] = $row;
        }
    }
    // 关闭数据库连接
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galgame角色人气总选举</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', sans-serif;
            background-color: #f4f7f9;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 800px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            box-sizing: border-box;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        p {
            color: #7f8c8d;
            font-size: 16px;
        }
        .search-box {
            display: flex;
            margin-bottom: 30px;
        }
        #search-input {
            flex-grow: 1;
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 8px 0 0 8px;
            outline: none;
            transition: border-color 0.3s;
        }
        #search-input:focus {
            border-color: #3498db;
        }
        #search-button {
            padding: 12px 25px;
            font-size: 16px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        #search-button:hover {
            background-color: #2980b9;
        }
        .leaderboard {
            margin-top: 40px;
        }
        .leaderboard h2 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
            color: #34495e;
        }
        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .leaderboard-item:last-child {
            border-bottom: none;
        }
        .rank {
            font-size: 24px;
            font-weight: bold;
            color: #aaa;
            width: 50px;
            text-align: center;
        }
        .rank.top-1 { color: #ffd700; } /* 金色 */
        .rank.top-2 { color: #c0c0c0; } /* 银色 */
        .rank.top-3 { color: #cd7f32; } /* 铜色 */
        .char-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .char-info {
            flex-grow: 1;
            padding-left: 15px;
        }
        .char-info .name { font-weight: bold; font-size: 18px; }
        .char-info .game { font-size: 14px; color: #777; }
        .votes {
            font-size: 20px;
            font-weight: bold;
            color: #e74c3c;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
            background-color: #fafafa;
            border-radius: 8px;
        }
        /* 新增：昵称输入区域的样式 */
        .nickname-container {
            margin: 20px 0;
            padding: 15px;
            background-color: #eaf5ff;
            border: 1px solid #bde0ff;
            border-radius: 8px;
            text-align: center;
            font-size: 16px;
        }
        .nickname-container label {
            margin-right: 10px;
            font-weight: 500;
        }
        .nickname-container input {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            width: 200px;
        }
        .nickname-container button {
            padding: 8px 15px;
            margin-left: 8px;
            border: none;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            cursor: pointer;
        }
        .nickname-container strong {
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Galgame角色人气总选举</h1>

            <!-- ========== 新增：昵称输入区域 ========== -->
            <div class="nickname-container">
                <label for="nickname">您的群内昵称:</label>
                <input type="text" id="nickname" name="nickname" placeholder="请输入您在群里的名字">
                <button onclick="saveNickname()">确认</button>
            </div>
            <!-- ======================================= -->

            <p>请输入角色名进行搜索，并为她投票！</p>
        </div>

        <div class="search-box">
            <input type="text" id="search-input" placeholder="例如: 千反田える">
            <button id="search-button" onclick="searchCharacter()">搜索</button>
        </div>

        <div class="leaderboard">
            <h2>🏆 人气排行榜 TOP 5 🏆</h2>
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

    <script>
        // 页面加载时，检查是否已保存昵称
        window.onload = function() {
            const savedNickname = localStorage.getItem('voter_nickname');
            if (savedNickname) {
                document.getElementById('nickname').value = savedNickname;
                const container = document.querySelector('.nickname-container');
                if(container) {
                    container.innerHTML = `欢迎回来，<strong>${savedNickname}</strong>！ <button onclick="clearNickname()">更换昵称</button>`;
                }
            }
            // 让搜索框在回车时也能触发搜索
            document.getElementById('search-input').addEventListener('keyup', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    document.getElementById('search-button').click();
                }
            });
        };

        // 保存昵称到浏览器的本地存储
        function saveNickname() {
            const nicknameInput = document.getElementById('nickname');
            const nickname = nicknameInput.value.trim();
            if (nickname) {
                localStorage.setItem('voter_nickname', nickname);
                alert('昵称已保存！');
                location.reload(); // 刷新页面以显示欢迎信息
            } else {
                alert('请输入一个有效的昵称！');
            }
        }

        // 清除已保存的昵称
        function clearNickname() {
            localStorage.removeItem('voter_nickname');
            location.reload();
        }

        // !! 核心升级：新的搜索函数 !!
        function searchCharacter() {
            const nickname = localStorage.getItem('voter_nickname');
            if (!nickname) {
                alert('在搜索前，请先设置您的群内昵称！');
                return; // 如果没有昵称，则终止搜索
            }
            const keyword = document.getElementById('search-input').value;
            if (keyword) {
                // 将昵称和关键词一起通过URL传递给 search.php
                window.location.href = `search.php?keyword=${encodeURIComponent(keyword)}&nickname=${encodeURIComponent(nickname)}`;
            }
        }
    </script>
</body>
</html>
