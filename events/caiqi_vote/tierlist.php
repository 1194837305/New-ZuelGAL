<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === tierlist.php 完整视觉增强与修复版 ===
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('PRC'); 


$config_path = '../../config.php';
if (!file_exists($config_path)) {
    die("致命错误：系统核心配置文件丢失！");
}
require_once $config_path;

$games = [];
$is_shrunk = (time() > strtotime("2026-04-27 00:00:00")); 

try {
    $conn = db_connect();
    
    $conn->query("CREATE TABLE IF NOT EXISTS event_caiqi_works (
        ymgal_id INT PRIMARY KEY,
        title_cn VARCHAR(255) NOT NULL,
        cover_url TEXT,
        nomination_count INT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->query("CREATE TABLE IF NOT EXISTS event_caiqi_tier_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ymgal_id INT NOT NULL,
        voter_name VARCHAR(100) NOT NULL,
        ip_address VARCHAR(50) NOT NULL,
        vote_type VARCHAR(20) NOT NULL,
        vote_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    $check_tier_votes = $conn->query("SHOW COLUMNS FROM event_caiqi_works LIKE 'tier_votes'");
    if ($check_tier_votes && $check_tier_votes->num_rows == 0) {
        $conn->query("ALTER TABLE event_caiqi_works ADD COLUMN tier_votes INT DEFAULT 0");
    }
    $check_last_voted = $conn->query("SHOW COLUMNS FROM event_caiqi_works LIKE 'last_voted_at'");
    if ($check_last_voted && $check_last_voted->num_rows == 0) {
        $conn->query("ALTER TABLE event_caiqi_works ADD COLUMN last_voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    $top24_ids = [];
    $nom_map = [];
    $works_res = $conn->query("SELECT ymgal_id, nomination_count FROM event_caiqi_works");
    if ($works_res && $works_res->num_rows > 0) {
        while ($row = $works_res->fetch_assoc()) {
            $nom_map[$row['ymgal_id']] = (int)$row['nomination_count'];
        }
    }

    $prop_sql = "SELECT games_data FROM event_caiqi_proposals ORDER BY votes DESC, created_at ASC";
    $prop_res = $conn->query($prop_sql);

    $rollover_quota = 0; 
    $rank_index = 0;     

    if ($prop_res && $prop_res->num_rows > 0) {
        while ($row = $prop_res->fetch_assoc()) {
            if ($rank_index == 0) { $base_quota = 12; }
            elseif ($rank_index == 1) { $base_quota = 8; }
            else { $base_quota = 4; }

            $current_quota = $base_quota + $rollover_quota; 
            $games_array = json_decode($row['games_data'], true);
            $unique_candidates = [];

            if (is_array($games_array)) {
                foreach ($games_array as $g) {
                    $g_id = (int)$g['id'];
                    if (!in_array($g_id, $top24_ids)) {
                        $unique_candidates[] = $g_id;
                    }
                }
            }

            usort($unique_candidates, function($a, $b) use ($nom_map) {
                $count_a = $nom_map[$a] ?? 0;
                $count_b = $nom_map[$b] ?? 0;
                return $count_b <=> $count_a; 
            });

            $selected_games = array_slice($unique_candidates, 0, $current_quota);
            $top24_ids = array_merge($top24_ids, $selected_games);
            $added_count = count($selected_games);
            $rollover_quota = $current_quota - $added_count;
            $rank_index++;

            if (count($top24_ids) >= 24) {
                $top24_ids = array_slice($top24_ids, 0, 24); 
                break;
            }
        }
    }
    
    if (empty($top24_ids)) {
        die("当前没有任何合法的提案数据，无法生成淘汰赛榜单！");
    }
    
    $id_list = implode(',', $top24_ids);
    $sql = "SELECT * FROM event_caiqi_works WHERE ymgal_id IN ($id_list) ORDER BY tier_votes DESC, last_voted_at DESC, nomination_count DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $games[] = $row; 
        }
    }
    
    $today = date('Y-m-d');
    $ip = $_SERVER['REMOTE_ADDR'];
    
    


    $conn->close();
} catch (Exception $e) {
    die("数据库通讯崩溃：" . $e->getMessage());
}

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

function renderGames($tier_array, $max_slots, $is_trash_tier = false, $is_shrunk_global = false) {
    $html = '';
    $count = count($tier_array);
    for ($i = 0; $i < $count; $i++) {
        $g = $tier_array[$i];
        $nom_count = $g['nomination_count'];
        $tier_v = $g['tier_votes'];
        
        $votes_html = '<span style="color:#888; font-size:10px;">'.$nom_count.'提 | </span>' . $tier_v . '战';
        
        // 💡 修复核心：安全转义标题，彻底避免特殊字符搞崩 JS
        $encoded_title = htmlspecialchars($g['title_cn'], ENT_QUOTES, 'UTF-8');
        
        // 💡 修复核心：使用 dataset 提取标题，而不是在 onclick 里硬拼字符串
        $onclick = ($is_shrunk_global && $is_trash_tier) 
                   ? "alert('🚨 缩圈警报：该作品已被锁死在深渊，无法再进行投票！')" 
                   : "openVoteModal(".$g['ymgal_id'].", this.dataset.title)";

        $html .= '
        <div class="game-card" data-title="'.$encoded_title.'" onclick="'.$onclick.'">
            <div class="game-cover">
                <img src="'.htmlspecialchars($g['cover_url']).'" alt="">
                <div class="game-votes">'.$votes_html.'</div>
            </div>
            <div class="game-title">'.$encoded_title.'</div>
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
        .tier-row.is-trash .game-card:not(.empty-card)::after { 
            content: '🔒 危'; 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            font-weight: bold; 
            color: #ff4d4f; 
            border: 2px solid #ff4d4f; 
            padding: 2px 5px; 
            transform: translate(-50%, -50%) rotate(-15deg); 
            background: rgba(0,0,0,0.8); 
            pointer-events: none; /* 💡 修复核心：穿透伪元素点击 */
        }

        @media (max-width: 768px) { .tier-row { flex-direction: column; } .tier-label { width: 100%; height: 40px; font-size: 18px; } .game-card { width: calc(33.33% - 7px); } }
    </style>
</head>
<body>

    <?php 
    // 无论你在哪个子文件夹，这行代码都能精准定位到根目录的 player.php
    include_once $_SERVER['DOCUMENT_ROOT'] . "/player.php"; 
?>

    <div class="battle-header">
        <a href="index.php?archive=1" class="back-btn">← 阅览海选历史盲盒</a>
        <h1 class="title">十二菜器</h1>
        <h4 class="title">第二阶段：淘汰赛</h4>
        <div class="countdown">
            <span id="stage-label">距离缩圈斩杀：</span>
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
         <div class="ammo-num ammo-love" id="ammo-love-ui">- / 1</div>
     </div>
     <div class="ammo-item">
         <div style="font-size: 12px; color: #888;">今日普通票 (1分)</div>
         <div class="ammo-num ammo-normal" id="ammo-normal-ui">- / 5</div>
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
        const timeNodes = {
            shrink: new Date("2026-04-27 00:00:00").getTime(),
            final: new Date("2026-04-28 00:00:00").getTime()
        };

        function updateTierTimer() {
            const now = new Date().getTime();
            let target, label;
            if (now < timeNodes.shrink) { target = timeNodes.shrink; label = "距离缩圈斩杀："; }
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
            
        
        // 🟢 弹药库动态同步引擎
     async function syncAmmo() {
         let voter = localStorage.getItem('caiqi_voter_name') || '';
         try {
             const response = await fetch('api_vote_tierlist.php', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json' },
                 body: JSON.stringify({ action: 'get_ammo', voter_name: voter })
             });
             const json = await response.json();
             if (json.status === 'success') {
                 document.getElementById('ammo-love-ui').innerText = json.remain_love + ' / 1';
                 document.getElementById('ammo-normal-ui').innerText = json.remain_normal + ' / 5';
             }
         } catch (error) {
             console.error("弹药库同步中断", error);
         }
     }

     // 页面一加载，立刻同步最真实的弹药量
     document.addEventListener('DOMContentLoaded', syncAmmo);

     // 优化投票执行逻辑：无刷新更新体验
     async function executeFire(voteType) {
         if (!currentTargetId) return;
         let voter = localStorage.getItem('caiqi_voter_name');
         if (!voter) {
             voter = prompt("请输入社团代号：");
             if (!voter) return;
             localStorage.setItem('caiqi_voter_name', voter);
             syncAmmo(); // 刚填完名字，立刻去查一下这个名字有没有票
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
                 // 💡 不再使用 location.reload() 刷新网页！
                 // 而是静默重新拉取票数，并稍等后局部更新UI，体验丝滑！
                 syncAmmo(); 
                 setTimeout(() => { location.reload(); }, 1500); // 延迟刷新，让用户先看清票数扣除
             } else { 
                 alert('❌ ' + json.message); 
                 syncAmmo(); // 哪怕拦截了，也刷新一下真实的剩余票数给用户看
             }
         } catch (error) { alert('服务器连接异常，请重试'); }
     }
        
    </script>
    
    <div id="danmaku-track" style="position: fixed; top: 0; left: 0; width: 100%; height: 50%; pointer-events: none; z-index: 9998; overflow: hidden;"></div>

<div id="danmaku-history">
    <div id="history-header" onclick="toggleDanmakuHistory()" title="点击 缩起/展开 日志">
        <span class="header-title">📜 战术通信日志</span>
        <span id="history-toggle-icon">▲</span>
    </div>
    <div id="history-content">
        <div class="history-item" style="color: var(--primary); border-color: var(--primary);">
            [系统广播]: 弹幕通信网络已连接，双轨通信启动...
        </div>
    </div>
</div>

<?php if(isset($_SESSION['user_id'])): ?>
    <div id="danmaku-controller">
        <input type="text" id="danmaku-input" placeholder="输入全频段广播..." maxlength="50" onkeypress="if(event.keyCode==13) sendDanmaku()">
        <button onclick="sendDanmaku()" id="danmaku-btn">发射</button>
    </div>
<?php else: ?>
    <div id="danmaku-controller" class="unauth-controller" onclick="openAuthModal('login')">
        <span>⚠ 接入系统以发送弹幕</span>
    </div>
<?php endif; ?>


<style>
/* 飞行弹幕基础样式 */
.danmaku-item {
    position: absolute;
    right: -100%;
    white-space: nowrap;
    font-size: 22px;
    font-weight: 900;
    color: #fff;
    text-shadow: 1px 1px 0 #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 0 0 8px rgba(0,0,0,0.8);
    animation: danmaku-move 9s linear forwards;
}

@keyframes danmaku-move {
    0% { right: -100%; transform: translateX(100%); }
    100% { right: 100%; transform: translateX(0); }
}

/* 历史记录墙容器 */
#danmaku-history {
    position: fixed;
    bottom: 80px; 
    left: 20px;
    width: 280px;
    z-index: 9998;
    display: flex;
    flex-direction: column;
    max-height: 200px;
    transition: max-height 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    overflow: hidden;
}

/* 手柄栏 */
#history-header {
    background: rgba(10, 10, 12, 0.9);
    padding: 6px 12px;
    border-radius: 4px 4px 0 0;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #aaa;
    border-bottom: 1px solid #333;
    user-select: none;
    transition: background 0.3s;
}
#history-header:hover { background: rgba(20, 20, 25, 1); }
#history-toggle-icon { transition: transform 0.4s; color: #888; }

/* 内容滚动区 */
#history-content {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 6px 0;
    -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 20%);
    mask-image: linear-gradient(to bottom, transparent 0%, black 20%);
}

