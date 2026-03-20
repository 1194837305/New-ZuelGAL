<?php
/**
 * ZuelGal 核心入口文件
 */

// 1. 开启报错调试（如果页面正常了，请删掉这两行）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. 安全启动 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. 引用配置文件 (注意：这里已经帮你改成了 user_config.php)
if (file_exists('user_config.php')) {
    require_once 'user_config.php';
} else {
    // 如果找不到文件，直接报错，不至于白屏
    die("致命错误：根目录下未找到 user_config.php 文件。请检查宝塔面板中的文件名。");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ZuelGal - 中南财经政法大学视觉小说社</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700;900&display=swap" rel="stylesheet">
    
    <style>
        /* === 1. 全局与变量 === */
        :root {
            --primary: #c9171e; 
            --bg-dark: #0a0a0c;
            --text-main: #e0e0e0;
            --text-muted: #888888;
            --border-color: rgba(255, 255, 255, 0.15);
        }

        body, html {
            margin: 0; padding: 0;
            width: 100%; height: 100%;
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Noto Serif SC', 'Yu Mincho', 'MS Mincho', serif;
            overflow: hidden; 
        }

        ::-webkit-scrollbar { width: 6px; height: 6px;}
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { background: #444; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }

        /* === 2. 动态背景层 === */
        #global-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('assets/bg1.jpg'); 
            background-size: cover; background-position: center;
            z-index: -2;
            animation: slowBreath 30s infinite alternate;
        }
        @keyframes slowBreath {
            0% { transform: scale(1); }
            100% { transform: scale(1.08); }
        }
        #bg-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to right, rgba(10,10,12,0.95) 0%, rgba(10,10,12,0.6) 40%, rgba(10,10,12,0.3) 100%);
            z-index: -1; pointer-events: none;
        }

        /* === 3. 18+ 警告 (Age Gate) === */
        #age-gate {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: #000; z-index: 99999; /* 确保层级足够高 */
            display: flex; align-items: center; justify-content: center;
            transition: opacity 1s ease;
        }
        .age-gate-box {
            text-align: center; border: 1px solid #333; padding: 60px;
            background: radial-gradient(circle, rgba(30,0,0,0.5) 0%, rgba(0,0,0,1) 100%);
            max-width: 90%; box-sizing: border-box;
        }
        .age-gate-box h1 { color: var(--primary); letter-spacing: 5px; margin-bottom: 20px; font-size: 32px;}
        .age-gate-box p { line-height: 2; margin-bottom: 40px; color: #ccc; font-size: 15px;}
        .btn-group-age { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
        .age-gate-box button {
            background: transparent; color: #fff; border: 1px solid #555;
            padding: 10px 40px; font-family: inherit; font-size: 16px;
            cursor: pointer; transition: 0.3s;
        }
        .age-gate-box button:hover { border-color: var(--primary); background: rgba(201, 23, 30, 0.2); }

        /* === 4. 侧边栏与认证中心UI === */
        #sidebar {
            position: fixed; top: 0; left: 0; width: 280px; height: 100%;
            background: rgba(10, 10, 12, 0.85); backdrop-filter: blur(10px);
            border-right: 1px solid var(--border-color);
            z-index: 100; display: flex; flex-direction: column;
        }
        .logo-area { padding: 50px 0; text-align: center; border-bottom: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center;}
        .logo-area h1 { margin: 0; font-size: 36px; font-weight: 900; letter-spacing: 2px;}
        .logo-area h1 span { color: var(--primary); }
        .logo-area p { margin: 10px 0 0; font-size: 12px; color: var(--text-muted); letter-spacing: 3px; }
        .mobile-user-icon { display: none; cursor: pointer; color: var(--text-muted); }

        .nav-menu { flex: 1; display: flex; flex-direction: column; padding: 40px 0; }
        .nav-item {
            padding: 15px 40px; color: var(--text-muted); text-decoration: none;
            font-size: 18px; letter-spacing: 3px; position: relative;
            transition: 0.3s; display: flex; align-items: baseline;
        }
        .nav-item span.en { font-size: 12px; opacity: 0.5; margin-left: 10px; font-family: sans-serif; }
        .nav-item:hover, .nav-item.active { color: #fff; background: rgba(255,255,255,0.05); padding-left: 50px;}
        .nav-item.active::before {
            content: ''; position: absolute; left: 0; top: 0;
            width: 4px; height: 100%; background: var(--primary);
        }

        .sidebar-footer { padding: 30px; text-align: center; border-top: 1px solid var(--border-color); }
        
        .user-profile-module {
            display: flex; align-items: center; gap: 12px; padding: 12px 15px;
            background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px; margin-bottom: 10px; transition: 0.3s ease;
        }
        .user-profile-module:hover { background: rgba(201, 23, 30, 0.05); border-color: var(--primary); }
        .avatar-circle {
            width: 32px; height: 32px; background: #151518; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; border: 1px solid #222;
        }
        .user-auth-links { font-size: 14px; font-weight: 500; }
        .auth-btn { color: #aaa; text-decoration: none; transition: 0.2s; cursor: pointer; }
        .auth-btn:hover { color: #fff; text-shadow: 0 0 10px var(--primary); }
        .login-btn { color: var(--text-main); }
        .auth-sep { color: #333; margin: 0 2px; }

        /* === 5. 登录/注册 弹窗 UI === */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px); z-index: 200000;
            display: none; align-items: center; justify-content: center;
            opacity: 0; transition: 0.3s ease;
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content {
            background: #0f0f11; width: 100%; max-width: 400px;
            border: 1px solid rgba(201, 23, 30, 0.3); border-radius: 12px;
            padding: 30px; box-shadow: 0 20px 60px rgba(0,0,0,1);
            transform: translateY(20px); transition: 0.3s;
        }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .modal-tabs { display: flex; gap: 20px; }
        .tab-btn { background: none; border: none; color: #666; font-size: 18px; cursor: pointer; padding-bottom: 5px; transition: 0.2s; }
        .tab-btn.active { color: var(--primary); border-bottom: 2px solid var(--primary); font-weight: bold; }
        .close-modal { color: #555; font-size: 28px; cursor: pointer; transition: 0.3s; }
        .close-modal:hover { color: #fff; }
        .auth-panel { display: none; }
        .auth-panel.active { display: block; }
        .input-group { margin-bottom: 15px; }
        .input-group input {
            width: 100%; background: #1a1a1c; border: 1px solid #333;
            padding: 12px; color: #fff; border-radius: 6px; box-sizing: border-box; font-family: inherit;
        }
        .input-group input:focus { border-color: var(--primary); outline: none; }
        .submit-btn {
            width: 100%; background: var(--primary); color: #fff; border: none;
            padding: 12px; border-radius: 6px; cursor: pointer; margin-top: 10px;
            font-weight: bold; transition: 0.3s;
        }
        .submit-btn:hover { filter: brightness(1.2); box-shadow: 0 0 15px rgba(201, 23, 30, 0.4); }
        .avatar-label {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px; background: #151518; border: 1px dashed #444;
            border-radius: 6px; cursor: pointer; font-size: 13px; color: #888; transition: 0.3s;
        }
        .avatar-label:hover { border-color: var(--primary); color: #fff; }

        /* === 6. 右侧内容区及组件样式 === */
        #main-content {
            margin-left: 280px; height: 100vh; overflow-y: auto; overflow-x: hidden;
            position: relative; scroll-behavior: smooth; display: flex; flex-direction: column;
        }
        .view-container { flex: 1; width: 100%; opacity: 1; transition: opacity 0.4s ease; }
        .view-container.fade { opacity: 0; }

        .hero-video { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; pointer-events: none; }
        .hero-webp { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; pointer-events: none; }
        .mobile-only { display: none; }
        .pc-only { display: block; }
        .hero-video-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); z-index: 1; pointer-events: none; }
        .hero-title, .hero-subtitle, .scroll-down { z-index: 2; position: relative; }
        
        .hero-section { min-height: 100vh; display: flex; flex-direction: column; justify-content: flex-end; padding: 100px; box-sizing: border-box; position: relative; z-index: 1; overflow: hidden; }
        .hero-title { font-size: 4rem; text-shadow: 2px 4px 10px rgba(0,0,0,0.8); margin: 0; line-height: 1.2;}
        .hero-subtitle { font-size: 1.5rem; color: #bbb; margin-top: 10px; letter-spacing: 5px; }
        .scroll-down { position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); animation: bounce 2s infinite; color: #fff; font-size: 12px; letter-spacing: 2px; text-align: center; }
        .scroll-down::after { content: '↓'; display: block; font-size: 24px; margin-top: 5px; }
        @keyframes bounce { 0%, 20%, 50%, 80%, 100% { transform: translateY(0) translateX(-50%); } 40% { transform: translateY(-15px) translateX(-50%); } 60% { transform: translateY(-7px) translateX(-50%); } }

        .content-section { padding: 80px 100px; background: rgba(0,0,0,0.7); border-top: 1px solid var(--border-color); }
        .section-title { font-size: 32px; color: var(--primary); margin-bottom: 40px; display: flex; align-items: baseline; border-bottom: 1px solid #333; padding-bottom: 15px; }
        .section-title span { font-size: 16px; color: #666; margin-left: 15px; font-family: sans-serif; letter-spacing: 2px;}

        /* 各大模块卡片样式 */
        .news-list { list-style: none; padding: 0; margin: 0; }
        .news-item { display: flex; padding: 15px 0; border-bottom: 1px dashed #333; transition: 0.3s; }
        .news-item:hover { background: rgba(255,255,255,0.05); padding-left: 10px; }
        .news-date { width: 120px; color: var(--primary); font-family: sans-serif; }
        .news-tag { background: #222; padding: 2px 10px; font-size: 12px; margin-right: 15px; border: 1px solid #444; }
        .news-title { color: #ddd; cursor: pointer; flex: 1;}
        .news-title:hover { color: #fff; text-decoration: underline; }

        .project-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .project-card { background: rgba(20,20,25,0.8); border: 1px solid #333; overflow: hidden; cursor: pointer; transition: 0.4s; position: relative; }
        .project-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: 0 10px 30px rgba(201, 23, 30, 0.2); }
        .project-img { width: 100%; height: 250px; background-size: cover; background-position: center; border-bottom: 2px solid var(--primary); }
        .project-info { padding: 30px; }
        .project-info h3 { margin: 0 0 10px 0; font-size: 24px; }
        .project-info p { color: var(--text-muted); font-size: 14px; line-height: 1.6; }
        .status-badge { position: absolute; top: 15px; right: 15px; z-index: 10; padding: 4px 10px; font-size: 12px; border-radius: 4px; font-weight: bold; }
        .status-ended { background: #555; }
        .special-event-card { display: flex; border: 1px solid #333; background: rgba(20,20,25,0.8); }
        .special-event-card .project-img { width: 50%; height: auto; min-height: 300px; border-bottom: none; border-right: 2px solid var(--primary); }
        .special-event-card .project-info { width: 50%; display: flex; flex-direction: column; justify-content: center; padding: 40px; box-sizing: border-box;}

        .cast-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .cast-card { display: flex; align-items: center; gap: 20px; background: rgba(20, 20, 25, 0.6); border: 1px solid #333; border-radius: 12px; padding: 20px; transition: 0.3s; }
        .cast-card:hover { background: rgba(30, 30, 35, 0.8); border-color: var(--primary); transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.4); }
        .cast-avatar { width: 80px; height: 80px; border-radius: 50%; background-size: cover; background-position: center; border: 2px solid var(--primary); box-shadow: 0 0 15px rgba(229, 9, 20, 0.2); flex-shrink: 0; }
        .cast-info { flex: 1; }
        .cast-name { font-size: 20px; color: #fff; margin: 0 0 5px 0; font-weight: bold; }
        .cast-role { font-size: 13px; color: var(--primary); margin: 0 0 8px 0; letter-spacing: 1px; }
        .cast-desc { font-size: 13px; color: #aaa; line-height: 1.5; }

        .archive-list { display: flex; flex-direction: column; gap: 20px; }
        .archive-item { display: flex; background: rgba(20,20,25,0.6); border: 1px solid #333; border-radius: 8px; overflow: hidden; transition: 0.3s; cursor: pointer; }
        .archive-item:hover { background: rgba(30,30,35,0.8); border-color: var(--primary); transform: translateX(10px); }
        .archive-cover { width: 240px; min-width: 240px; height: 150px; background-size: cover; background-position: center; border-right: 1px solid #333; }
        .archive-content { padding: 25px; display: flex; flex-direction: column; justify-content: space-between; flex: 1; }
        .archive-title { font-size: 20px; color: #fff; margin: 0 0 10px 0; }
        .archive-summary { font-size: 14px; color: var(--text-muted); line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .archive-meta { font-size: 12px; color: #666; font-family: sans-serif; display: flex; gap: 15px; margin-top: 15px; }

        .worldview-hero { width: 100%; height: 300px; background: rgba(20, 20, 25, 0.8); border: 1px dashed #555; border-radius: 12px; margin-bottom: 40px; display: flex; align-items: center; justify-content: center; background-size: cover; background-position: center; }
        .worldview-quote { font-size: 18px; color: var(--primary); font-style: italic; border-left: 4px solid var(--primary); padding: 20px; margin-bottom: 40px; line-height: 1.6; background: linear-gradient(90deg, rgba(255,255,255,0.05) 0%, transparent 100%); border-radius: 0 8px 8px 0; }
        .worldview-subtitle { font-size: 22px; color: #fff; margin: 50px 0 20px 0; display: flex; align-items: center; gap: 10px; }
        .worldview-subtitle::before { content: ''; display: block; width: 6px; height: 22px; background: var(--primary); border-radius: 3px; }
        .worldview-text p { font-size: 15px; color: #bbb; line-height: 2.2; margin-bottom: 20px; text-align: justify; }
        
        .extra-filter { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 30px; }
        .filter-btn { background: rgba(255, 255, 255, 0.05); border: 1px solid #333; color: #aaa; padding: 6px 15px; border-radius: 20px; font-size: 13px; cursor: pointer; transition: 0.3s; }
        .filter-btn:hover, .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .extra-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
        .extra-card { background: rgba(20, 20, 25, 0.6); border: 1px solid #333; border-radius: 8px; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .extra-card:hover { background: rgba(30, 30, 35, 0.8); border-color: var(--primary); transform: translateY(-3px); }
        .extra-card-left { display: flex; flex-direction: column; gap: 8px; }
        .extra-title { color: #fff; font-size: 16px; font-weight: bold; display: flex; align-items: center; gap: 8px; }
        .extra-tags { display: flex; flex-wrap: wrap; gap: 5px; }
        .extra-tag { background: rgba(255,255,255,0.1); color: #ccc; font-size: 11px; padding: 2px 8px; border-radius: 4px; }
        .extra-arrow { color: #555; font-size: 18px; transition: 0.3s; }
        .extra-card:hover .extra-arrow { color: var(--primary); transform: translateX(3px); }

        .global-footer { background: #050505; border-top: 1px solid #222; padding: 30px 20px; text-align: center; font-size: 12px; color: #666; width: 100%; box-sizing: border-box; }
        .friendly-links { margin-bottom: 15px; }
        .friendly-links .link-label { color: #888; margin-right: 10px; }
        .friendly-links a { color: #888; text-decoration: none; margin: 0 5px; transition: 0.3s; }
        .friendly-links a:hover { color: var(--primary); }
        .copyright { color: #444; }

        /* === 7. 手机端响应式适配 === */
        @media (max-width: 850px) {
            .pc-only { display: none !important; }
            .mobile-only { display: block !important; }
            #global-bg { animation: none !important; }
            .hero-webp { transform: translateZ(0); will-change: transform; backface-visibility: hidden; }
            #bg-overlay { background: rgba(10, 10, 12, 0.8) !important; }
            
            #sidebar { width: 100%; height: 60px; flex-direction: row; align-items: center; justify-content: space-between; border-right: none; border-bottom: 1px solid var(--border-color); padding: 0 15px; box-sizing: border-box; }
            .logo-area { padding: 0; border: none; flex-direction: row; gap: 15px; }
            .logo-area h1 { font-size: 20px; }
            .logo-area p { display: none; }
            
            .mobile-user-icon { display: flex; padding: 5px; background: rgba(255,255,255,0.05); border-radius: 50%; }
            .sidebar-footer { display: none; } /* 隐藏底部的面板 */

            .nav-menu { flex-direction: row; padding: 0; margin-left: auto; flex: unset; overflow-x: auto; overflow-y: hidden; white-space: nowrap; -ms-overflow-style: none; scrollbar-width: none; }
            .nav-menu::-webkit-scrollbar { display: none; }
            .nav-item { padding: 0 10px; font-size: 14px; height: 60px; align-items: center; border-bottom: 2px solid transparent; }
            .nav-item.active::before { display: none; }
            .nav-item.active { border-bottom-color: var(--primary); background: transparent; }
            .nav-item:hover { padding-left: 10px; background: transparent; color: var(--primary); }
            .nav-item span.en { display: none; }

            #main-content { margin-left: 0; padding-top: 60px; }
            .hero-section { padding: 40px 20px; text-align: center; align-items: center; justify-content: center; }
            .hero-title { font-size: 2.2rem; }
            .hero-subtitle { font-size: 1rem; }
            .content-section { padding: 40px 20px; }
            .section-title { font-size: 24px; flex-direction: column; align-items: flex-start;}
            .section-title span { margin-left: 0; margin-top: 5px; font-size: 12px; }

            .news-item { flex-direction: column; align-items: flex-start; }
            .project-grid { grid-template-columns: 1fr; gap: 20px; }
            .special-event-card { flex-direction: column; }
            .special-event-card .project-img { width: 100%; height: 200px; min-height: unset; border-right: none; border-bottom: 2px solid var(--primary); }
            .special-event-card .project-info { width: 100%; padding: 25px; }
            
            .cast-card { flex-direction: column; text-align: center; }
            .archive-item { flex-direction: column; }
            .archive-cover { width: 100%; height: 180px; border-right: none; border-bottom: 1px solid #333; }
            
            .global-footer { padding: 25px 15px; }
            .friendly-links { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px; }
            .friendly-links .link-label { width: 100%; text-align: center; margin: 0 0 8px 0; font-size: 12px; }
            .friendly-links a { background: rgba(255,255,255,0.05); border: 1px solid #333; padding: 4px 10px; border-radius: 4px; font-size: 11px; margin: 0; }
        }
    </style>
</head>
<body>

    <?php include_once "player.php"; ?>

    <div id="auth-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-tabs">
                    <button class="tab-btn active" onclick="switchAuthTab('login')">登录</button>
                    <button class="tab-btn" onclick="switchAuthTab('register')">申请入社</button>
                </div>
                <span class="close-modal" onclick="closeAuthModal()">&times;</span>
            </div>
            
            <div id="login-panel" class="auth-panel active">
                <div class="input-group">
                    <input type="text" placeholder="学号 / 昵称 / Email" required>
                </div>
                <div class="input-group">
                    <input type="password" placeholder="密码" required>
                </div>
                <button class="submit-btn" onclick="handleLogin()">立即进入</button>
            </div>

            <div id="register-panel" class="auth-panel">
                <div class="input-group">
                    <input type="text" id="reg-username" placeholder="用户名 (用于登录的ID)" required>
                </div>
                <div class="input-group">
                    <input type="text" id="reg-nickname" placeholder="昵称 (显示在页面的名字)" required>
                </div>
                <div class="input-group">
                    <input type="email" id="reg-email" placeholder="电子邮箱" required>
                </div>
                <div class="input-group">
                    <input type="password" id="reg-password" placeholder="设置密码" required>
                </div>
                <div class="input-group">
                    <input type="password" id="reg-confirm" placeholder="确认密码" required>
                </div>
                <div class="input-group avatar-upload">
                    <label for="avatar-file" class="avatar-label">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        上传个人头像
                    </label>
                    <input type="file" id="avatar-file" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                    <div id="avatar-preview-name" style="font-size: 11px; color: #666; margin-top: 5px;"></div>
                </div>
                <button class="submit-btn" onclick="handleRegister()">注 册</button>
            </div>
        </div>
    </div>
    
    <div id="global-bg"></div>
    <div id="bg-overlay"></div>

    <div id="age-gate">
        <div class="age-gate-box">
            <h1>WARNING</h1>
            <p>本网站包含视觉小说与 Galgame 相关的深度资讯。<br>
               <strong>「あなたは18歳以上ですか？」</strong></p>
            <div class="btn-group-age">
                <button id="btn-yes">YES / はい</button>
                <button id="btn-no">NO / いいえ</button>
            </div>
        </div>
    </div>

    <nav id="sidebar">
        <div class="logo-area">
            <h1>Zuel<span>Gal</span>.</h1>
            <div class="mobile-user-icon" onclick="<?php echo isset($_SESSION['user_id']) ? "alert('欢迎回来，{$_SESSION['nickname']}！')" : "openAuthModal('login')"; ?>">
                <svg viewBox="0 0 24 24" width="22" height="22" style="<?php echo isset($_SESSION['user_id']) ? 'color: var(--primary);' : ''; ?>" stroke="currentColor" stroke-width="2" fill="none">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <p>中南大视觉小说社</p>
        </div>
        
        <div class="nav-menu">
            <a href="#" class="nav-item active" data-view="home">入口 <span class="en">TOP</span></a>
            <a href="#" class="nav-item" data-view="worldview">世界观 <span class="en">WORLDVIEW</span></a>
            <a href="#" class="nav-item" data-view="cast">登场人物 <span class="en">CHARACTER</span></a>
            <a href="#" class="nav-item" data-view="projects">相关企划 <span class="en">PROJECTS</span></a>
            <a href="#" class="nav-item" data-view="warehouse">资料仓库 <span class="en">ARCHIVE</span></a>
            <a href="#" class="nav-item" data-view="extra">？<span class="en">EXTRA</span></a>
        </div>

        <div class="sidebar-footer">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="user-profile-module">
                    <div class="user-avatar-wrap">
                        <div class="avatar-circle" style="border-color: var(--primary); overflow: hidden;">
                            <?php if(!empty($_SESSION['avatar']) && $_SESSION['avatar'] !== '/assets/default-avatar.png'): ?>
                                <img src="<?php echo $_SESSION['avatar']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <span style="color: var(--primary); font-weight: bold;">
                                    <?php echo mb_substr($_SESSION['nickname'], 0, 1, 'utf-8'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="user-auth-links">
                        <span class="user-name" style="color: #fff; margin-right: 5px;"><?php echo $_SESSION['nickname']; ?></span>
                        <a href="logout.php" class="auth-btn" style="font-size: 10px; opacity: 0.5;">[退出]</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="user-profile-module">
                    <div class="user-avatar-wrap">
                        <div class="avatar-circle">
                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="#888" stroke-width="2" fill="none"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </div>
                    </div>
                    <div class="user-auth-links">
                        <a class="auth-btn login-btn">登录</a>
                        <span class="auth-sep">/</span>
                        <a class="auth-btn reg-btn">注册</a>
                    </div>
                </div>
            <?php endif; ?>
            <div style="margin-top: 15px; font-size: 10px; color: #444; letter-spacing: 1px;">&copy; 2026 ZUELGAL.</div>
        </div>
    </nav>

    <main id="main-content">
        <div id="pjax-container">
            <div id="view-container" class="view-container"></div>
        </div>

        <footer class="global-footer">
            <div class="friendly-links">
                <span class="link-label">友情链接</span>
                <a href="https://space.bilibili.com/20942465?spm_id_from=333.337.search-card.all.click" target="_blank">武大漫协</a>
                <a href="https://space.bilibili.com/382680998?spm_id_from=333.337.0.0" target="_blank">风蓝动漫社</a>
                <a href="https://space.bilibili.com/10284760?spm_id_from=333.337.0.0" target="_blank">东西动漫社</a>
            </div>
            <div class="copyright">&copy; 2026 ZuelGal. All rights reserved. 穗织ICP备0721号</div>
        </footer>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/pjax/pjax.js"></script>
    <script>
        // === 0. PJAX 初始化 ===
        var pjax = new Pjax({
            elements: "a[href^='/'], a[href^='http']:not([target='_blank'])",
            selectors: ["title", "#pjax-container"],
            cacheBust: false
        });

        document.addEventListener('pjax:send', function() {
            document.getElementById('pjax-container').style.opacity = '0.3';
        });
        document.addEventListener('pjax:complete', function() {
            document.getElementById('pjax-container').style.opacity = '1';
        });

        // === 1. Age Gate 控制与播放器触发 ===
        const ageGate = document.getElementById('age-gate');

        if(sessionStorage.getItem('verified')) {
            ageGate.style.display = 'none';
        }

        const btnYes = document.getElementById('btn-yes');
        if (btnYes) {
            btnYes.addEventListener('click', () => {
                sessionStorage.setItem('verified', 'true');
                ageGate.style.opacity = '0';
                setTimeout(() => ageGate.style.display = 'none', 1000);
                
                const mjs = document.querySelector('meting-js');
                if (mjs && mjs.aplayer) mjs.aplayer.play();
            });
        }

        const btnNo = document.getElementById('btn-no');
        if (btnNo) {
            btnNo.addEventListener('click', () => {
                window.location.href = "https://www.bilibili.com/video/BV1Ba41zGEdF";
            });
        }

        // === 2. 弹窗控制逻辑 ===
        const authModal = document.getElementById('auth-modal');

        function openAuthModal(mode = 'login') {
            authModal.classList.add('active');
            switchAuthTab(mode);
        }

        function closeAuthModal() {
            authModal.classList.remove('active');
        }

        function switchAuthTab(mode) {
            const loginBtn = document.querySelector('.tab-btn:nth-child(1)');
            const regBtn = document.querySelector('.tab-btn:nth-child(2)');
            const loginPanel = document.getElementById('login-panel');
            const regPanel = document.getElementById('register-panel');

            if (mode === 'login') {
                loginBtn.classList.add('active'); regBtn.classList.remove('active');
                loginPanel.classList.add('active'); regPanel.classList.remove('active');
            } else {
                regBtn.classList.add('active'); loginBtn.classList.remove('active');
                regPanel.classList.add('active'); loginPanel.classList.remove('active');
            }
        }

        authModal.addEventListener('click', (e) => {
            if (e.target === authModal) closeAuthModal();
        });

        document.addEventListener('DOMContentLoaded', () => {
            const loginLink = document.querySelector('.login-btn');
            const regLink = document.querySelector('.reg-btn');
            if(loginLink) loginLink.onclick = () => openAuthModal('login');
            if(regLink) regLink.onclick = () => openAuthModal('register');
        });

        // === 2.5 真正的后端对接逻辑 ===
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                document.getElementById('avatar-preview-name').textContent = "已选择: " + input.files[0].name;
            }
        }

        async function handleRegister() {
            const username = document.getElementById('reg-username').value;
            const nickname = document.getElementById('reg-nickname').value;
            const email = document.getElementById('reg-email').value;
            const password = document.getElementById('reg-password').value;
            const confirm = document.getElementById('reg-confirm').value;
            const avatarFile = document.getElementById('avatar-file').files[0]; 

            if (password !== confirm) return alert('两次输入的密码不一致！');
            if (!username || !nickname || !email || !password) return alert('请填写完整信息');

            const formData = new FormData();
            formData.append('username', username);
            formData.append('nickname', nickname);
            formData.append('email', email);
            formData.append('password', password);
            if (avatarFile) {
                formData.append('avatar', avatarFile); 
            }

            try {
                const response = await fetch('auth_handler.php?action=register', {
                    method: 'POST',
                    body: formData 
                });
                const result = await response.json();
                alert(result.message);
                if(result.success) switchAuthTab('login');
            } catch (err) {
                alert('注册失败，请检查 PHP 文件上传配置');
            }
        }

        async function handleLogin() {
            const accountInput = document.querySelector('#login-panel input[type="text"]');
            const passwordInput = document.querySelector('#login-panel input[type="password"]');

            if (!accountInput.value || !passwordInput.value) return alert('请输入账号和密码');

            const formData = new FormData();
            formData.append('account', accountInput.value);
            formData.append('password', passwordInput.value);

            try {
                const response = await fetch('auth_handler.php?action=login', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    location.reload(); 
                } else {
                    alert(result.message);
                }
            } catch (err) {
                console.error(err);
                alert('登录请求失败');
            }
        }

        // === 3. 数据中心 ===
        const projectsData = [
            {
                status: "MAINTENANCE 架构升级中",
                statusClass: "status-ended",
                img: "/assets/backup/sora01.png", 
                imgStyle: "filter: brightness(0.5);",
                title: "十二菜器：年度作品评选",
                desc: "基于全新 YmGal API 构建。从浩瀚的档案库中，选出你心目中的十二部神作。（当前区块正在进行视觉重构，访问路径暂已冻结）",
                link: "javascript:alert('【系统拦截】\\n十二菜器企划正在进行架构升级与页面重构。\\n访问权限暂暂时封锁');"
            },
            {
                status: "ARCHIVE 已结束",
                statusClass: "status-ended", 
                img: "/assets/bg1.jpg",
                imgStyle: "filter: grayscale(50%);", 
                title: "第一届角色人气总选举",
                desc: "2025年秋季特别活动。检索 VNDB 数据库，为你最爱的女性角色投上神圣的一票。",
                link: "/events/character_vote/index.php"
            }
        ];

        const renderProjectCards = () => {
            return projectsData.map(item => `
                <div class="project-card" onclick="window.location.href='${item.link}'">
                    <div class="status-badge ${item.statusClass}">${item.status}</div>
                    <div class="project-img" style="background-image: url('${item.img}'); ${item.imgStyle}"></div>
                    <div class="project-info">
                        <h3>${item.title}</h3>
                        <p>${item.desc}</p>
                    </div>
                </div>
            `).join('');
        };

        const archiveData = [
            {
                cover: "https://assets-1316693082.cos.ap-nanjing.myqcloud.com/assets/new_easter_egg.jpg",
                title: "【VNFES-视觉小说的今后和将来企划】中南财经政法大学NTR交牛同好会历史",
                summary: "丛雨为什么要保留自己在猪栏菜专当看板娘的历史？那是因为...",
                date: "2025-12-25",
                author: "ZuelGal 编辑部",
                link: "/articles/index.html?id=001"
            },
            {
                cover: "",
                title: "【推荐】试了Project: Zuelphoria的demo，我必须说点什么",
                summary: "上周群主在群里发了Project:Zuelphoria的demo，让我们几个帮忙测试。说实话本来没抱太高期望，同人社团做游戏嘛，能有个七八分像样就不错了。但打开之后玩了四十多分钟，我愣是没缓过神。妈呀，这完成度也太高了...",
                date: "2018-09-15",
                author: "211.69.161.30",
                link: "/kwaidan.php"
            },
        ];

        const renderArchiveList = () => {
            return archiveData.map(item => `
                <div class="archive-item" onclick="window.location.href='${item.link}'">
                    <div class="archive-cover" style="background-image: url('${item.cover}');"></div>
                    <div class="archive-content">
                        <div>
                            <h3 class="archive-title">${item.title}</h3>
                            <div class="archive-summary">${item.summary}</div>
                        </div>
                        <div class="archive-meta">
                            <span>📅 ${item.date}</span>
                            <span>✍️ ${item.author}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        };

        const extraData = [
            { title: "欢迎来到特调局", url: "https://youhiya.itch.io/welcome-to-sib", tags: ["摸鱼", "ARG"], emoji: "📁" },
            { title: "榕树信箱", url: "https://roastfish042.neocities.org/tree", tags: ["摸鱼", "ARG"], emoji: "📁" },
            { title: "缓存溢出", url: "http://www.unizhang.icu", tags: ["摸鱼", "ARG"], emoji: "📁" },
            { title: "圣希尔达的回响", url: "https://eecdpq5oxzo5.space.minimaxi.com", tags: ["摸鱼", "ARG"], emoji: "📁" },
            { title: "三川米粉店", url: "http://www.berrysignalscmfd.top", tags: ["摸鱼", "ARG"], emoji: "🍜" },
            { title: "保亚保健有限公司", url: "https://wanwuyongyuan.github.io/baoya", tags: ["摸鱼", "ARG"], emoji: "💊" },
            { title: "永安温泉度假酒店", url: "https://yongan0504.rth2.xyz", tags: ["摸鱼", "ARG"], emoji: "♨️" },
            { title: "大学生登山失踪事件", url: "https://missing.shiroki-y.top/introduction.html", tags: ["摸鱼", "ARG"], emoji: "⛰️" },
            { title: "逃离宏业电子厂", url: "https://www.escapefromhongye.xyz/super-broccoli", tags: ["摸鱼", "ARG"], emoji: "🏭" },
            { title: "民间旮旯ADV", url: "https://humihumi.com/", tags: ["摸鱼", "ADV"], emoji: "📖" },
            { title: "地铁抢座大作战", url: "https://www.escapefromhongye.xyz/hub111", tags: ["摸鱼", "SLG"], emoji: "🚇" },
            { title: "青椒模拟器", url: "https://tenure-plus.feedscription.com/?mode=local&lang=en", tags: ["摸鱼", "SLG"], emoji: "🏫" },
            { title: "读博模拟器", url: "https://research.wmz.ninja/projects/phd/index.zh-hans.html", tags: ["摸鱼", "SLG"], emoji: "🎓" },
            { title: "互联网大厂模拟器", url: "https://www.bigtech-simulator.com/", tags: ["摸鱼", "SLG"], emoji: "🏢" },
            { title: "鳌太线模拟器", url: "https://cyberhiking.com/", tags: ["摸鱼", "SLG"], emoji: "🏕️" },
            { title: "数回", url: "https://cn.puzzle-loop.com/", tags: ["天才", "puzzle"], emoji: "🧠" },
            { title: "数邻", url: "https://cn.puzzle-dominosa.com/", tags: ["天才", "puzzle"], emoji: "🧠" },
            { title: "点灯", url: "https://cn.puzzle-light-up.com/", tags: ["天才", "puzzle"], emoji: "💡" },
            { title: "帐篷", url: "https://cn.puzzle-light-up.com/", tags: ["天才", "puzzle"], emoji: "⛺" },
            { title: "扫雷", url: "https://cn.puzzle-minesweeper.com/minesweeper-5x5-easy/", tags: ["庸才", "puzzle"], emoji: "💣" },
            { title: "CYMath", url: "https://www.cymath.com/cn/", tags: ["做题", "PC"], emoji: "📐" },
            { title: "刷单词", url: "https://qwerty.kaiyi.cool/", tags: ["做题", "PC"], emoji: "🔤" },
            { title: "游戏九宫格", url: "https://my9.shatranj.space/game", tags: ["摸鱼"], emoji: "🎮" },
            { title: "猜盐填番", url: "https://xiaoce.fun/fillanime", tags: ["摸鱼"], emoji: "📺" },
            { title: "猜盐辩论", url: "https://xiaoce.fun/debate", tags: ["摸鱼"], emoji: "🗣️" },
            { title: "猜角色", url: "https://xiaoce.fun/guesscute", tags: ["摸鱼"], emoji: "👤" },
            { title: "豚豚师兄的复习资料", url: "https://pan.baidu.com/s/1kF6zn-8kAVLFALOgpPbDSA?pwd=1n2a", tags: ["做题","天才"], emoji: "🤔" }
        ];

        const renderExtraCards = (data) => {
            if(data.length === 0) return `<div style="color:#aaa;">此分类下暂无档案...</div>`;
            return data.map(item => `
                <a href="${item.url}" target="_blank" class="extra-card">
                    <div class="extra-card-left">
                        <div class="extra-title">${item.emoji} ${item.title}</div>
                        <div class="extra-tags">
                            ${item.tags.map(t => `<span class="extra-tag">${t}</span>`).join('')}
                        </div>
                    </div>
                    <div class="extra-arrow">➔</div>
                </a>
            `).join('');
        };

        window.filterExtra = function(selectedTag) {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                if(btn.getAttribute('data-tag') === selectedTag) btn.classList.add('active');
                else btn.classList.remove('active');
            });
            const filteredData = selectedTag === 'all' 
                ? extraData 
                : extraData.filter(item => item.tags.includes(selectedTag));
            document.getElementById('extra-container').innerHTML = renderExtraCards(filteredData);
        };

        const castData = [
            { name: "白羽 Shiroha", role: "Robot", avatar: "/assets/profile/shiroha.jpg", desc: "神秘的、由外部人士部署的QQ机器人，本群由盛转衰的标志，只在周末上线。" },
            { name: "丛雨 Murasame", role: "Robot/前·看板娘", avatar: "/assets/profile/murasame.jpg", desc: "时代的眼泪，从风光无限到被无情抛弃只过了不到一年。" },
            { name: "blur  ", role: "群主/创始人", avatar: "/assets/profile/blur.jpg", desc: "神秘的20级财税师兄，没人知道为什么他能在2014年创建本群。" },
            { name: "神明 Kamisama ", role: "???", avatar: "/assets/profile/yinuo.png", desc: "不愿意透露姓名的神明，与后面的妖魔鬼怪划清界限，不同其沆瀣一气。" },
            { name: "Xianer  ", role: "管理/傀儡", avatar: "/assets/profile/xianer.jpg", desc: "绝望地看着一切崩坏，只能靠后人的智慧了，你群未来怎么样只有天知道..." },
            { name: "无涯  ", role: "", avatar: "/assets/profile/wuya.jpg", desc: "已毕业土木国gal师兄｜三次元现充GAL男主" },
            { name: "顽皮汪汪  ", role: "", avatar: "/assets/profile/wanpiwangwang.jpg", desc: "ZUEL第二神人｜悬疑猎奇向受益者|已成功以坏结局通关现实高恋" },
            { name: "rumpen  ", role: "", avatar: "/assets/profile/rumpen.jpg", desc: "《小孩姐要考中南财经政法大学！》第一作者|木柜子痴|武昌土著 |江夏留子|" },
            { name: "和田  ", role: "", avatar: "/assets/profile/hetian.jpg", desc: "insult爱好者" }
        ];

        const renderCastCards = () => {
            return castData.map(item => `
                <div class="cast-card">
                    <div class="cast-avatar" style="background-image: url('${item.avatar}');"></div>
                    <div class="cast-info">
                        <h3 class="cast-name">${item.name}</h3>
                        <div class="cast-role">${item.role}</div>
                        <div class="cast-desc">${item.desc}</div>
                    </div>
                </div>
            `).join('');
        };

        // === 4. SPA 视图模板 ===
        const views = {
            home: `
            <div class="hero-section">
                <video class="hero-video pc-only" id="pc-video" autoplay loop muted playsinline>
                    <source data-src="/assets/hero-pc.mp4" type="video/mp4">
                </video>
                <img class="hero-webp mobile-only" src="/assets/hero-mobile-static.jpg" alt="静态背景" style="z-index: 0;">
                <img class="hero-webp mobile-only" src="/assets/hero-mobile.webp" alt="动态背景" style="z-index: 1;">
                <div class="hero-video-overlay" style="z-index: 2;"></div> 
                <h2 class="hero-title" style="z-index: 3; position: relative;">在这虚构的故事中，<br>寻找真实的对象。</h2>
                <div class="hero-subtitle" style="z-index: 3; position: relative;">ZuelGal / 中南大视觉小说社官方入口</div>
                <div class="scroll-down" style="z-index: 3;">SCROLL DOWN</div>
            </div>
            <div class="content-section">
                <h2 class="section-title">最新情报 <span>UPDATE & NEWS</span></h2>
                <ul class="news-list">
                    <li class="news-item"><div class="news-date">2026.03.09</div><div class="news-tag">活动</div><div class="news-title">「十二菜器」年度作品大赏即将开启。</div></li>
                    <li class="news-item"><div class="news-date">2026.02.26</div><div class="news-tag">系统</div><div class="news-title">官方网站 v2.0 全新架构上线，支持移动端自适应。</div></li>
                </ul>
            </div>
            <div class="content-section" style="padding-bottom: 80px;">
                <h2 class="section-title">焦点企划 <span>SPECIAL EVENT</span></h2>
                <div class="special-event-card">
                    <div class="project-img" style="background-image: url('/assets/subarashi01.png');"></div>
                    <div class="project-info">
                        <span style="color: var(--primary); font-weight: bold; margin-bottom: 10px; font-size: 12px; letter-spacing: 2px;">PREPARING...</span>
                        <h3 style="font-size: 32px; margin-bottom: 20px; line-height: 1.3;">十二菜器<br>YmGal 联库企划</h3>
                        <p>这是一场属于现充Zueler的狂欢。平台接入月幕 Gal 数据库，开启全新的年度神作评选。</p>
                        <button onclick="loadView('projects')" style="margin-top: 30px; background: transparent; border: 1px solid #fff; color: #fff; padding: 10px 20px; width: 150px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--primary)'" onmouseout="this.style.background='transparent'">进入企划枢纽 →</button>
                    </div>
                </div>
            </div>
            `,
            projects: `
                <div class="content-section" style="min-height: 100vh; padding-top: 100px;">
                    <h2 class="section-title">相关企划 <span>PROJECTS & EVENTS</span></h2>
                    <p style="color: #aaa; margin-bottom: 40px; line-height: 2;">记录社团举办的每一次大型互动企划，请选择事件线。</p>
                    <div class="project-grid">
                        ${renderProjectCards()}
                    </div>
                </div>
            `,
            worldview: `
                <div class="content-section" style="min-height: 100vh; padding-top: 100px;">
                    <h2 class="section-title">世界观 <span>WORLDVIEW</span></h2>
                    <div class="worldview-hero" style="background-image: url('/assets/wenbolou.png');"></div>
                    <div class="worldview-quote">
                        “在我们习以为常的日常背后，隐藏着由真实与虚幻编织的非日常边界。”<br>
                    </div>
                    <div class="worldview-text">
                        <h3 class="worldview-subtitle">背景设定</h3>
                        <p>猪栏菜专Galgame同好会似乎成立于2014年秋。<p>
                        <p> 最初只是一群喜欢Galgame的人在QQ上拉了个群，互相分享资源，聊聊刚推完的作品。后来有人提议做个网站，把大家写的游戏推荐、鉴赏杂谈、资料整理放上去，于是网站就这么搭起来了。境外服务器，没挂学校任何部门，纯粹是私人性质的小站。首页放了张丛雨和白羽的图，背景是文波楼，看着还行。听说2017年前后，受《高考恋爱一百天》《三色绘恋》等国产Galgame的启发，群里开始有人提议自己做游戏。当时的管理是15级的师兄，热情很高，拉了几个人组了个小团队，项目代号叫“Project: Zuelronobox”什么的。据说是个悬疑向旮旯game，当年还放出了一个试玩demo，不过目前似乎已经失传，后来项目怎么样了，没人说得清，毕竟现在的群主都是2020年入学的，之前的事情已经无从考证力~</p>
                        <p>这么多年过去，群里的人来来去去。有人毕业了，有人退坑了，偶尔有新成员加进来。网站还开着，但文章专栏里的大部分投稿都被删了，不知道为什么只剩下一篇。注册功能好像也关了，以后看看能不能弄回来吧。</p>

                        <h3 class="worldview-subtitle">同好会成员守则（2015.9修订）</h3>
                        <p>关于入群</p>
                        <p>一、本会采取邀请制入群。新成员需由现有成员推荐，经管理员审核后方可加入。</p>
                        <p>二、入群申请需填写以下信息：昵称、入学年份、常用邮箱、推荐人。信息不全者不予通过。</p>
                        <p>三、如遇以下情况，管理员有权拒绝申请且无需说明理由：申请者填写的入学年份早于2014年；申请者填写的毕业年份晚于当前年份；申请者填写的推荐人不存在于现有成员列表中。</p>
                        <p>关于群聊</p>
                        <p>四、本会主要活动形式为线上讨论。每日0:00至6:00为静默时段，在此期间全员禁言。</p>
                        <p>五、静默时段内如收到任何私聊消息，无论内容为何，均不予回复。次日可向管理员报备。</p>
                        <p>六、每年9月前后，群内可能出现陌生账号发言，内容多为询问“这里有资源吗”或“这里是什么群”。遇此情况，所有成员不得回复。当日群聊将由系统自动开启全员禁言。</p>
                        <p>关于文章专栏</p>
                        <p>七、文章专栏收录成员撰写的游戏推荐、鉴赏杂谈、资料整理。投稿请发送至管理员邮箱，审核通过后由管理员统一发布。</p>
                        <p>八、已发布文章不接受修改或删除。如作者退群或毕业，文章仍保留于专栏中。</p>
                        <p>九、如发现某篇文章的发布时间早于2014年或晚于当前日期，应视为页面显示异常，可尝试刷新页面。如刷新后仍显示异常，应关闭页面。</p>
                        <p>十、文章专栏不设评论区，不统计访问量。如页面下方出现任何留言或数字，应视为页面显示错误，忽略即可。</p>
                        <p>关于线下</p>
                        <p>十一、本会原则上不组织线下活动。如确有需要，需提前一周在群内公告。活动地点仅限于校内公共场所。</p>
                        <p>十二、如在校内遇见自称是本会成员的人邀请单独见面，无论对方是否认识，均应拒绝并立即离开现场。事后可向管理员核实。</p>
                        <p>关于退群</p>
                        <p>十三、成员退群自由。退群前请手动删除所有聊天记录。若有其余紧急事项，请联系管理员。</p>
                        <p>附则</p>
                        <p>十四、本守则每年修订一次，修订版发布则旧版守则自动废止。</p>
                        <p>十五、如有疑问，可在群内提出。如无人回应，说明没有问题。</p>
                    </div>
                </div>
            `,
            cast: `
                <div class="content-section" style="min-height: 100vh; padding-top: 100px;">
                    <h2 class="section-title">登场人物 <span>Character</span></h2>
                    <p style="color:#aaa; margin-bottom: 40px; line-height: 2;">这里是英灵殿，谁都可以进来。</p>
                    <div class="cast-grid">
                        ${renderCastCards()}
                    </div>
                </div>
            `,
            warehouse: `
                <div class="content-section" style="min-height: 100vh; padding-top: 100px;">
                    <h2 class="section-title">资料仓库 <span>ARCHIVE</span></h2>
                    <p style="color: #aaa; margin-bottom: 40px; line-height: 2;">收录社团优质专栏、漫评、攻略与剧情解析。</p>
                    <div class="archive-list">
                        ${renderArchiveList()}
                    </div>
                </div>
            `,
            extra: `
                <div class="content-section" style="min-height: 100vh; padding-top: 100px;">
                    <h2 class="section-title">工具与神秘链接 <span>EXTRA</span></h2>
                    <p style="color: #aaa; margin-bottom: 30px; line-height: 2;">收录各种奇妙的摸鱼站点、ARG 解谜档案与实用工具。请选择你的权限终端。</p>
                    <div class="extra-filter">
                        <button class="filter-btn active" data-tag="all" onclick="filterExtra('all')">🌐 全部档案</button>
                        <button class="filter-btn" data-tag="ARG" onclick="filterExtra('ARG')">🕵️ ARG</button>
                        <button class="filter-btn" data-tag="SLG" onclick="filterExtra('SLG')">🎮 SLG</button>
                        <button class="filter-btn" data-tag="puzzle" onclick="filterExtra('puzzle')">🧩 Puzzle</button>
                        <button class="filter-btn" data-tag="天才" onclick="filterExtra('天才')">💡 天才限定</button>
                        <button class="filter-btn" data-tag="做题" onclick="filterExtra('做题')">📝 做题</button>
                        <button class="filter-btn" data-tag="摸鱼" onclick="filterExtra('摸鱼')">🐟 摸鱼专区</button>
                    </div>
                    <div class="extra-grid" id="extra-container">
                        ${renderExtraCards(extraData)}
                    </div>
                </div>
            `
        };

        // === 5. 路由挂载 ===
        const viewContainer = document.getElementById('view-container');
        const mainContentArea = document.getElementById('main-content');
        const navItems = document.querySelectorAll('.nav-item');

        function loadView(viewName) {
            viewContainer.classList.add('fade');
            setTimeout(() => {
                viewContainer.innerHTML = views[viewName] || '<h1>404 Not Found</h1>';
                mainContentArea.scrollTop = 0;
                
                navItems.forEach(nav => nav.classList.remove('active'));
                const activeNav = document.querySelector(`.nav-item[data-view="${viewName}"]`);
                if(activeNav) activeNav.classList.add('active');
                
                viewContainer.classList.remove('fade');

                if (window.innerWidth > 850) {
                    const pcVideo = document.getElementById('pc-video');
                    if (pcVideo) {
                        const source = pcVideo.querySelector('source');
                        source.src = source.getAttribute('data-src');
                        pcVideo.load();
                        pcVideo.play().catch(e => console.warn(e));
                    }
                }
            }, 400); 
        }

        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const targetView = item.getAttribute('data-view');
                if (targetView) loadView(targetView);
            });
        });

        // 默认加载首页
        loadView('home');
    </script>
</body>
</html>
</html>