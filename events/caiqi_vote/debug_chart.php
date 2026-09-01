<?php
// === debug_chart.php 悬浮窗极限探针 ===
ini_set('display_errors', 1);
error_reporting(E_ALL);

$config_path = '../../config.php'; 
if (!file_exists($config_path)) die("配置文件丢失");
require_once $config_path;

$conn = db_connect();

// 随机抓取 1 个带有封面的作品进行活体测试
$test_sql = "SELECT title_cn, cover_url FROM event_caiqi_works WHERE cover_url IS NOT NULL AND cover_url != '' LIMIT 1";
$res = $conn->query($test_sql);

if (!$res || $res->num_rows === 0) {
    die("<h1>数据库测试失败：找不到任何带有 cover_url 的作品！请检查数据库是否存入了封面路径！</h1>");
}

$test_game = $res->fetch_assoc();
$test_title = htmlspecialchars($test_game['title_cn'], ENT_QUOTES);
$test_cover = htmlspecialchars($test_game['cover_url'], ENT_QUOTES);

// 准备测试数据给前端
$debug_data = json_encode([
    'title' => $test_title,
    'cover' => $test_cover
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>ECharts 悬浮窗探针</title>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <style>
        body { background: #111; color: #fff; font-family: monospace; padding: 20px; }
        .panel { border: 1px solid #444; padding: 15px; margin-bottom: 20px; background: #1a1a1a; }
        #debug-log { color: #ff4d4f; font-weight: bold; min-height: 50px; }
        h3 { color: #00fa9a; margin-top: 0; }
    </style>
</head>
<body>

    <div class="panel">
        <h3>1. 数据库原生图片路径测试 (如果这里是裂开的，说明存的路径有问题)</h3>
        <p>作品名：<?= $test_title ?></p>
        <p>URL：<a href="<?= $test_cover ?>" target="_blank" style="color:#00BFFF;"><?= $test_cover ?></a></p>
        <img src="<?= $test_cover ?>" alt="加载失败" style="width: 200px; border: 1px dashed #fff;">
    </div>

    <div class="panel">
        <h3>2. ECharts 极简悬浮测试 (请把鼠标放到底下的红点上！)</h3>
        <div id="chart-test" style="width: 100%; height: 300px; background: #000;"></div>
    </div>

    <div class="panel">
        <h3>3. 前端控制台监听 (如果有报错，这里会显示红字)</h3>
        <div id="debug-log">等待鼠标悬浮操作...</div>
    </div>

    <script>
        const testData = <?php echo $debug_data; ?>;
        const logger = document.getElementById('debug-log');
        const chartDom = document.getElementById('chart-test');
        const myChart = echarts.init(chartDom, 'dark');

        const option = {
            backgroundColor: 'transparent',
            tooltip: {
                trigger: 'item',
                formatter: function(params) {
                    try {
                        // 记录：触发成功
                        logger.innerHTML += `<br><span style="color:#00fa9a;">[成功] 触发 formatter，目标: ${params.name}</span>`;
                        
                        return `
                            <div style="border: 2px solid red; padding: 10px;">
                                <div style="color: yellow; margin-bottom: 5px;">Echarts 悬浮窗内</div>
                                <img src="${testData.cover}" style="width: 150px; height: auto;">
                                <div>${testData.title}</div>
                            </div>
                        `;
                    } catch (e) {
                        // 记录：触发崩溃
                        logger.innerHTML += `<br><span style="color:red;">[崩溃] Formatter 内部报错: ${e.message}</span>`;
                        return "渲染失败";
                    }
                }
            },
            xAxis: { type: 'category', data: ['测试点'] },
            yAxis: { type: 'value' },
            series: [{
                name: testData.title,
                type: 'line',
                symbol: 'circle',
                symbolSize: 20, // 弄一个超大的红点让你摸
                itemStyle: { color: 'red' },
                data: [100]
            }]
        };

        myChart.setOption(option);
    </script>
</body>
</html>