/* 缩起状态 */
#danmaku-history.collapsed { max-height: 28px; }
#danmaku-history.collapsed #history-toggle-icon { transform: rotate(180deg); }
#danmaku-history.collapsed #history-content { -webkit-mask-image: none; mask-image: none; }

/* 单条日志 */
.history-item {
    background: rgba(10, 10, 12, 0.7);
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 12px;
    color: #ddd;
    border-left: 2px solid #555;
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease;
    word-break: break-all;
}
.history-name { font-weight: bold; color: #888; margin-right: 5px; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* 发射器 PC 端 */
#danmaku-controller {
    position: fixed;
    bottom: 25px;
    left: 20px;
    display: flex;
    gap: 10px;
    background: rgba(15, 15, 20, 0.9);
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #333;
    box-shadow: 0 5px 20px rgba(0,0,0,0.5);
    z-index: 9999;
}
#danmaku-input {
    background: transparent;
    border: none;
    color: #fff;
    outline: none;
    width: 220px;
    font-size: 14px;
}
#danmaku-btn {
    background: var(--primary);
    color: #fff;
    border: none;
    padding: 6px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}
.unauth-controller {
    justify-content: center;
    cursor: pointer;
    border-color: var(--primary) !important;
    background: rgba(201, 23, 30, 0.1) !important;
    color: var(--primary);
    font-weight: bold;
    font-size: 13px;
}

