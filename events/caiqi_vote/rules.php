<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>十二菜器 · 赛事最高机密档案</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #c9171e;
            --bg-dark: #050507;
            --text-main: #e0e0e0;
            --text-muted: #888;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Noto Serif SC', serif;
            overflow-x: hidden;
        }

        /* 赛博扫描线背景 */
        .scanlines {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,0) 50%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0.2));
            background-size: 100% 4px;
            z-index: 9999;
            pointer-events: none;
            opacity: 0.3;
        }

        /* 动态网格背景 */
        .grid-bg {
            position: fixed;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background-image: 
                linear-gradient(rgba(201, 23, 30, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(201, 23, 30, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            transform: perspective(500px) rotateX(60deg) translateY(-100px);
            animation: grid-move 10s linear infinite;
            z-index: -1;
        }

        @keyframes grid-move {
            0% { transform: perspective(500px) rotateX(60deg) translateY(0); }
            100% { transform: perspective(500px) rotateX(60deg) translateY(40px); }
        }

        /* 核心布局 */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 60px 20px 100px;
        }

        /* Glitch 故障标题 */
        .glitch-title {
            font-size: 3rem;
            font-weight: 900;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 10px;
            position: relative;
            margin-bottom: 60px;
            color: #fff;
            text-shadow: 0 0 20px var(--primary);
        }
        
        .glitch-title::before, .glitch-title::after {
            content: attr(data-text);
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0.8;
        }
        .glitch-title::before {
            left: 2px;
            text-shadow: -2px 0 #00ffff;
            clip: rect(44px, 450px, 56px, 0);
            animation: glitch-anim 5s infinite linear alternate-reverse;
        }
        .glitch-title::after {
            left: -2px;
            text-shadow: -2px 0 var(--primary);
            clip: rect(44px, 450px, 56px, 0);
            animation: glitch-anim2 5s infinite linear alternate-reverse;
        }

        @keyframes glitch-anim {
            0% { clip: rect(10px, 9999px, 86px, 0); }
            5% { clip: rect(42px, 9999px, 14px, 0); }
            10% { clip: rect(97px, 9999px, 81px, 0); }
            15% { clip: rect(6px, 9999px, 100px, 0); }
            20% { clip: rect(51px, 9999px, 65px, 0); }
            100% { clip: rect(51px, 9999px, 65px, 0); }
        }
        @keyframes glitch-anim2 {
            0% { clip: rect(65px, 9999px, 100px, 0); }
            5% { clip: rect(14px, 9999px, 86px, 0); }
            10% { clip: rect(81px, 9999px, 6px, 0); }
            15% { clip: rect(100px, 9999px, 97px, 0); }
            20% { clip: rect(42px, 9999px, 10px, 0); }
            100% { clip: rect(42px, 9999px, 10px, 0); }
        }

        /* 规则卡片 */
        .rule-section {
            background: rgba(15, 15, 20, 0.8);
            border: 1px solid #222;
            border-left: 4px solid var(--primary);
            border-radius: 4px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
            opacity: 0; /* 配合JS实现滚动淡入 */
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .rule-section.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .rule-section:hover {
            border-color: #444;
            border-left-color: #ff1493;
            box-shadow: 0 10px 40px rgba(201, 23, 30, 0.2);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px dashed #333;
            padding-bottom: 15px;
        }

        .phase-badge {
            background: var(--primary);
            color: #fff;
            padding: 4px 12px;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
            transform: skewX(-15deg);
        }
        
        .phase-badge span { display: block; transform: skewX(15deg); }

        .section-title {
            font-size: 1.8rem;
            color: #fff;
            margin: 0;
        }

        .rule-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .rule-list li {
            position: relative;
            padding-left: 25px;
            margin-bottom: 15px;
            line-height: 1.8;
            font-size: 1.05rem;
        }

        .rule-list li::before {
            content: '>';
            position: absolute;
            left: 0;
            color: var(--primary);
            font-family: monospace;
            font-weight: bold;
            animation: blink 1s infinite;
        }

        .highlight {
            color: #00ffff;
            font-weight: bold;
            text-shadow: 0 0 8px rgba(0, 255, 255, 0.4);
        }
        .highlight-red {
            color: #ff4d4f;
            font-weight: bold;
            text-shadow: 0 0 8px rgba(255, 77, 79, 0.4);
        }

        /* 动态图像留白位 */
        .media-placeholder {
            width: 100%;
            height: 250px;
            margin-top: 30px;
            background: #0a0a0c;
            border: 1px dashed #444;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-family: monospace;
            overflow: hidden;
        }
        
        /* 留白位扫描线动画 */
        .media-placeholder::before {
            content: '';
            position: absolute;
            top: -100%; left: 0; width: 100%; height: 20%;
            background: linear-gradient(to bottom, transparent, rgba(201, 23, 30, 0.2), transparent);
            animation: scan 4s infinite linear;
        }

        @keyframes scan {
            0% { top: -20%; }
            100% { top: 120%; }
        }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

        /* === 审判大厅背景架构 === */
.trial-hall-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -2; /* 确保在所有内容之下 */
    overflow: hidden;
    background: #050507; /* 极深色兜底 */
}

.bg-image {
    position: absolute;
    top: -5%;
    left: -5%;
    width: 110%;
    height: 110%;
    /* 核心：使用已验证正确的根目录路径 */
    background: url('/assets/caiqi_bg.webp') center/cover no-repeat;
    
    /* 艺术滤镜：模糊、降噪、低亮度 */
    /* 适当调高 brightness(0.6)，确保能看清审判大厅的轮廓 */
    filter: blur(10px) saturate(0.6) brightness(0.6); 
    
    /* 增加极慢的呼吸缩放感 */
    animation: bg-pulse 25s infinite alternate ease-in-out;
    transition: filter 1s ease;
}

/* 径向遮罩：中间亮、四周暗，像聚光灯打在提案卡片上 */
.bg-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(
        circle at center, 
        transparent 10%, 
        rgba(5, 5, 7, 0.4) 40%, 
        rgba(5, 5, 7, 0.85) 80%, 
        #050507 100%
    );
}

/* 强化暗角：增加压迫感 */
.bg-vignette {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    box-shadow: inset 0 0 150px rgba(0, 0, 0, 0.9);
    pointer-events: none;
}

@keyframes bg-pulse {
    0% { transform: scale(1); }
    100% { transform: scale(1.08); }
}

/* 移动端性能优化：减少模糊度以防卡顿 */
@media (max-width: 768px) {
    .bg-image {
        filter: blur(5px) saturate(0.5) brightness(0.5);
        animation: none;
    }
}


    </style>
</head>
<body>

    <div class="trial-hall-bg">
    <div class="bg-image"></div>
    <div class="bg-overlay"></div>
    <div class="bg-vignette"></div>
</div>

    <div class="scanlines"></div>
    <div class="grid-bg"></div>

    <div class="container">
        <h1 class="glitch-title" data-text="十二菜器·作战法则">十二菜器·作战法则</h1>

        <div class="rule-section">
            <div class="section-header">
                <div class="phase-badge"><span>PHASE 1</span></div>
                <h2 class="section-title">盲盒提案海选</h2>
            </div>
            <ul class="rule-list">
                <li>社员可在提案大厅提交自己心仪的 Galgame 作品列表，并为其它提案投票（仅1票机会）</li>
                <li>每份提案最多包含 <span class="highlight">12 部作品</span>,可以将不超过一半的作品设置为公开可见</li>
                <li>提案将在后台自动根据得票热度进行排序，根据提案的得票分配晋级下一轮的24部作品名额。</li>
            </ul>
            <div class="media-placeholder" style="background-image: url('/assets/caiqirule1.gif'); background-size: cover; background-position: center;">
                
            </div>
        </div>

        <div class="rule-section">
            <div class="section-header">
                <div class="phase-badge"><span>PHASE 2</span></div>
                <h2 class="section-title">缩圈大逃杀</h2>
            </div>
            <ul class="rule-list">
                <li>生成 24 强名单后，正式开启“十二菜器”第二赛段实时榜单战况。</li>
                <li>每日票数配额：每人每日拥有 <span class="highlight-red">1发 真爱票（+2战力）</span> 与 <span class="highlight">5发 普通票（+1战力）</span>。</li>
                <li>投票限制：每日对同一部作品只能进行一次票数注入，必须将投票分散至不同作品。</li>
                <li>投票用户判定：系统采用 <span class="highlight-red">IP地址 与 社团代号 双轨锁定</span>。</li>
            </ul>
            <div class="media-placeholder" style="background-image: url('/assets/caiqirule2.gif'); background-size: cover; background-position: center;">
                
            </div>
        </div>

        <div class="rule-section">
            <div class="section-header">
                <div class="phase-badge"><span>SYSTEM</span></div>
                <h2 class="section-title">顺延晋级与缩圈规则</h2>
            </div>
            <ul class="rule-list">
                <li>12-8-4-4... 顺延机制：高票提案享有12部作品的入围权，排名第二的提案有8部作品晋级，后续提案组合均为4部。若入围作品已在名单中，名额将顺延至该提案的总提名数更高作品直至晋级池到达24部。</li>
                <li>缩圈深渊机制：第二赛段当时间轴推进至决战前最后 24 小时，处于榜单 <span class="highlight-red">“拉”行列（第13-24名）的作品将被永久锁死在深渊</span>，无法再接收任何投票。</li>
                <li>可以通过全频段广播进行弹幕留言。</li>
                <li>同分判定：参考世萌规则，分数相同时，<span class="highlight">最后一次获得投票注入的时间越晚</span>（热度越高）的作品，排名越靠前。最终排名第一的作品将成为尼菜众望所归的旮旯届最强神作。</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 60px;">
            <a href="#" onclick="window.close(); return false;" style="color: var(--primary); text-decoration: none; border: 1px solid var(--primary); padding: 10px 30px; font-weight: bold; letter-spacing: 2px; transition: 0.3s;" onmouseover="this.style.background='var(--primary)'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='var(--primary)';">关闭档案 / 返回大厅</a>
        </div>
    </div>

    <script>
        // 极简高性能的滚动监听动画 (Intersection Observer)
        document.addEventListener('DOMContentLoaded', () => {
            const sections = document.querySelectorAll('.rule-section');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target); // 动画只触发一次
                    }
                });
            }, {
                threshold: 0.15 // 露出 15% 即可触发动画
            });

            sections.forEach(section => {
                observer.observe(section);
            });
        });
    </script>
</body>
</html>