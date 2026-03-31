<?php
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>十二菜器 · 盲盒提案组装台 - ZuelGal</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        /* === 基础与暗黑科幻风格 === */
        :root { --primary: #c9171e; --bg-dark: #0a0a0c; --panel-bg: #141418; --border: #333; }
        body {
            margin: 0; padding: 0; background-color: var(--bg-dark);
            color: #eee; font-family: 'Noto Serif SC', serif;
            background-image: radial-gradient(circle at 50% 0%, #2a080c 0%, #0a0a0c 60%);
            background-attachment: fixed;
            overflow-x: hidden;
        }
        
        /* === 顶部导航 === */
        .header { text-align: center; padding: 40px 20px 20px; position: relative; }
        .back-btn {
            position: absolute; left: 20px; top: 20px;
            color: #aaa; text-decoration: none; font-size: 14px;
            border: 1px solid #444; padding: 5px 15px; border-radius: 4px; transition: 0.3s;
        }
        .back-btn:hover { color: #fff; border-color: var(--primary); }
        .title { font-size: 36px; color: var(--primary); margin: 0 0 10px 0; letter-spacing: 4px; text-shadow: 0 0 20px rgba(201,23,30,0.5); }
        .subtitle { color: #888; font-size: 14px; letter-spacing: 1px; }

        /* === 核心布局：双栏工作台 === */
        .workbench {
            max-width: 1400px; margin: 0 auto; padding: 20px;
            display: flex; gap: 30px; align-items: flex-start;
        }

        /* 左侧：搜索区 */
        .search-area { flex: 3; min-width: 0; }
        .search-box { display: flex; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .search-input {
            flex: 1; padding: 18px 20px; font-size: 16px;
            background: rgba(255,255,255,0.05); border: 1px solid var(--border);
            color: #fff; outline: none; transition: 0.3s; border-radius: 8px 0 0 8px;
        }
        .search-input:focus { border-color: var(--primary); background: rgba(255,255,255,0.08); }
        .search-btn {
            padding: 0 30px; font-size: 16px; background: var(--primary); font-weight: bold;
            color: #fff; border: none; cursor: pointer; border-radius: 0 8px 8px 0; transition: 0.3s;
        }
        .search-btn:hover { background: #e01922; box-shadow: 0 0 15px rgba(201,23,30,0.6); }

        /* 搜索结果网格 */
        .result-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .result-card {
            background: var(--panel-bg); border: 1px solid var(--border); border-radius: 8px;
            padding: 10px; text-align: center; transition: 0.3s;
        }
        .result-card:hover { border-color: #555; transform: translateY(-5px); }
        
        /* 严控封面比例 16:9 横屏 */
        .cover-16-9 {
            width: 100%; aspect-ratio: 16/9; border-radius: 4px;
            object-fit: cover; background-color: #000; margin-bottom: 10px;
        }
        .game-title { font-size: 15px; margin: 5px 0; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        .game-date { font-size: 12px; color: #777; margin-bottom: 15px;}
        .add-btn {
            width: 100%; padding: 8px; background: transparent; border: 1px solid var(--primary);
            color: var(--primary); border-radius: 4px; cursor: pointer; transition: 0.3s;
        }
        .add-btn:hover { background: var(--primary); color: #fff; }

        /* 右侧：我的提案手提箱 (固定跟随) */
        .proposal-area {
            flex: 2; min-width: 320px; background: var(--panel-bg);
            border: 1px solid var(--primary); border-radius: 12px; padding: 20px;
            position: sticky; top: 20px; box-shadow: 0 0 40px rgba(201,23,30,0.1);
        }
        .panel-title { border-bottom: 1px dashed #444; padding-bottom: 15px; margin-top: 0; display: flex; justify-content: space-between; align-items: flex-end;}
        .status-text { font-size: 13px; color: #888; font-family: sans-serif; }
        .status-highlight { color: var(--gold); font-weight: bold; }

        /* 12个槽位的网格 */
        .slots-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 20px 0;
        }
        .slot-item {
            aspect-ratio: 16/9; border: 1px dashed #444; border-radius: 6px;
            position: relative; display: flex; justify-content: center; align-items: center;
            color: #444; font-size: 20px; overflow: hidden; background: #0c0c0e; transition: 0.3s;
        }
        
        /* 已填充的槽位 */
        .slot-filled { border: 1px solid #555; }
        .slot-filled img { width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
        
        /* 绝密盲盒特效 */
        .slot-hidden img { filter: brightness(0.2) grayscale(100%) blur(2px); }
        .slot-hidden::after {
            content: '??? \A 绝密档案'; white-space: pre; position: absolute;
            color: var(--primary); font-weight: 900; font-size: 14px; text-align: center;
            line-height: 1.5; text-shadow: 0 0 5px #000; pointer-events: none;
            background: repeating-linear-gradient(45deg, rgba(0,0,0,0.5), rgba(0,0,0,0.5) 10px, rgba(201,23,30,0.2) 10px, rgba(201,23,30,0.2) 20px);
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
        }

        /* 槽位交互按钮 (删除 & 切换公开) */
        .slot-controls {
            position: absolute; bottom: 0; left: 0; width: 100%; height: 30px;
            background: rgba(0,0,0,0.8); display: flex; transform: translateY(100%); transition: 0.3s;
        }
        .slot-filled:hover .slot-controls { transform: translateY(0); }
        .control-btn { flex: 1; border: none; background: transparent; color: #fff; cursor: pointer; font-size: 12px; }
        .control-btn:hover { background: rgba(255,255,255,0.2); }
        .btn-del { color: #ff4d4f; }

        /* 底部提交表单 */
        .submit-zone { margin-top: 20px; }
        .author-input {
            width: 100%; padding: 12px; background: #000; border: 1px solid #444; color: #fff;
            border-radius: 6px; box-sizing: border-box; margin-bottom: 15px; text-align: center;
        }
        .submit-btn {
            width: 100%; padding: 15px; background: linear-gradient(135deg, var(--primary), #8b0000);
            color: #fff; border: none; font-size: 18px; font-weight: bold; border-radius: 6px;
            cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px rgba(201,23,30,0.4);
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(201,23,30,0.6); }
        .submit-btn:disabled { background: #444; color: #888; cursor: not-allowed; transform: none; box-shadow: none; }

        /* === 响应式 === */
        @media (max-width: 900px) {
            .workbench { flex-direction: column; padding: 10px; }
            .search-area { width: 100%; box-sizing: border-box; }
            .proposal-area { width: 100%; box-sizing: border-box; position: relative; top: 0; order: -1; margin-bottom: 20px;}
            .slots-grid { grid-template-columns: repeat(4, 1fr); }
            .search-box { flex-direction: column; }
            .search-input { border-radius: 8px; margin-bottom: 10px; }
            .search-btn { border-radius: 8px; padding: 15px; }
        }
        @media (max-width: 500px) {
            .slots-grid { grid-template-columns: repeat(3, 1fr); gap: 5px; }
            .result-grid { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
            .result-card { padding: 8px; min-width: 0; }
            .game-title { font-size: 13px; }
            .add-btn { font-size: 12px; padding: 6px; }
            .slot-controls { transform: translateY(0); height: 24px; } /* 手机端始终显示按钮 */
        }
        
        /* === 右上角规则按钮样式 === */
.cyber-rule-btn {
    position: fixed;
    top: 25px;
    right: 30px;
    /* 1. 降低 z-index：确保它在所有弹窗（通常是 1000+）之下 */
    z-index: 900; 
    
    background: rgba(10, 10, 12, 0.85);
    color: var(--primary, #c9171e);
    text-decoration: none;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: bold;
    letter-spacing: 2px;
    border: 1px solid var(--primary, #c9171e);
    border-radius: 4px;
    backdrop-filter: blur(8px);
    box-shadow: 0 0 10px rgba(201, 23, 30, 0.2);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    overflow: hidden;
}

/* 扫光特效 */
.cyber-rule-btn::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 50%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(201, 23, 30, 0.4), transparent);
    transform: skewX(-20deg);
    animation: cyber-sweep 3s infinite;
}

.cyber-rule-btn:hover {
    background: rgba(201, 23, 30, 0.15);
    box-shadow: 0 0 20px rgba(201, 23, 30, 0.6), inset 0 0 10px rgba(201, 23, 30, 0.3);
    transform: translateY(-2px);
    color: #fff;
}

.btn-icon {
    animation: warning-blink 2s infinite;
}

@keyframes cyber-sweep {
    0% { left: -100%; }
    20% { left: 200%; }
    100% { left: 200%; }
}
@keyframes warning-blink {
    0%, 100% { opacity: 1; text-shadow: 0 0 8px var(--primary, #c9171e); }
    50% { opacity: 0.4; text-shadow: none; }
}
        
        /* === BGM 开关样式 === */
#bgm-switch {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 44px;
    height: 44px;
    background: rgba(10, 10, 12, 0.8);
    border: 1px solid var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10000;
    backdrop-filter: blur(5px);
    transition: all 0.3s ease;
    box-shadow: 0 0 15px rgba(201, 23, 30, 0.2);
}

#bgm-icon {
    color: var(--primary);
    font-size: 20px;
    font-weight: bold;
    /* 播放时的旋转动画 */
    animation: music-spin 3s linear infinite;
}

/* 暂停状态的样式 */
#bgm-switch.paused {
    opacity: 0.5;
    border-color: #666;
}
#bgm-switch.paused #bgm-icon {
    color: #666;
    animation-play-state: paused;
}

@keyframes music-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

#bgm-switch:hover {
    transform: scale(1.1);
    box-shadow: 0 0 20px rgba(201, 23, 30, 0.5);
}
        
        
    </style>
</head>


    
<body>
    
        <audio id="caiqi-bgm" loop>
    <source src="../../assets/mfsn_battle.mp3" type="audio/mpeg">
</audio>

<div id="bgm-switch" onclick="toggleBGM()" title="音乐开关">
    <div id="bgm-icon">♪</div>
</div>
    

    <a href="rules.php" target="_blank" class="cyber-rule-btn">
    <span class="btn-icon">⚠</span> 赛事最高机密
</a>

    <div class="header">
        <a href="/events/caiqi_vote/index.php" class="back-btn">← 撤退</a>
        <h1 class="title">盲盒提案生成台</h1>
        <div class="subtitle">组装至多12部作品的提案，将不超过一半的作品曝光</div>
    </div>

    <div class="workbench">
        <div class="search-area">
            <div class="search-box">
                <input type="text" id="search-input" class="search-input" placeholder="输入作品名，检索 YmGal 数据库...">
                <button class="search-btn" onclick="performSearch()">扫描档案</button>
            </div>
            <div id="result-grid" class="result-grid">
                <div style="grid-column: 1/-1; text-align: center; color: #666; padding: 50px;">
                    等待检索指令...
                </div>
            </div>
        </div>

        <div class="proposal-area">
            <h3 class="panel-title">
                我的十二菜器提案
                <div class="status-text">
                    已列装 <span id="count-total" class="status-highlight">0</span>/12 
                    | 曝光 <span id="count-public" style="color:var(--primary);">0</span>/0
                </div>
            </h3>
            
            <div class="slots-grid" id="slots-grid">
                </div>
            
            <div style="font-size: 12px; color: #888; margin-bottom: 15px; line-height: 1.5;">
                <b style="color:var(--primary);">规则：</b>最多可收录 12 部作品。<b style="color:#fff;">你最多只能将其中一半(向下取整)设为公开曝光</b>，其余必须以绝密盲盒形式提交
            </div>

            <div class="submit-zone">
                <input type="text" id="author-name" class="author-input" placeholder="输入你的CN昵称">
                <button class="submit-btn" id="submit-btn" onclick="submitProposal()" disabled>封存并提交提案</button>
            </div>
        </div>
    </div>

<script>

    const bgm = document.getElementById('caiqi-bgm');
const bgmBtn = document.getElementById('bgm-switch');

// 1. 手动切换逻辑
function toggleBGM() {
    if (bgm.paused) {
        bgm.play();
        bgmBtn.classList.remove('paused');
    } else {
        bgm.pause();
        bgmBtn.classList.add('paused');
    }
}

// 2. 破解浏览器“自动播放”限制
// 只要用户在页面任何地方点一下，音乐就会立刻开启（符合“默认开启”的意图）
document.addEventListener('click', function() {
    if (bgm.paused && !bgmBtn.classList.contains('paused')) {
        bgm.play().catch(e => console.log("等待交互以播放音频"));
    }
}, { once: true }); // 只触发一次，之后就不管了

// 3. 尝试直接播放（部分环境下如果已有交互记录则会成功）
window.addEventListener('load', () => {
    bgm.volume = 0.5; // 建议初始音量 50%，不要吓到群友
    bgm.play().catch(() => {
        console.log("浏览器拦截了自动播放，等待用户点击...");
    });
});


    // === 核心数据状态 ===
    let myProposal = []; // 存放选中的游戏对象 {id, title, cover, isPublic: false}
    const MAX_GAMES = 12;

    // 初始化渲染 12 个空槽位
    function renderSlots() {
        const grid = document.getElementById('slots-grid');
        grid.innerHTML = '';
        
        let publicCount = 0;
        myProposal.forEach(g => { if(g.isPublic) publicCount++; });
        
        // 动态计算最大可公开数量 (向下取整)
        const maxPublicAllowed = Math.floor(myProposal.length / 2);
        
        // 更新面板数据
        document.getElementById('count-total').innerText = myProposal.length;
        document.getElementById('count-public').innerText = publicCount;
        document.getElementById('count-public').nextSibling.textContent = '/' + maxPublicAllowed; 
        
        // 控制提交按钮状态
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.disabled = (myProposal.length === 0);

        // 渲染 12 个格子
        for (let i = 0; i < MAX_GAMES; i++) {
            const slot = document.createElement('div');
            
            if (i < myProposal.length) {
                const game = myProposal[i];
                slot.className = `slot-item slot-filled ${game.isPublic ? '' : 'slot-hidden'}`;
                slot.innerHTML = `
                    <img src="${game.cover}" alt="cover">
                    <div class="slot-controls">
                        <button class="control-btn" title="切换公开/绝密状态" onclick="toggleVisibility(${i}, ${maxPublicAllowed})">
                            ${game.isPublic ? '👁️ 曝光' : '🚫 绝密'}
                        </button>
                        <button class="control-btn btn-del" onclick="removeGame(${i})">✖ 移除</button>
                    </div>
                `;
            } else {
                slot.className = 'slot-item';
                slot.innerText = '+';
            }
            grid.appendChild(slot);
        }
    }

    // 添加游戏到提案
    function addGame(id, title, cover) {
        if (myProposal.length >= MAX_GAMES) {
            alert('警告：提手提箱已满，最多容纳 12 部作品！');
            return;
        }
        if (myProposal.find(g => g.id === id)) {
            alert('该作品已在你的提案中！');
            return;
        }
        myProposal.push({ id, title, cover, isPublic: false });
        renderSlots();
    }

    // 移除游戏
    function removeGame(index) {
        myProposal.splice(index, 1);
        let publicCount = myProposal.filter(g => g.isPublic).length;
        const maxPublicAllowed = Math.floor(myProposal.length / 2);
        
        while (publicCount > maxPublicAllowed) {
            for (let i = myProposal.length - 1; i >= 0; i--) {
                if (myProposal[i].isPublic) {
                    myProposal[i].isPublic = false;
                    publicCount--;
                    break;
                }
            }
        }
        renderSlots();
    }

    // 切换曝光/绝密状态
    function toggleVisibility(index, maxAllowed) {
        const game = myProposal[index];
        let currentPublic = myProposal.filter(g => g.isPublic).length;

        if (!game.isPublic) {
            if (currentPublic >= maxAllowed) {
                alert(`规则限制：你的提案总数为 ${myProposal.length} 部，最多只能曝光 ${maxAllowed} 部作品！`);
                return;
            }
            game.isPublic = true;
        } else {
            game.isPublic = false;
        }
        renderSlots();
    }

    // 搜索监听
    document.getElementById('search-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') performSearch();
    });

    // 搜索执行
    async function performSearch() {
        const keyword = document.getElementById('search-input').value.trim();
        if (!keyword) return;

        const btn = document.querySelector('.search-btn');
        const grid = document.getElementById('result-grid');
        btn.innerText = '检索中...'; btn.disabled = true;

        try {
            const response = await fetch(`search_ymgal.php?keyword=${encodeURIComponent(keyword)}`);
            const json = await response.json();

            if (json.status === 'success') {
                grid.innerHTML = ''; 
                if (json.data.length === 0) {
                    grid.innerHTML = '<p style="color:#aaa; grid-column:1/-1; text-align:center;">数据库中未找到该档案...</p>';
                } else {
                    json.data.forEach(game => {
                        const cover = game.cover_url || 'https://via.placeholder.com/300x169?text=NO+COVER';
                        const safeTitle = game.title_cn.replace(/'/g, "\\'").replace(/"/g, "&quot;");
                        
                        grid.innerHTML += `
                            <div class="result-card">
                                <img src="${cover}" class="cover-16-9" alt="cover">
                                <div class="game-title" title="${game.title_cn}">${game.title_cn}</div>
                                <div class="game-date">${game.release_date || '未知日期'}</div>
                                <button class="add-btn" onclick="addGame(${game.ymgal_id}, '${safeTitle}', '${cover}')">
                                    + 添入提案
                                </button>
                            </div>
                        `;
                    });
                }
            } else { alert('检索出错: ' + json.message); }
        } catch (error) {
            alert('网络连接异常');
        } finally {
            btn.innerText = '扫描档案'; btn.disabled = false;
        }
    }

    // 真实提交逻辑：对接 PHP 接口
    async function submitProposal() {
        const author = document.getElementById('author-name').value.trim();
        if (!author) { alert('请输入你的代号！我们需要知道这是谁的提案。'); return; }
        
        const publicCount = myProposal.filter(g => g.isPublic).length;
        if (publicCount === 0) {
            // confirm 会弹出一个带有“确定”和“取消”的系统弹窗
            const isSure = confirm('系统检测到你目前没有曝光任何作品（全为绝密盲盒），是否继续提交？');
            if (!isSure) {
                return; // 如果用户点了“取消”，直接退出函数，不提交
            }
        }
        
        
        const payload = {
            author_name: author,
            total_games: myProposal.length,
            proposal_data: myProposal 
        };

        const btn = document.getElementById('submit-btn');
        btn.innerText = '系统正在封存数据...';
        btn.disabled = true;

        try {
            const response = await fetch('api_submit_proposal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const json = await response.json();

            if (json.status === 'success') {
                alert('🎉 ' + json.message);
                window.location.href = 'index.php'; 
            } else {
                alert('❌ 提交失败：' + json.message);
                btn.innerText = '封存并提交提案';
                btn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            alert('网络连接异常，无法连通服务器。');
            btn.innerText = '封存并提交提案';
            btn.disabled = false;
        }
    }

    // 初始化运行一次
    renderSlots();
</script>

</body>
</html>