/* 📱 移动端绝对适配 */
@media (max-width: 768px) {
    #danmaku-controller {
        left: 0; bottom: 0; width: 100%;
        border-radius: 0; border: none;
        padding: 10px 15px; box-sizing: border-box;
        background: rgba(10, 10, 12, 0.98);
    }
    #danmaku-input { width: 100%; flex: 1; }
    #danmaku-history {
        bottom: 60px; left: 10px; width: 75%; max-height: 120px;
    }
    #danmaku-history.collapsed { max-height: 28px; }
    .danmaku-item { font-size: 16px; animation-duration: 7s; }
}
</style>


<script>
// === 双引擎弹幕核心 JS ===
let lastDanmakuId = 0;
const pageType = window.location.pathname.includes('tierlist.php') ? 'tierlist' : 'lobby'; 
const track = document.getElementById('danmaku-track');
const historyBox = document.getElementById('danmaku-history');
const historyContent = document.getElementById('history-content');
const toggleIcon = document.getElementById('history-toggle-icon');

const myNickname = "<?php echo isset($_SESSION['nickname']) ? addslashes($_SESSION['nickname']) : ''; ?>";

let danmakuPool = []; 
let poolIndex = 0;

// 控制缩起/展开
function toggleDanmakuHistory() {
    historyBox.classList.toggle('collapsed');
    if (historyBox.classList.contains('collapsed')) {
        toggleIcon.innerText = '▼';
    } else {
        toggleIcon.innerText = '▲';
        historyContent.scrollTop = historyContent.scrollHeight;
    }
}

