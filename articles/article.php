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
    // 跃迁至前端 PDF 解析器，同时传递路径和真实标题
    $encoded_url = urlencode($article['content']);
    $encoded_title = urlencode($article['title']);
    // 注意这里改成了 index.html 且加上了 title 参数
    header("Location: /articles/index.html?file={$encoded_url}&title={$encoded_title}");
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
            <button class="like-btn" onclick="alert('点赞系统建设中...')">❤️ 赞同 (<?php echo $article['like_count']; ?>)</button>
        </div>
    </div>

</body>
</html>