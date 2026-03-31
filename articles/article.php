<?php
// 1. 开启报错（调试完记得删掉）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. 引入根目录下的配置文件 (使用 ../ 回退一级)
if (file_exists('../user_config.php')) {
    require_once '../user_config.php';
    
    // 【核心修正】将你的 $pdo_auth 赋值给代码中使用的 $pdo
    if (isset($pdo_auth)) {
        $pdo = $pdo_auth;
    } else {
        die("错误：在 user_config.php 中未找到 \$pdo_auth 变量。");
    }
} else {
    die("致命错误：无法在根目录找到 user_config.php，请检查文件是否存在。");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$preview = isset($_GET['preview']) ? 1 : 0; // 管理员预览模式

if ($id <= 0) die("无效的档案 ID");

// 拉取文章数据
$stmt = $pdo->prepare("SELECT * FROM club_articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) die("档案在虚空中迷失了 (404 Not Found)");

// 权限墙：只有审核通过的，或者管理员在预览的才能看
$is_admin = (isset($_SESSION['user_id']) && in_array($_SESSION['user_id'], [1, 2])); // 同样，修改为你的管理员ID
if ($article['status'] != 1 && !($preview && $is_admin)) {
    die("该档案尚在特调局审核中，或已被封存。");
}

// 增加阅读量 (如果是访客且不是预览)
if (!$preview) {
    $pdo->prepare("UPDATE club_articles SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);
}

// 路由分发器 (Dispatcher)
if ($article['type'] === 'bilibili') {
    // 跃迁至 B站
    header("Location: " . $article['content']);
    exit;
} elseif ($article['type'] === 'pdf') {
    // 跃迁至前端 PDF 解析器，必须带上 id！
    $encoded_url = urlencode($article['content']);
    $encoded_title = urlencode($article['title']);
    header("Location: /articles/index.html?id={$id}&file={$encoded_url}&title={$encoded_title}");
    exit;
}

// 以下为 'text' 类型的渲染逻辑
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> - ZuelGal 资料仓库</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <style>
        :root { --primary: #c9171e; --bg-dark: #0a0a0c; }
        body { background-color: var(--bg-dark); color: #e0e0e0; font-family: 'Noto Serif SC', serif; margin: 0; line-height: 1.8; }
        
        /* 顶部导航简版 */
        .nav-bar { background: rgba(10,10,12,0.9); padding: 15px 40px; border-bottom: 1px solid #333; position: sticky; top: 0; z-index: 100; display: flex; justify-content: space-between; align-items: center;}
        .nav-bar a { color: #aaa; text-decoration: none; font-size: 14px; transition: 0.3s;}
        .nav-bar a:hover { color: #fff; }

        /* 文章主体容器 */
        .article-container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .article-header { border-bottom: 1px dashed #333; padding-bottom: 30px; margin-bottom: 40px; }
        .article-title { font-size: 36px; margin: 0 0 15px 0; line-height: 1.4; color: #fff; }
        .article-meta { font-family: sans-serif; font-size: 13px; color: #666; display: flex; gap: 20px; }
        .article-meta span { display: flex; align-items: center; gap: 5px; }

        /* 针对 Quill 富文本的定制渲染样式 */
        .ql-editor { padding: 0; font-family: inherit; font-size: 18px; color: #ddd; }
        .ql-editor h1, .ql-editor h2, .ql-editor h3 { color: #fff; margin-top: 1.5em; border-bottom: 1px solid #222; padding-bottom: 10px; }
        .ql-editor img { max-width: 100%; border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.5); display: block; margin: 20px auto; }
        .ql-editor blockquote { border-left: 4px solid var(--primary); background: #151518; color: #aaa; padding: 15px 20px; font-style: normal; }
        .ql-editor pre { background: #111; border: 1px solid #333; padding: 15px; border-radius: 6px; }

        /* 底部互动区预留 */
        .article-footer { margin-top: 60px; padding-top: 30px; border-top: 1px solid #333; text-align: center; }
        .like-btn { background: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 10px 30px; border-radius: 30px; font-size: 16px; cursor: pointer; transition: 0.3s; }
        .like-btn:hover { background: var(--primary); color: #fff; }
        
        /* 评论区样式 */
        .comments-section { margin-top: 40px; border-top: 1px solid #333; padding-top: 30px; }
        .comment-input-box { display: flex; gap: 15px; margin-bottom: 30px; }
        .comment-input-box textarea { flex: 1; background: #111; border: 1px solid #333; color: #eee; padding: 15px; border-radius: 8px; font-family: inherit; resize: vertical; min-height: 80px; }
        .comment-input-box textarea:focus { border-color: var(--primary); outline: none; }
        .btn-send { background: var(--primary); color: #fff; border: none; padding: 0 25px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-send:hover { filter: brightness(1.2); }
        
        .comment-item { display: flex; gap: 15px; margin-bottom: 25px; }
        .comment-avatar { width: 40px; height: 40px; border-radius: 50%; background: #222; background-size: cover; background-position: center; border: 1px solid #444; flex-shrink: 0;}
        .comment-body { flex: 1; border-bottom: 1px dashed #222; padding-bottom: 15px; }
        .comment-name { color: #aaa; font-size: 13px; margin-bottom: 5px; font-weight: bold; }
        .comment-text { color: #ddd; font-size: 15px; line-height: 1.6; }
        .comment-time { color: #666; font-size: 11px; margin-top: 8px; }
    </style>
</head>
<body>

    <div class="nav-bar">
        <a href="../index.php">← 返回主枢纽</a>
        <span style="color:#555; font-size: 12px;">ZUELGAL ARCHIVE</span>
    </div>

    <div class="article-container">
        <?php if($preview): ?>
            <div style="background: var(--primary); color:#fff; padding: 10px; text-align:center; margin-bottom: 30px; border-radius: 6px;">
                👁️ 管理员预览模式
            </div>
        <?php endif; ?>

        <div class="article-header">
            <h1 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h1>
            <div class="article-meta">
                <span>📅 <?php echo date('Y-m-d', strtotime($article['created_at'])); ?></span>
                <span>👁️ <?php echo $article['view_count']; ?> 次观测</span>
            </div>
        </div>

        <div class="ql-snow">
            <div class="ql-editor">
                <?php 
                    // 这里直接输出 HTML，因为存入前已经是在富文本编辑器里生成的安全结构
                    echo $article['content']; 
                ?>
            </div>
        </div>

        <div class="article-footer">
            <button id="btn-like" class="like-btn" onclick="toggleLike()">❤️ 赞同 (<span id="like-count"><?php echo $article['like_count']; ?></span>)</button>
        </div>

        <div class="comments-section">
            <h3 style="color: #fff; font-size: 18px; margin-bottom: 20px;">档案留言板</h3>
            <div class="comment-input-box">
                <textarea id="comment-input" placeholder="留下你的观测记录..."></textarea>
                <button class="btn-send" onclick="submitComment()">发送</button>
            </div>
            <div id="comments-list">
                <div style="color: #666; text-align: center; padding: 20px;">正在接通神经漫游网络...</div>
            </div>
        </div>
    </div> <script>
        // 确保 PHP 能够正确输出档案 ID
        const currentArticleId = <?php echo isset($id) ? intval($id) : 0; ?>;
        
        // 1. 页面加载时拉取评论和点赞状态
        async function loadInteractions() {
            if (!currentArticleId) return;
            try {
                const res = await fetch(`../api_interaction.php?action=get_comments&article_id=${currentArticleId}`);
                const data = await res.json();
                
                if (data.success) {
                    // 渲染点赞状态
                    const btnLike = document.getElementById('btn-like');
                    if (data.is_liked) {
                        btnLike.style.background = 'var(--primary)';
                        btnLike.style.color = '#fff';
                    }
                    
                    // 渲染评论列表
                    const listContainer = document.getElementById('comments-list');
                    if (data.comments.length === 0) {
                        listContainer.innerHTML = '<div style="color: #666; text-align: center; padding: 20px;">暂无观测记录，来做第一个留言的人吧。</div>';
                        return;
                    }
                    
                    listContainer.innerHTML = data.comments.map(c => `
                        <div class="comment-item">
                            <div class="comment-avatar" style="background-image: url('${c.avatar || '../assets/bg1.jpg'}');"></div>
                            <div class="comment-body">
                                <div class="comment-name">${c.nickname || '佚名社员'}</div>
                                <div class="comment-text">${c.content}</div>
                                <div class="comment-time">${c.created_at}</div>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (err) {
                console.error("互动数据加载失败:", err);
            }
        }

        // 2. 点赞逻辑
        async function toggleLike() {
            if (!currentArticleId) return;
            const formData = new FormData();
            formData.append('action', 'toggle_like');
            formData.append('article_id', currentArticleId);
            
            try {
                const res = await fetch('../api_interaction.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById('like-count').innerText = data.new_count;
                    const btnLike = document.getElementById('btn-like');
                    if (data.status === 'liked') {
                        btnLike.style.background = 'var(--primary)'; 
                        btnLike.style.color = '#fff';
                    } else {
                        btnLike.style.background = 'transparent'; 
                        btnLike.style.color = 'var(--primary)';
                    }
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert("网络连接失败，请稍后重试");
            }
        }

        // 3. 评论逻辑
        async function submitComment() {
            if (!currentArticleId) return;
            const input = document.getElementById('comment-input');
            const content = input.value.trim();
            if (!content) return alert('不能发送空电波哦');
            
            const formData = new FormData();
            formData.append('action', 'comment');
            formData.append('article_id', currentArticleId);
            formData.append('content', content);
            
            try {
                const res = await fetch('../api_interaction.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    input.value = '';
                    loadInteractions(); // 重新拉取列表，实现无刷新显示新评论
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert("网络连接失败，请稍后重试");
            }
        }

        // 【安全补丁】：强制将函数绑定到全局 window 对象，防止作用域丢失
        window.toggleLike = toggleLike;
        window.submitComment = submitComment;

        // 启动拉取
        loadInteractions();
    </script>
</body>
</html>