// 写入日志墙
function appendHistory(name, text, isMine = false) {
    const div = document.createElement('div');
    div.className = 'history-item';
    if (isMine) div.style.borderLeftColor = '#FFD700';
    div.innerHTML = `<span class="history-name">${name}:</span><span>${text}</span>`;
    
    historyContent.appendChild(div);
    
    // 收到新消息自动展开
    if (historyBox.classList.contains('collapsed')) {
        toggleDanmakuHistory();
    }
    historyContent.scrollTop = historyContent.scrollHeight;
}

// 飞行弹幕渲染
function renderFlyingDanmaku(name, text, isMine = false) {
    const div = document.createElement('div');
    div.className = 'danmaku-item';
    
    if (isMine) {
        div.style.color = '#FFD700';
        div.style.border = '1px solid rgba(255, 215, 0, 0.5)';
        div.style.padding = '0 10px';
        div.style.borderRadius = '20px';
        div.style.background = 'rgba(0,0,0,0.5)';
    }

    div.innerText = text; 
    const lane = Math.floor(Math.random() * 10);
    div.style.top = (lane * 10) + '%';
    
    track.appendChild(div);
    setTimeout(() => { div.remove(); }, 9500); 
}

// 发射核心
async function sendDanmaku() {
    const input = document.getElementById('danmaku-input');
    if (!input) return;

    const text = input.value.trim();
    if (!text) return;
    input.value = '';

    renderFlyingDanmaku(myNickname, text, true);
    appendHistory(myNickname, text, true);

    try {
        const response = await fetch('api_danmaku.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send', page_type: pageType, content: text })
        });
        const json = await response.json();
        if (json.status !== 'success') alert('❌ ' + json.message);
    } catch (e) { console.error("发送异常", e); }
}

// 雷达同步
async function fetchDanmaku() {
    try {
        const response = await fetch('api_danmaku.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get', page_type: pageType, last_id: lastDanmakuId })
        });
        const json = await response.json();
        
        if (json.status === 'success' && json.data.length > 0) {
            lastDanmakuId = json.last_id;
            
            json.data.forEach((msg) => {
                danmakuPool.push(msg);
                appendHistory(msg.name, msg.text);
                
                if (msg.name !== myNickname) {
                    renderFlyingDanmaku(msg.name, msg.text);
                }
            });
        }
    } catch (e) {}
}

// 永动循环引擎
function loopHistoricalDanmaku() {
    if (danmakuPool.length > 0 && Math.random() > 0.4) {
        const msg = danmakuPool[poolIndex];
        renderFlyingDanmaku(msg.name, msg.text);
        poolIndex = (poolIndex + 1) % danmakuPool.length;
    }
    setTimeout(loopHistoricalDanmaku, Math.random() * 1500 + 1500);
}

// 启动系统
setInterval(fetchDanmaku, 3000);
fetchDanmaku();
loopHistoricalDanmaku();
</script>
    
<div class="trial-hall-bg">
    <div class="bg-image"></div>
    <div id="tsparticles"></div> 
    <div class="bg-overlay"></div>
    <div class="bg-vignette"></div>
</div>

<style>
/* 初始状态是完全透明的 */
#voter-pulse-overlay {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: #fff; /* 能量爆发的白光，也可以选 #c9171e 深红 */
    opacity: 0;
    z-index: 5; /* 确保在所有背景元素之上 */
    pointer-events: none;
    transition: none;
}

