<?php
// trends.php (Layout Fixed & BGM Player Added)

// 数据库连接配置 (这部分PHP代码是正确的，无需改动)
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'zqz_voting');
define('DB_PASSWORD', '789789');
define('DB_NAME', 'zqz_voting');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$character_options_html = '';
if ($conn->connect_error) {
    $character_options_html = "<option disabled>数据库连接失败</option>";
} else {
    $conn->set_charset("utf8mb4");
    $sql = "SELECT vndb_id, name, game_title FROM characters WHERE votes > 0 ORDER BY votes DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $character_options_html .= "<option value='" . htmlspecialchars($row['vndb_id']) . "'>" . htmlspecialchars($row['name']) . " - " . htmlspecialchars($row['game_title']) . "</option>";
        }
    } else {
        $character_options_html = "<option disabled>暂无有票数的角色</option>";
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>角色人气战线</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        /* --- 原有样式保持不变 --- */
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', sans-serif; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; background-image: url('huizhi.jpg'  ); background-size: cover; background-position: center; background-attachment: fixed; }
        body::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.3); z-index: -1; }
        .container { background-color: rgba(255, 255, 255, 0.9); border-radius: 12px; box-shadow: 0 6px 24px rgba(0,0,0,0.15); max-width: 1200px; width: 90%; padding: 30px 40px; box-sizing: border-box; backdrop-filter: blur(5px); }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
        .select2-container { width: 100% !important; margin-bottom: 20px; }
        .back-link { display: block; width: fit-content; margin: 40px auto 10px; padding: 10px 25px; background-color: #3498db; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .chart-container { position: relative; height: 60vh; width: 100%; }

        /* ====================================================== */
        /* ========== 修改点 1：从 index.php 移植播放器样式 ========== */
        /* ====================================================== */
        #music-player {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 50px;
            height: 50px;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease, background-color 0.3s ease;
            z-index: 999;
        }
        #music-player:hover {
            transform: scale(1.1);
            background-color: rgba(0, 0, 0, 0.7);
        }
        #music-player::before {
            content: '🎵';
            font-size: 24px;
            color: white;
        }
        #music-player.playing::before {
            animation: spin 4s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        /* ====================================================== */

    </style>
</head>
<body>
    <div class="container">
        <h1>角色人气战线</h1>
        <select id="character-selector" multiple="multiple"><?php echo $character_options_html; ?></select>
        <div class="chart-container">
            <canvas id="trends-chart"></canvas>
        </div>
        <a href="/" class="back-link">返回首页</a>
    </div>

    <!-- ====================================================== -->
    <!-- ========== 修改点 2：从 index.php 移植播放器HTML ========== -->
    <!-- ====================================================== -->
    <audio id="bg-music" loop>
        <source src="kamiu.mp3" type="audio/mpeg">
    </audio>
    <div id="music-player" title="点击播放/暂停背景音乐"></div>
    <!-- ====================================================== -->

    <script>
        // ======================================================
        // ========== 修改点 3：从 index.php 移植播放器JS逻辑 ==========
        // ======================================================
        function initMusicPlayer() {
            const playerBtn = document.getElementById('music-player');
            const audio = document.getElementById('bg-music');
            let isPlaying = false;

            // 自动播放的尝试
            function firstPlay() {
                if (!isPlaying) {
                    audio.play().then(() => {
                        playerBtn.classList.add('playing');
                        isPlaying = true;
                    }).catch(error => {
                        // 自动播放失败是正常现象，等待用户交互
                        console.log("自动播放被浏览器阻止，等待用户交互。");
                    });
                    // 无论成功与否，都移除监听器，只尝试一次
                    document.body.removeEventListener('click', firstPlay);
                    document.body.removeEventListener('keydown', firstPlay);
                }
            }

            // 监听用户与页面的首次交互
            document.body.addEventListener('click', firstPlay);
            document.body.addEventListener('keydown', firstPlay);

            // 播放器按钮的点击事件
            playerBtn.addEventListener('click', (event) => {
                event.stopPropagation(); // 防止触发body的点击事件
                if (isPlaying) {
                    audio.pause();
                    playerBtn.classList.remove('playing');
                    isPlaying = false;
                } else {
                    audio.play();
                    playerBtn.classList.add('playing');
                    isPlaying = true;
                }
            });
        }
        // ======================================================

        $(document).ready(function() {
            // --- 原有图表逻辑保持不变 ---
            const selector = $('#character-selector').select2({
                placeholder: '请选择要比较的角色（最多10个）',
                maximumSelectionLength: 10,
                language: "zh-CN"
            });
            const ctx = document.getElementById('trends-chart').getContext('2d');
            const trendsChart = new Chart(ctx, {
                type: 'line',
                data: { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' }, title: { display: true, text: '每日累计票数趋势' } },
                }
            });
            selector.on('change', function() {
                const selectedIds = $(this).val();
                updateChart(selectedIds);
            });
            async function updateChart(vndbIds) {
                if (!vndbIds || vndbIds.length === 0) {
                    trendsChart.data.labels = [];
                    trendsChart.data.datasets = [];
                    trendsChart.update();
                    return;
                }
                try {
                    const response = await fetch('get_trends_data.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(vndbIds)
                    });
                    if (!response.ok) { throw new Error('网络请求失败: ' + response.statusText); }
                    const data = await response.json();
                    if (data.error) { throw new Error('服务器错误: ' + data.error); }
                    trendsChart.data.labels = data.labels;
                    trendsChart.data.datasets = data.datasets;
                    trendsChart.update();
                } catch (error) {
                    console.error('更新图表失败:', error);
                    alert('无法加载图表数据，请检查控制台获取更多信息。');
                }
            }

            // --- 新增：在页面加载完成后初始化播放器 ---
            initMusicPlayer();
        });
    </script>
</body>
</html>
