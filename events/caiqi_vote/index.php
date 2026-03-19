<?php
// 打开报错，方便排查问题
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. 引入配置文件 (必须放在最前面，否则无法连接数据库！)
$config_path = '../../config.php';
if (!file_exists($config_path)) {
    die("致命错误：系统核心配置文件丢失！");
}
require_once $config_path;

$real_proposals = [];

// 2. 开启唯一的一个 try-catch 保护罩，把所有数据库操作包在里面
try {
    $conn = db_connect();
    
    // 🚦 拦截器：检查当前活动阶段！
    $stage_check = $conn->query("SELECT setting_value FROM event_caiqi_settings WHERE setting_key = 'current_stage'");
    if ($stage_check && $stage_check->num_rows > 0) {
        $stage = $stage_check->fetch_row()[0];
        // 如果系统处于阶段2，且用户不是主动要看“历史海选(archive=1)”，则强制踢入淘汰赛界面！
        if ($stage == '2' && !isset($_GET['archive'])) {
            header("Location: tierlist.php");
            exit;
        }
    }
    
    // 3. 检查提案表是否存在（防止没人提交时报错）
    $check_table = $conn->query("SHOW TABLES LIKE 'event_caiqi_proposals'");
    
    if ($check_table && $check_table->num_rows > 0) {
        // 核心查询：按票数从高到低排列提案！
        $sql = "SELECT * FROM event_caiqi_proposals ORDER BY votes DESC, created_at ASC";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // 把当初存进去的 JSON 字符串还原成数组
                $games_array = json_decode($row['games_data'], true);
                $formatted_slots = [];
                
                // 将数据格式化为前端 UI 需要的结构
                if(is_array($games_array)) {
                    foreach($games_array as $game) {
                        $formatted_slots[] = [
                            'public' => isset($game['isPublic']) ? $game['isPublic'] : false,
                            'cover' => isset($game['cover']) ? $game['cover'] : '',
                            'title' => isset($game['title']) ? $game['title'] : ''
                        ];
                    }
                }
                
                $real_proposals[] = [
                    'id' => $row['id'],
                    'author_name' => $row['author_name'],
                    'votes' => $row['votes'],
                    'public_count' => $row['public_games'],
                    'total_count' => $row['total_games'],
                    'slots' => $formatted_slots
                ];
            }
        }
    }
    $conn->close();
} catch (Exception $e) {
    die("数据库通讯崩溃：" . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>十二菜器 · 盲盒提案大厅 - ZuelGal</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        /* 这里保留之前写好的所有炫酷 CSS 样式 */
        :root { --primary: #c9171e; --bg-dark: #0a0a0c; --panel-bg: #141418; --gold: #FFD700; }
        body { margin: 0; padding: 0; background-color: var(--bg-dark); color: #eee; font-family: 'Noto Serif SC', serif; background-image: radial-gradient(circle at 50% 0%, #2a080c 0%, #0a0a0c 60%); background-attachment: fixed; }
        .hero { text-align: center; padding: 60px 20px 40px; position: relative; border-bottom: 1px dashed #333; margin-bottom: 40px;}
        .back-btn { position: absolute; left: 20px; top: 20px; color: #aaa; text-decoration: none; font-size: 14px; border: 1px solid #444; padding: 5px 15px; border-radius: 4px; transition: 0.3s; }
        .back-btn:hover { color: #fff; border-color: var(--primary); }
        .title { font-size: 48px; color: var(--primary); margin: 0 0 10px 0; letter-spacing: 5px; text-shadow: 0 0 20px rgba(201,23,30,0.5); }
        .subtitle { color: #aaa; font-size: 16px; letter-spacing: 2px; margin-bottom: 30px; }
        .create-btn { display: inline-block; padding: 18px 40px; font-size: 20px; font-weight: bold; background: linear-gradient(135deg, var(--primary), #8b0000); color: #fff; text-decoration: none; border-radius: 8px; box-shadow: 0 5px 20px rgba(201,23,30,0.4); transition: 0.3s; border: 1px solid #ff4d4f; }
        .create-btn:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(201,23,30,0.6); }
        .lobby-container { max-width: 1200px; margin: 0 auto; padding: 0 20px 60px; }
        .section-title { font-size: 24px; color: #fff; border-left: 4px solid var(--primary); padding-left: 15px; margin-bottom: 30px; }
        .proposal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px; }
        .p-card { background: rgba(20, 20, 24, 0.8); border: 1px solid #333; border-radius: 12px; overflow: hidden; transition: 0.3s; cursor: pointer; position: relative; }
        .p-card:hover { border-color: var(--primary); transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.8); }
        .p-header { padding: 15px 20px; border-bottom: 1px solid #222; display: flex; justify-content: space-between; align-items: center; background: #0f0f12; }
        .p-author { font-size: 18px; font-weight: bold; color: #fff; }
        .p-votes { font-size: 20px; font-weight: 900; color: var(--gold); text-shadow: 0 0 10px rgba(255,215,0,0.3); }
        .p-preview { padding: 20px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; background: #111; }
        .mini-slot { aspect-ratio: 16/9; background: #000; border-radius: 2px; overflow: hidden; position: relative; border: 1px solid #222;}
        .mini-slot img { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
        .mini-hidden { background: #1a0505; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 12px; font-weight: bold; border-color: #3a0000; }
        .p-footer { padding: 15px; text-align: center; background: #0a0a0c; font-size: 14px; color: #888; transition: 0.3s; }
        .p-card:hover .p-footer { background: var(--primary); color: #fff; }
        .empty-state { text-align: center; padding: 80px 20px; color: #888; background: rgba(0,0,0,0.5); border: 1px dashed #444; border-radius: 12px; }
        
        /* 弹窗样式 */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: var(--panel-bg); width: 90%; max-width: 800px; border-radius: 12px; border: 1px solid var(--primary); box-shadow: 0 0 50px rgba(201,23,30,0.3); display: flex; flex-direction: column; max-height: 90vh; }
        .m-header { padding: 20px 30px; border-bottom: 1px dashed #444; display: flex; justify-content: space-between; align-items: center; }
        .m-title { font-size: 24px; color: #fff; margin: 0; }
        .m-close { background: none; border: none; color: #888; font-size: 30px; cursor: pointer; transition: 0.3s; }
        .m-close:hover { color: var(--primary); }
        .m-body { padding: 30px; overflow-y: auto; flex: 1; }
        .m-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        @media (max-width: 600px) { .m-grid { grid-template-columns: repeat(2, 1fr); } }
        .detail-slot { aspect-ratio: 16/9; position: relative; border-radius: 4px; overflow: hidden; border: 1px solid #444; }
        .detail-slot img { width: 100%; height: 100%; object-fit: cover; }
        .detail-title { position: absolute; bottom: 0; left: 0; width: 100%; background: rgba(0,0,0,0.8); padding: 5px; box-sizing: border-box; font-size: 12px; text-align: center; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .detail-hidden { background: #110000; border-color: var(--primary); display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--primary); }
        .detail-hidden::before { content: '???'; font-size: 24px; font-weight: 900; margin-bottom: 5px;}
        .detail-hidden::after { content: 'RESTRICTED'; font-size: 10px; letter-spacing: 2px; }
        .m-footer { padding: 20px 30px; border-top: 1px solid #222; text-align: center; background: #0a0a0c; border-radius: 0 0 12px 12px; }
        .vote-btn { padding: 15px 50px; font-size: 18px; font-weight: bold; background: var(--primary); color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: 0.3s; width: 100%; max-width: 400px; }
        .vote-btn:hover { background: #ff4d4f; box-shadow: 0 0 20px rgba(201,23,30,0.6); }
        @media (max-width: 768px) { .title { font-size: 32px; } .create-btn { padding: 15px 30px; font-size: 16px; width: 80%; box-sizing: border-box;} .p-preview { grid-template-columns: repeat(3, 1fr); } }
    </style>
</head>
<body>

    <div class="hero">
        <a href="../../index.html" class="back-btn">← 撤退</a>
        <h1 class="title">十二菜器 · 盲盒海选</h1>
        <div class="subtitle">第一阶段：暗流涌动的提案之战</div>
        <div id="countdown-box" style="margin-top: 20px; font-family: monospace; color: #888; font-size: 14px;">
            距离提案封存还有：<span id="caiqi-timer" style="color: var(--primary); font-weight: bold; font-size: 18px;">--:--:--</span>
        </div>
        
        <script>
    // 🚩 在这里设置你的海选截止时间
    const deadline = new Date("2026-04-20 23:59:59").getTime();

    function updateTimer() {
        const now = new Date().getTime();
        const diff = deadline - now;
        if (diff <= 0) {
            document.getElementById('caiqi-timer').innerText = "海选已结束，档案封存中";
            return;
        }
        const d = Math.floor(diff / (1000 * 60 * 60 * 24));
        const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((diff % (1000 * 60)) / 1000);
        document.getElementById('caiqi-timer').innerText = `${d}天 ${h}时 ${m}分 ${s}秒`;
    }
    setInterval(updateTimer, 1000);
    updateTimer();
</script>
        
        <a href="propose.php" class="create-btn">➕ 拟定我的盲盒提案</a>
    </div>

    <div class="lobby-container">
        <h2 class="section-title">全域提案档案库 <span style="font-size: 14px; color: #888; margin-left: 10px; font-weight: normal;">(按得票数排序)</span></h2>
        
        <?php if (empty($real_proposals)): ?>
            <div class="empty-state">
                <div style="font-size: 40px; margin-bottom: 15px;">📂</div>
                <h3>档案库目前空无一物</h3>
                <p>战争才刚刚开始。点击上方按钮，去缔造第一份绝密档案吧！</p>
            </div>
        <?php else: ?>
            <div class="proposal-grid">
                <?php foreach ($real_proposals as $prop): ?>
                <div class="p-card" onclick='openModal(<?= json_encode($prop, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    <div class="p-header">
                        <div class="p-author"><?= htmlspecialchars($prop['author_name']) ?></div>
                        <div class="p-votes">🔥 <?= $prop['votes'] ?></div>
                    </div>
                    
                    <div class="p-preview">
                        <?php for ($i = 0; $i < 12; $i++): ?>
                            <?php if (isset($prop['slots'][$i])): ?>
                                <?php $slot = $prop['slots'][$i]; ?>
                                <?php if ($slot['public']): ?>
                                    <div class="mini-slot"><img src="<?= htmlspecialchars($slot['cover']) ?>" alt=""></div>
                                <?php else: ?>
                                    <div class="mini-slot mini-hidden">?</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="mini-slot" style="border: 1px dashed #333; background: transparent;"></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="p-footer">
                        点击拆解绝密档案并投票 ➔
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="proposal-modal">
        <div class="modal-content">
            <div class="m-header">
                <h2 class="m-title" id="modal-author-title">谁的提案</h2>
                <button class="m-close" onclick="closeModal()">&times;</button>
            </div>
            
            <div class="m-body">
                <div style="margin-bottom: 20px; color: #aaa; font-size: 14px; text-align: center;">
                    此提案包含 <span id="modal-total" style="color:#fff; font-weight:bold;">12</span> 部作品，其中 <span id="modal-hidden" style="color:var(--primary); font-weight:bold;">6</span> 部已被列为机密。
                </div>
                <div class="m-grid" id="modal-grid">
                    </div>
            </div>
            
            <div class="m-footer">
                <button class="vote-btn" id="modal-vote-btn" onclick="submitVote()">🗳️ 投送神圣一票</button>
            </div>
        </div>
    </div>

    <script>
        let currentProposalId = null;

        function openModal(proposalData) {
            currentProposalId = proposalData.id;
            document.getElementById('modal-author-title').innerText = proposalData.author_name + ' 的盲盒提案';
            
            let hiddenCount = 0;
            const grid = document.getElementById('modal-grid');
            grid.innerHTML = '';
            
            proposalData.slots.forEach(slot => {
                const div = document.createElement('div');
                if (slot.public) {
                    div.className = 'detail-slot';
                    div.innerHTML = `
                        <img src="${slot.cover}" alt="cover">
                        <div class="detail-title">${slot.title}</div>
                    `;
                } else {
                    hiddenCount++;
                    div.className = 'detail-slot detail-hidden';
                }
                grid.appendChild(div);
            });

            document.getElementById('modal-total').innerText = proposalData.slots.length;
            document.getElementById('modal-hidden').innerText = hiddenCount;

            document.getElementById('proposal-modal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('proposal-modal').style.display = 'none';
            document.body.style.overflow = 'auto';
            currentProposalId = null;
        }

        document.getElementById('proposal-modal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // 这个提交接口我们会在下一轮补齐
        async function submitVote() {
            if (!currentProposalId) return;
            
            // 从浏览器本地记忆里拿名字，如果没有就弹窗问
            let voter = localStorage.getItem('caiqi_voter_name');
            if (!voter) {
                voter = prompt("⚠️ 系统安检：请输入你的社团代号（注：整个海选阶段你只有 1 票权力）：");
                if (!voter) return; // 点了取消
                localStorage.setItem('caiqi_voter_name', voter); // 存入本地
            }

            const btn = document.getElementById('modal-vote-btn');
            btn.innerText = '链接服务器中...';
            btn.disabled = true;

            try {
                const response = await fetch('api_vote_proposal.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ proposal_id: currentProposalId, voter_name: voter })
                });
                const json = await response.json();

                if (json.status === 'success') {
                    alert('🎉 ' + json.message);
                    location.reload(); // 投票成功，刷新大厅看票数涨没涨！
                } else {
                    alert('❌ 投票失败：' + json.message);
                }
            } catch (error) {
                alert('网络断开连接。');
            } finally {
                btn.innerText = '🗳️ 投送神圣一票';
                btn.disabled = false;
            }
        }
    </script>
    <div onclick="accessAdmin()" style="position: fixed; bottom: 0; right: 0; width: 50px; height: 50px; cursor: default; z-index: 9999;"></div>
    <script>
        function accessAdmin() {
            let pwd = prompt("SYSTEM ADMIN OVERRIDE CODE:");
            if(pwd) window.location.href = 'admin_caiqi.php?pwd=' + pwd;
        }
    </script>
</body>
</html>