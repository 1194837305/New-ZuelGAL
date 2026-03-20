<?php
// === tierlist.php 完整视觉增强版 ===
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('PRC'); // 强制中国时区

$config_path = '../../config.php';
if (!file_exists($config_path)) {
    die("致命错误：系统核心配置文件丢失！");
}
require_once $config_path;

$games = [];
$is_shrunk = (time() > strtotime("2026-04-27 00:00:00")); // 判断是否已过缩圈时间

try {
    $conn = db_connect();
    
    // 💡 修复1：自动创建总作品表 (如果不存在)
    $conn->query("CREATE TABLE IF NOT EXISTS event_caiqi_works (
        ymgal_id INT PRIMARY KEY,
        title_cn VARCHAR(255) NOT NULL,
        cover_url TEXT,
        nomination_count INT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 💡 修复2：自动创建淘汰赛投票记录表 (如果不存在)
    $conn->query("CREATE TABLE IF NOT EXISTS event_caiqi_tier_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ymgal_id INT NOT NULL,
        voter_name VARCHAR(100) NOT NULL,
        ip_address VARCHAR(50) NOT NULL,
        vote_type VARCHAR(20) NOT NULL,
        vote_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // 安全自检：确保作品表里有淘汰赛专用的字段
    $check_tier_votes = $conn->query("SHOW COLUMNS FROM event_caiqi_works LIKE 'tier_votes'");
    if ($check_tier_votes && $check_tier_votes->num_rows == 0) {
        $conn->query("ALTER TABLE event_caiqi_works ADD COLUMN tier_votes INT DEFAULT 0");
    }
    $check_last_voted = $conn->query("SHOW COLUMNS FROM event_caiqi_works LIKE 'last_voted_at'");
    if ($check_last_voted && $check_last_voted->num_rows == 0) {
        $conn->query("ALTER TABLE event_caiqi_works ADD COLUMN last_voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

// 💡 核心重构：配额顺延制晋级逻辑 (12-8-4-4...)
    $top24_ids = [];

    // 1. 预先拉取所有作品的提名数，方便在配额内进行“优胜劣汰”
    $nom_map = [];
    $works_res = $conn->query("SELECT ymgal_id, nomination_count FROM event_caiqi_works");
    if ($works_res && $works_res->num_rows > 0) {
        while ($row = $works_res->fetch_assoc()) {
            $nom_map[$row['ymgal_id']] = (int)$row['nomination_count'];
        }
    }

    // 2. 按得票数从高到低，获取所有提案
    $prop_sql = "SELECT games_data FROM event_caiqi_proposals ORDER BY votes DESC, created_at ASC";
    $prop_res = $conn->query($prop_sql);

    $rollover_quota = 0; // 顺延名额结转池
    $rank_index = 0;     // 提案名次索引 (0是第一名，1是第二名...)

    if ($prop_res && $prop_res->num_rows > 0) {
        while ($row = $prop_res->fetch_assoc()) {
            // 确定当前提案的基础配额
            if ($rank_index == 0) { $base_quota = 12; }
            elseif ($rank_index == 1) { $base_quota = 8; }
            else { $base_quota = 4; }

            $current_quota = $base_quota + $rollover_quota; // 实际可用配额 = 基础 + 结转过来的

            $games_array = json_decode($row['games_data'], true);
            $unique_candidates = [];

            if (is_array($games_array)) {
                // 筛选出这个提案里，还没有被前面大佬挑走的作品
                foreach ($games_array as $g) {
                    $g_id = (int)$g['id'];
                    if (!in_array($g_id, $top24_ids)) {
                        $unique_candidates[] = $g_id;
                    }
                }
            }

            // 👑 核心：对这些候选作品，按照【总被提名数】从高到低进行内部排序！
            usort($unique_candidates, function($a, $b) use ($nom_map) {
                $count_a = $nom_map[$a] ?? 0;
                $count_b = $nom_map[$b] ?? 0;
                return $count_b <=> $count_a; // 降序：提名数多的排在前面
            });

            // 从排序好的名单中，精准截取当前配额数量的作品
            $selected_games = array_slice($unique_candidates, 0, $current_quota);

            // 将截取到的精锐作品编入最终的 24 强大名单
            $top24_ids = array_merge($top24_ids, $selected_games);

            // 计算该提案没有用完的空余配额，结转给下一名
            $added_count = count($selected_games);
            $rollover_quota = $current_quota - $added_count;

            $rank_index++;

            // 如果已经集齐 24 强，立刻停止开箱
            if (count($top24_ids) >= 24) {
                $top24_ids = array_slice($top24_ids, 0, 24); // 防止超载，强制锁死在 24 个
                break;
            }
        }
    }
    
    if (empty($top24_ids)) {
        die("当前没有任何合法的提案数据，无法生成淘汰赛榜单！");
    }
    
    // 3. 拿着这 24 个天选之子的 ID，去作品库里把它们的战力数据查出来
    $id_list = implode(',', $top24_ids);
    $sql = "SELECT * FROM event_caiqi_works WHERE ymgal_id IN ($id_list) ORDER BY tier_votes DESC, last_voted_at DESC, nomination_count DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $games[] = $row; // 这行最关键，把查到的数据塞进 $games 数组
        }
    }
    // 同步今日余弹 (💡 修复3：加上判断，防止查不到数据时 fetch_row()[0] 报错)
    $today = date('Y-m-d');
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $love_res = $conn->query("SELECT COUNT(*) FROM event_caiqi_tier_votes WHERE vote_type = 'love' AND vote_date = '$today' AND ip_address = '$ip'");
    $used_love = $love_res ? $love_res->fetch_row()[0] : 0;
    
    $normal_res = $conn->query("SELECT COUNT(*) FROM event_caiqi_tier_votes WHERE vote_type = 'normal' AND vote_date = '$today' AND ip_address = '$ip'");
    $used_normal = $normal_res ? $normal_res->fetch_row()[0] : 0;
    
    $init_love = max(0, 1 - $used_love);
    $init_normal = max(0, 5 - $used_normal);

    $conn->close();
} catch (Exception $e) {
    die("数据库通讯崩溃：" . $e->getMessage());
}

// 分配算法 (1-2-3-6-12)
$hang = []; $top = []; $elite = []; $npc = []; $trash = [];
$promoted_count = 0;
foreach ($games as $g) {
    if ($g['tier_votes'] > 0 && $promoted_count < 12) {
        if (count($hang) < 1) { $hang[] = $g; }
        elseif (count($top) < 2) { $top[] = $g; }
        elseif (count($elite) < 3) { $elite[] = $g; }
        elseif (count($npc) < 6) { $npc[] = $g; }
        $promoted_count++;
    } else {
        $trash[] = $g;
    }
}
$tiers = [ 'hang' => $hang, 'top' => $top, 'elite' => $elite, 'npc' => $npc, 'trash' => $trash ];

// 渲染函数：增加缩圈视觉逻辑
function renderGames($tier_array, $max_slots, $is_trash_tier = false, $is_shrunk_global = false) {
    $html = '';
    $count = count($tier_array);
    for ($i = 0; $i < $count; $i++) {
        $g = $tier_array[$i];
        
        // 核心修改：分别提取两个数据
        $nom_count = $g['nomination_count'];
        $tier_v = $g['tier_votes'];
        
        // 拼接双重数据显示：[提名数] | [当前战力]
        // 这里给提名数加了一点灰度，让它看起来像背景数据，而红色的战力更醒目
        $votes_html = '<span style="color:#888; font-size:10px;">'.$nom_count.'提 | </span>' . $tier_v . '战';
        
        $safe_title = htmlspecialchars($g['title_cn'], ENT_QUOTES);
        $onclick = ($is_shrunk_global && $is_trash_tier) 
                   ? "alert('🚨 缩圈警报：该作品已被锁死在深渊，无法再进行投票！')" 
                   : "openVoteModal(".$g['ymgal_id'].", '".$safe_title."')";

        $html .= '
        <div class="game-card" onclick="'.$onclick.'">
            <div class="game-cover">
                <img src="'.htmlspecialchars($g['cover_url']).'" alt="">
                <div class="game-votes">'.$votes_html.'</div>
            </div>
            <div class="game-title">'.$safe_title.'</div>
        </div>';
    }
    for ($i = $count; $i < $max_slots; $i++) {
        $html .= '<div class="game-card empty-card"><div class="game-cover" style="background:#050505; display:flex; align-items:center; justify-content:center; color:#333; font-size:24px;">?</div><div class="game-title" style="color:#444;">虚位以待</div></div>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>十二菜器 · 实时战况榜 - ZuelGal</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #c9171e; --bg-dark: #0a0a0c; }
        body { margin: 0; padding: 0; background-color: var(--bg-dark); color: #eee; font-family: 'Noto Serif SC', serif; }
        .battle-header { text-align: center; padding: 40px 20px; background: url('https://via.placeholder.com/1920x400/1a0505/000') center/cover; position: relative; border-bottom: 2px solid var(--primary); }
        .back-btn { position: absolute; top: 20px; left: 20px; color: #aaa; text-decoration: none; border: 1px dashed #444; padding: 5px 15px; border-radius: 4px; font-size: 14px; transition: 0.3s; }
        .back-btn:hover { border-color: var(--primary); color: #fff; }
        .title { font-size: 40px; color: #fff; margin: 0; text-transform: uppercase; letter-spacing: 5px; text-shadow: 0 0 15px var(--primary); }
        
        /* 倒计时样式 */
        .countdown { display: inline-block; margin-top: 15px; padding: 10px 20px; background: rgba(0,0,0,0.8); border: 1px solid var(--primary); color: var(--primary); font-family: monospace; font-size: 18px; font-weight: bold; border-radius: 4px; }

        .ammo-bar { position: fixed; bottom: 0; left: 0; width: 100%; background: rgba(20,20,25,0.95); border-top: 1px solid #333; display: flex; justify-content: center; gap: 30px; padding: 15px 0; z-index: 100; backdrop-filter: blur(10px); }
        .ammo-num { font-size: 24px; font-weight: 900; }
        .ammo-love { color: #ff1493; text-shadow: 0 0 10px rgba(255,20,147,0.5); }
        .ammo-normal { color: #00bfff; }

        .tier-board { max-width: 1200px; margin: 40px auto 100px; padding: 0 20px; display: flex; flex-direction: column; gap: 15px; }
        .tier-row { display: flex; background: #111; border: 1px solid #222; border-radius: 8px; overflow: hidden; min-height: 120px; }
        .tier-label { width: 100px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 900; color: #000; text-align: center; line-height: 1.2; flex-shrink: 0; }
        .label-hang { background: linear-gradient(135deg, #ff4b4b, #ff0000); }
        .label-top { background: linear-gradient(135deg, #ffa500, #ff8c00); }
        .label-elite { background: linear-gradient(135deg, #ffd700, #daa520); }
        .label-npc { background: #666; color: #fff; }
        .label-trash { background: #222; color: #555; border-right: 1px solid #000; }

        .tier-games { flex: 1; padding: 10px; display: flex; flex-wrap: wrap; gap: 10px; background: rgba(0,0,0,0.3); }
        .game-card { width: 140px; background: #1a1a1e; border: 1px solid #333; border-radius: 4px; overflow: hidden; position: relative; cursor: pointer; transition: 0.2s; }
        .game-card:hover:not(.empty-card) { border-color: var(--primary); transform: translateY(-3px); }
        .game-cover { width: 100%; aspect-ratio: 16/9; position: relative; overflow: hidden; background: #000; }
        .game-cover img { width: 100%; height: 100%; object-fit: cover; }
        .game-votes { position: absolute; bottom: 0; right: 0; background: rgba(0,0,0,0.8); color: var(--primary); font-weight: bold; font-family: monospace; padding: 2px 6px; font-size: 12px; }
        .game-title { padding: 8px; font-size: 12px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #ccc; }
        .empty-card { border-style: dashed; border-color: #333; cursor: default; }

        .tier-row.is-trash .tier-games { background: repeating-linear-gradient(45deg, #111, #111 10px, #000 10px, #000 20px); }
        .tier-row.is-trash .game-card:not(.empty-card) { filter: grayscale(100%) brightness(0.4); }
        .tier-row.is-trash .game-card:not(.empty-card)::after { content: '🔒 危'; position: absolute; top: 50%; left: 50%; font-weight: bold; color: #ff4d4f; border: 2px solid #ff4d4f; padding: 2px 5px; transform: translate(-50%, -50%) rotate(-15deg); background: rgba(0,0,0,0.8); }

        @media (max-width: 768px) { .tier-row { flex-direction: column; } .tier-label { width: 100%; height: 40px; font-size: 18px; } .game-card { width: calc(33.33% - 7px); } }
    </style>
</head>
<body>

    <div class="battle-header">
        <a href="index.php?archive=1" class="back-btn">← 阅览海选历史盲盒</a>
        <h1 class="title">十二菜器 · 实时战况榜</h1>
        <div class="countdown">
            <span id="stage-label">距离缩圈定榜：</span>
            <span id="tier-timer">--:--:--</span>
        </div>
    </div>

    <div class="tier-board">
        <div class="tier-row"><div class="tier-label label-hang">夯 (1)</div><div class="tier-games"><?= renderGames($tiers['hang'], 1, false, $is_shrunk) ?></div></div>
        <div class="tier-row"><div class="tier-label label-top">顶尖 (2)</div><div class="tier-games"><?= renderGames($tiers['top'], 2, false, $is_shrunk) ?></div></div>
        <div class="tier-row"><div class="tier-label label-elite">人上人(3)</div><div class="tier-games"><?= renderGames($tiers['elite'], 3, false, $is_shrunk) ?></div></div>
        <div class="tier-row"><div class="tier-label label-npc">NPC (6)</div><div class="tier-games"><?= renderGames($tiers['npc'], 6, false, $is_shrunk) ?></div></div>
        <div class="tier-row is-trash"><div class="tier-label label-trash">拉 (12)</div><div class="tier-games"><?= renderGames($tiers['trash'], 12, true, $is_shrunk) ?></div></div>
    </div>

    <div class="ammo-bar">
        <div class="ammo-item">
            <div style="font-size: 12px; color: #888;">今日真爱票 (2分)</div>
            <div class="ammo-num ammo-love" id="ammo-love-ui"><?= $init_love ?> / 1</div>
        </div>
        <div class="ammo-item">
            <div style="font-size: 12px; color: #888;">今日普通票 (1分)</div>
            <div class="ammo-num ammo-normal" id="ammo-normal-ui"><?= $init_normal ?> / 5</div>
        </div>
    </div>

    <div class="modal-overlay" id="fire-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-content" style="background: #141418; width: 90%; max-width: 400px; border-radius: 12px; border: 1px solid var(--primary); padding: 30px; text-align: center;">
            <h2 style="color: #fff; margin-top: 0;">⚠️ 武器授权</h2>
            <p style="color: #aaa; font-size: 14px; margin-bottom: 30px;">向目标 <span id="target-game-name" style="color: var(--primary); font-weight: bold;">???</span> 开火：</p>
            <button onclick="executeFire('love')" style="width: 100%; padding: 15px; margin-bottom: 10px; background: linear-gradient(135deg, #ff1493, #8b008b); color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">💖 真爱票 (+2分)</button>
            <button onclick="executeFire('normal')" style="width: 100%; padding: 15px; margin-bottom: 20px; background: linear-gradient(135deg, #00bfff, #00008b); color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">🔵 普通票 (+1分)</button>
            <button onclick="document.getElementById('fire-modal').style.display='none'" style="background: transparent; color: #666; border: none; cursor: pointer; font-size: 13px;">取消</button>
        </div>
    </div>

    <script>
        // 倒计时逻辑
        const timeNodes = {
            shrink: new Date("2026-04-27 00:00:00").getTime(),
            final: new Date("2026-04-28 00:00:00").getTime()
        };

        function updateTierTimer() {
            const now = new Date().getTime();
            let target, label;
            if (now < timeNodes.shrink) { target = timeNodes.shrink; label = "距离缩圈定榜："; }
            else if (now < timeNodes.final) { target = timeNodes.final; label = "🔥 决赛冲刺截止："; document.getElementById('stage-label').style.color = "#ff4d4f"; }
            else { document.getElementById('stage-label').innerText = "🏁 比赛已结束"; document.getElementById('tier-timer').innerText = ""; return; }

            const diff = target - now;
            const d = Math.floor(diff / (1000 * 60 * 60 * 24));
            const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((diff % (1000 * 60)) / 1000);
            document.getElementById('stage-label').innerText = label;
            document.getElementById('tier-timer').innerText = `${d > 0 ? d + '天 ' : ''}${h}:${m}:${s}`;
        }
        setInterval(updateTierTimer, 1000); updateTierTimer();

        // 投票函数逻辑保持你之前的 executeFire ...
        let currentTargetId = null;
        function openVoteModal(id, title) {
            currentTargetId = id;
            document.getElementById('target-game-name').innerText = `《${title}》`;
            document.getElementById('fire-modal').style.display = 'flex';
        }

        async function executeFire(voteType) {
            if (!currentTargetId) return;
            let voter = localStorage.getItem('caiqi_voter_name');
            if (!voter) {
                voter = prompt("请输入社团代号：");
                if (!voter) return;
                localStorage.setItem('caiqi_voter_name', voter);
            }
            document.getElementById('fire-modal').style.display = 'none';
            try {
                const response = await fetch('api_vote_tierlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ymgal_id: currentTargetId, voter_name: voter, vote_type: voteType })
                });
                const json = await response.json();
                if (json.status === 'success') {
                    alert('🔥 ' + json.message);
                    setTimeout(() => { location.reload(); }, 800);
                } else { alert('❌ ' + json.message); }
            } catch (error) { alert('连接失败'); }
        }
    </script>
</body>
</html>