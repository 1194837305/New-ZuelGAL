<?php
// === battle_line.php 决战观测中心 (节点精准击发终极版) ===

ini_set('display_errors', 0); 
error_reporting(E_ALL);
date_default_timezone_set('PRC'); 

$config_path = '../../config.php'; 
if (!file_exists($config_path)) die("配置文件丢失");
require_once $config_path;

try {
    $conn = db_connect();
    
    // --- 1. 时间封印 ---
    $end_time = '2026-04-14 00:00:00';
    $current_time = date('Y-m-d H:i:s');
    $anchor_time = ($current_time > $end_time) ? $end_time : $current_time;

    // --- 2. 获取 24 强名单 ---
    $top24_ids = []; $nom_map = [];
    $works_res = $conn->query("SELECT ymgal_id, nomination_count FROM event_caiqi_works");
    if($works_res) { while ($row = $works_res->fetch_assoc()) { $nom_map[$row['ymgal_id']] = (int)$row['nomination_count']; } }
    
    $prop_res = $conn->query("SELECT games_data FROM event_caiqi_proposals ORDER BY votes DESC, created_at ASC");
    $rollover = 0; $r_idx = 0;
    if($prop_res) {
        while ($row = $prop_res->fetch_assoc()) {
            $base = ($r_idx == 0) ? 12 : (($r_idx == 1) ? 8 : 4);
            $quota = $base + $rollover;
            $g_arr = json_decode($row['games_data'], true);
            $candidates = [];
            if (is_array($g_arr)) {
                foreach ($g_arr as $g) { if (!in_array((int)$g['id'], $top24_ids)) $candidates[] = (int)$g['id']; }
            }
            usort($candidates, function($a, $b) use ($nom_map) { return ($nom_map[$b] ?? 0) <=> ($nom_map[$a] ?? 0); });
            $selected = array_slice($candidates, 0, $quota);
            $top24_ids = array_merge($top24_ids, $selected);
            $rollover = $quota - count($selected); $r_idx++;
            if (count($top24_ids) >= 24) { $top24_ids = array_slice($top24_ids, 0, 24); break; }
        }
    }

    if (empty($top24_ids)) die("暂无合法的 24 强数据！");

    // --- 3. 构造数据矩阵与图片库 ---
    $id_list = implode(',', $top24_ids);
    $works_info = $conn->query("SELECT ymgal_id, title_cn, cover_url FROM event_caiqi_works WHERE ymgal_id IN ($id_list)");
    
    $series = []; $scores = []; $img_db = [];
    while($w = $works_info->fetch_assoc()) {
        $pid = $w['ymgal_id']; 
        $title = trim($w['title_cn']);
        $scores[$pid] = 0;
        
        $img_db[$title] = trim($w['cover_url']); 
        
        $series[$pid] = [
            'name' => $title,
            'type' => 'line',
            'smooth' => 0.35,
            'symbol' => 'circle',
            // 💡 破局核心 1：将节点显现出来！让用户能看见实体的触发点
            'showSymbol' => true, 
            'symbolSize' => 6, // 节点大小，像一个个小灯泡
            // 💡 破局核心 2：开启 ECharts 5 专用的线条触发指令（作为双保险）
            'triggerLineEvent' => true,
            'lineStyle' => ['width' => 2],
            'itemStyle' => ['borderWidth' => 1, 'borderColor' => '#000'],
            'emphasis' => [
                'focus' => 'series', 
                'lineStyle' => ['width' => 4, 'shadowBlur' => 10],
                'itemStyle' => ['symbolSize' => 10, 'borderWidth' => 2, 'borderColor' => '#fff']
            ],
            'data' => []
        ];
    }

    $threshold_line = [
        'name' => '🔥 TOP 12 生死线', 'type' => 'line', 'step' => 'end', 'symbol' => 'none', 'z' => 10,
        'lineStyle' => ['color' => '#ff4d4f', 'width' => 2, 'type' => 'dashed'],
        'data' => []
    ];

    // --- 4. 统计推演 ---
    $votes = $conn->query("SELECT ymgal_id, vote_type, created_at FROM event_caiqi_tier_votes WHERE ymgal_id IN ($id_list) AND created_at <= '$end_time' ORDER BY created_at ASC");
    $first_t = null; $records = [];
    if($votes) { while($v = $votes->fetch_assoc()) { if(!$first_t) $first_t = $v['created_at']; $records[] = $v; } }

    if ($first_t) {
        foreach ($scores as $id => $v) $series[$id]['data'][] = [$first_t, 0];
        $threshold_line['data'][] = [$first_t, 0];
    }

    foreach ($records as $v) {
        $wid = $v['ymgal_id'];
        if (isset($scores[$wid])) {
            $scores[$wid] += ($v['vote_type'] === 'love' ? 2 : 1);
            $series[$wid]['data'][] = [$v['created_at'], $scores[$wid]];
            $std = array_values($scores); rsort($std);
            $threshold_line['data'][] = [$v['created_at'], $std[11] ?? 0];
        }
    }

    foreach($scores as $id => $total) $series[$id]['data'][] = [$anchor_time, $total];
    $final_std = array_values($scores); rsort($final_std);
    $threshold_line['data'][] = [$anchor_time, $final_std[11] ?? 0];

    $final_series = array_values($series);
    array_unshift($final_series, $threshold_line);

    // 完全兼容探针的 JSON 转义方式，确保 JS 引擎稳定
    $json_opts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $img_db_json = json_encode($img_db, $json_opts) ?: '{}';
    $chart_data_json = json_encode($final_series, $json_opts) ?: '[]';

} catch (Exception $e) { die("Engine Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>战线观测图 - ZuelGal</title>
    <style>
        body, html { margin: 0; padding: 0; width: 100%; height: 100%; background: #050507; color: #fff; font-family: sans-serif; overflow: hidden; }
        .header { padding: 15px 30px; border-bottom: 1px solid #222; background: #0a0a0c; display: flex; justify-content: space-between; align-items: center; }
        .title { color: #c9171e; margin: 0; font-size: 22px; font-weight: 900; letter-spacing: 2px; }
        #chart-container { width: 100%; height: calc(100vh - 70px); }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
</head>
<body>
    <div class="header">
        <h1 class="title">十二菜器 · 决战观测中心</h1>
        <div>
            <?php if ($current_time >= $end_time): ?>
                <div style="font-size:13px; font-weight:bold; padding:4px 12px; border-radius:4px; border:1px solid #ff4d4f; color:#ff4d4f; background: rgba(255,77,79,0.1);">
                    🔒 赛事已截止，定格存档
                </div>
            <?php else: ?>
                <div style="font-size:13px; font-weight:bold; padding:4px 12px; border-radius:4px; border:1px solid #00fa9a; color:#00fa9a; background: rgba(0,250,154,0.1);">
                    🟢 实时直播更新中
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="chart-container"></div>

    <script>
        const imgDb = <?php echo $img_db_json; ?>;
        const chartData = <?php echo $chart_data_json; ?>;
        
        const myChart = echarts.init(document.getElementById('chart-container'), 'dark');

        const option = {
            backgroundColor: 'transparent',
            color: ['#FFD700', '#00BFFF', '#32CD32', '#FF8C00', '#9370DB', '#00FA9A', '#FF69B4', '#1E90FF', '#ADFF2F', '#DA70D6', '#00CED1', '#F0E68C', '#8A2BE2', '#7FFF00', '#D2691E', '#FF00FF', '#40E0D0', '#FA8072', '#6495ED', '#9ACD32', '#F4A460', '#DDA0DD', '#87CEEB', '#FFE4B5'],
            tooltip: {
                trigger: 'item', // 依然使用精准触发
                confine: true,
                backgroundColor: 'rgba(15,15,18,0.95)',
                borderColor: '#444',
                borderWidth: 1,
                padding: 12,
                formatter: function(p) {
                    try {
                        if (!p || !p.seriesName) return '';
                        
                        const name = p.seriesName;
                        const color = p.color || '#fff';
                        const time = (p.value && p.value.length > 0) ? p.value[0] : '';
                        const val = (p.value && p.value.length > 1) ? p.value[1] : 0;
                        const img = imgDb[name] || '';

                        let html = '<div style="min-width: 160px; text-align: center;">';
                        
                        if (name.includes('生死线')) {
                            html += '<div style="font-size:32px; margin-bottom:5px; text-shadow:0 0 15px #ff4d4f;">💀</div>';
                            html += '<div style="color:#ff4d4f; font-weight:bold; font-size:16px; margin-bottom:5px;">晋级门槛</div>';
                        } else {
                            if (img) {
                                html += '<div style="margin-bottom:10px;">';
                                // 💡 直接使用原生的 img 标签渲染，跟探针一模一样！
                                html += '<img src="' + img + '" style="width: 150px; height: 85px; object-fit: cover; border-radius: 4px; border: 2px solid ' + color + '; box-shadow: 0 0 10px ' + color + '88;">';
                                html += '</div>';
                            }
                            html += '<div style="font-weight:bold; font-size:15px; color:' + color + '; margin-bottom:6px;">' + name + '</div>';
                        }
                        
                        html += '<div style="font-size:12px; color:#888; margin-bottom:6px;">📅 ' + time + '</div>';
                        html += '<div style="font-size:14px; color:#fff; background:rgba(255,255,255,0.08); padding:5px 8px; border-radius:4px; display:inline-block;">累计票数: <b style="font-size:18px; color:' + color + '; margin-left:4px;">' + val + '</b></div>';
                        html += '</div>';
                        
                        return html;
                    } catch (e) {
                        return '<div style="color:#fff;">数据装载异常</div>';
                    }
                }
            },
            legend: { 
                type: 'scroll', orient: 'vertical', right: 15, top: 20, bottom: 60, 
                textStyle: { color: '#ccc', fontSize: 13 },
                inactiveColor: '#333'
            },
            grid: { left: '3%', right: '20%', bottom: '12%', top: '6%', containLabel: true },
            dataZoom: [
                { type: 'slider', bottom: 10, start: 0, end: 100, borderColor: '#333', fillerColor: 'rgba(201,23,30,0.15)', textStyle: { color: '#aaa'} }, 
                { type: 'inside' }
            ],
            xAxis: { 
                type: 'time', boundaryGap: false,
                splitLine: { show: true, lineStyle: { color: '#1a1a1c', type: 'dashed' } },
                axisLine: { lineStyle: { color: '#444' } }
            },
            yAxis: { 
                type: 'value', 
                splitLine: { lineStyle: { color: '#151518' } },
                axisLine: { show: true, lineStyle: { color: '#444' } }
            },
            series: chartData
        };

        myChart.setOption(option);
        window.addEventListener('resize', () => myChart.resize());
        
        <?php if ($current_time < $end_time): ?>
            setTimeout(() => location.reload(), 60000); 
        <?php endif; ?>
    </script>
</body>
</html>