/* 爆闪动画 */
@keyframes flash-overload {
    0% { opacity: 0.8; filter: brightness(2); }
    100% { opacity: 0; filter: brightness(1); }
}

.trigger-flash {
    animation: flash-overload 0.8s ease-out forwards;
}
/* === 视觉架构 CSS 修正 === */
.trial-hall-bg {
    position: fixed; /* 🟢 修正：使用 fixed，确保滚动时背景图和粒子都不截断 */
    top: 0; left: 0; width: 100vw; height: 100vh;
    z-index: -2;
    overflow: hidden;
}

.bg-image {
    position: absolute;
    top: -2%; left: -2%; width: 104%; height: 104%; /* 稍微大一点，防止模糊边缘露出 */
    /* 核心：再次指向提案大厅的背景图 */
    background: url('/assets/caiqi_bg.webp') center/cover no-repeat;
    /* 艺术处理：模糊、降饱和度、压暗，让粒子特效更清晰 */
    filter: blur(5px) saturate(0.3) brightness(0.2); 
    transition: filter 1s ease;
}

#tsparticles {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    z-index: 1; /* 🟢 必须比 .bg-image 高，但比 .bg-overlay 低 */
    pointer-events: none; /* 穿透点击，让鼠标能点到后面的投票卡片 */
}

/* 🟢 在这里给 tsParticles 自动生成的 canvas 加上混合模式 */
#tsparticles canvas {
    mix-blend-mode: screen; 
    opacity: 0.8;
}
</style>
<script src="/assets/js/cyber_bg.js"></script>

<script src="https://cdn.jsdelivr.net/npm/tsparticles-engine@2/tsparticles.engine.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tsparticles-basic@2/tsparticles.basic.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tsparticles-interaction-particles-links@2/tsparticles.interaction.particles.links.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tsparticles-move-base@2/tsparticles.move.base.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tsparticles-shape-polygon@2/tsparticles.shape.polygon.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tsparticles-updater-color@2/tsparticles.updater.color.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tsparticles-updater-opacity@2/tsparticles.updater.opacity.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tsparticles-updater-size@2/tsparticles.updater.size.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tsparticles-plugin-browser@2/tsparticles.plugin.browser.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/tsparticles@2/tsparticles.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    tsParticles.load("tsparticles", {
        fpsLimit: 60,
    particles: {
        color: { value: ["#c9171e", "#ff4d4f", "#ffd700"] }, // 赤红与金色
        links: { enable: false }, // 🟢 抛弃网格，纯粒子爆散美学
        move: { direction: "none", enable: true, outModes: "bounce", random: true, speed: 6, straight: false }, // 粒子速度飞快，带反弹
        number: { density: { enable: true, area: 800 }, value: 150 }, // 粒子数量激增
        opacity: { value: { min: 0.1, max: 1 }, animation: { enable: true, speed: 3, minimumValue: 0.1, sync: false } },
        shape: { type: "circle" }, // 工整圆点，突出爆散感
        size: { value: { min: 1, max: 3 }, animation: { enable: true, speed: 5, minimumValue: 1, sync: false } }
    },
    interactivity: {
        detectsOn: "window",
        events: {
            onHover: { enable: true, mode: "bubble" }, // 🟢 鼠标划过，粒子瞬间“爆燃”变大变亮
            resize: true
        },
        modes: { bubble: { distance: 120, duration: 2, opacity: 1, size: 8, color: "#ffd700" } }
    },
    detectRetina: true// 适配高分屏
    });
});

/**
 * 触发系统过载联动特效
 */
function triggerSystemOverload() {
    const overlay = document.getElementById('voter-pulse-overlay');
    
    // 1. 触发全屏闪烁
    overlay.classList.remove('trigger-flash');
    void overlay.offsetWidth; // 强制重绘，确保动画可以重复触发
    overlay.classList.add('trigger-flash');

    // 2. 联动 tsParticles：进入“过载模式”
    const container = tsParticles.domItem(0); // 获取当前的粒子容器实例
    if (container) {
        // 瞬间提升粒子速度和大小
        container.options.particles.move.speed = 20; 
        container.options.particles.size.value = { min: 5, max: 15 };
        container.refresh(); // 刷新渲染

        // 1秒后平滑降压，恢复正常水平
        setTimeout(() => {
            container.options.particles.move.speed = 6; 
            container.options.particles.size.value = { min: 1, max: 3 };
            container.refresh();
        }, 800);
    }
}

</script>

</body>
</html>