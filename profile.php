<?php
require_once 'user_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // 没登录的直接踢回首页
    exit;
}

// 变量对齐防崩
if (isset($pdo_auth)) {
    $pdo = $pdo_auth;
} else {
    die("数据库连接失败：未找到配置变量");
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'] ?? $_SESSION['username'];
$avatar = $_SESSION['avatar'] ?? 'assets/default-avatar.png';

// ==========================================
// 核心：拉取用户的三大互动数据
// ==========================================

// 1. 获取“我收到的赞” (统计该用户发布的所有 status=1 的文章的总获赞数)
$stmt_likes = $pdo->prepare("SELECT SUM(like_count) as total_likes FROM club_articles WHERE user_id = ? AND status = 1");
$stmt_likes->execute([$user_id]);
$total_likes = $stmt_likes->fetchColumn() ?: 0;

// 2. 获取“我收到的评论” (别人在我的文章下留的言，排除自己给自己的留言)
$stmt_recv_comments = $pdo->prepare("
    SELECT c.content, c.created_at, u.nickname as commenter_name, u.avatar as commenter_avatar, a.title, a.id as article_id, a.type
    FROM club_article_comments c
    JOIN club_articles a ON c.article_id = a.id
    JOIN users u ON c.user_id = u.id
    WHERE a.user_id = ? AND c.user_id != ? AND a.status = 1
    ORDER BY c.created_at DESC LIMIT 15
");
$stmt_recv_comments->execute([$user_id, $user_id]);
$received_comments = $stmt_recv_comments->fetchAll(PDO::FETCH_ASSOC);

// 3. 获取“我发出的评论” (我在任何文章下的留言)
$stmt_my_comments = $pdo->prepare("
    SELECT c.content, c.created_at, a.title, a.id as article_id, a.type, a.content as article_url
    FROM club_article_comments c
    JOIN club_articles a ON c.article_id = a.id
    WHERE c.user_id = ? AND a.status = 1
    ORDER BY c.created_at DESC LIMIT 15
");
$stmt_my_comments->execute([$user_id]);
$my_comments = $stmt_my_comments->fetchAll(PDO::FETCH_ASSOC);

// 路由生成器（用于点击评论直接跳到对应文章）
function getArticleLink($type, $id, $url = '') {
    if ($type === 'text') return "articles/article.php?id=" . $id;
    if ($type === 'pdf') return "articles/index.html?id=" . $id . "&file=" . urlencode($url);
    return "#"; 
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人中心 - ZuelGal</title>
    <style>
        :root { --primary: #ff6699; --bg: #fdf6f8; --text-main: #333; --text-muted: #888; }
        body { background: var(--bg); font-family: "Microsoft YaHei", sans-serif; margin: 0; padding: 40px 20px; display: flex; justify-content: center; }
        
        /* 布局容器 */
        .profile-container { display: flex; gap: 30px; max-width: 1000px; width: 100%; flex-wrap: wrap; align-items: flex-start;}
        
        /* 左侧：个人资料卡 */
        .profile-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); flex: 1; min-width: 300px; text-align: center; position: sticky; top: 40px;}
        .avatar-preview { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary); margin-bottom: 20px; box-shadow: 0 5px 15px rgba(255,102,153,0.3); }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; color: #666; font-size: 14px; font-weight: bold;}
        input[type="text"], input[type="file"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; transition: 0.3s; }
        input[type="text"]:focus { border-color: var(--primary); outline: none; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 25px; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold; transition: 0.3s; margin-top: 10px;}
        .btn-save:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255,102,153,0.4);}
        .back-link { display: inline-block; margin-top: 20px; color: #999; text-decoration: none; font-size: 14px; transition: 0.3s;}
        .back-link:hover { color: var(--primary); }

        /* 右侧：数据看板 */
        .dashboard-panel { flex: 2; min-width: 400px; display: flex; flex-direction: column; gap: 20px; }
        
        .stat-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; }
        .stat-title { font-size: 18px; font-weight: bold; color: var(--text-main); }
        .stat-number { font-size: 36px; font-weight: 900; color: var(--primary); }

        .list-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .list-card h3 { margin: 0 0 20px 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; color: var(--text-main); font-size: 18px;}
        
        .comment-item { padding: 15px 0; border-bottom: 1px dashed #eee; display: flex; gap: 15px; }
        .comment-item:last-child { border-bottom: none; padding-bottom: 0; }
        .comment-avatar { width: 40px; height: 40px; border-radius: 50%; background-size: cover; background-position: center; flex-shrink: 0; border: 1px solid #eee; }
        .comment-body { flex: 1; }
        .comment-header { font-size: 13px; color: var(--text-muted); margin-bottom: 5px; }
        .comment-header span { color: var(--primary); font-weight: bold; }
        .comment-content { font-size: 14px; color: #444; line-height: 1.6; background: #f9f9f9; padding: 10px; border-radius: 8px; margin-top: 5px;}
        .comment-target { font-size: 12px; color: #999; margin-top: 8px; }
        .comment-target a { color: #666; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .comment-target a:hover { color: var(--primary); text-decoration: underline; }
        
        .empty-state { text-align: center; color: #aaa; padding: 30px 0; font-size: 14px; }

        @media (max-width: 768px) {
            .profile-container { flex-direction: column; }
            .dashboard-panel { min-width: 100%; }
            .profile-card { position: relative; top: 0; }
        }
    </style>
</head>
<body>

<?php include_once "player.php"; ?>

<div class="profile-container">
    
    <div class="profile-card">
        <img src="<?php echo $avatar; ?>" class="avatar-preview" id="preview">
        <h2 style="color: var(--text-main); margin-top: 0;"><?php echo htmlspecialchars($nickname); ?></h2>
        
        <form action="profile_handler.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>更换社团代号 (昵称)</label>
                <input type="text" name="nickname" value="<?php echo htmlspecialchars($nickname); ?>" required>
            </div>
            
            <div class="form-group">
                <label>上传新头像 (建议 1:1 比例)</label>
                <input type="file" name="avatar" accept="image/png, image/jpeg, image/webp" onchange="updatePreview(this)">
            </div>
            
            <button type="submit" class="btn-save">💾 保存修改</button>
        </form>
        
        <a href="index.php" class="back-link">← 返回社团主页</a>
    </div>

    <div class="dashboard-panel">
        
        <div class="stat-card">
            <div class="stat-title">❤️ 我收到的赞同总数</div>
            <div class="stat-number"><?php echo $total_likes; ?></div>
        </div>

        <div class="list-card">
            <h3>📬 我收到的评论</h3>
            <?php if (empty($received_comments)): ?>
                <div class="empty-state">暂时还没有人留言呢...</div>
            <?php else: ?>
                <?php foreach ($received_comments as $c): ?>
                    <div class="comment-item">
                        <div class="comment-avatar" style="background-image: url('<?php echo htmlspecialchars($c['commenter_avatar'] ?: 'assets/default-avatar.png'); ?>');"></div>
                        <div class="comment-body">
                            <div class="comment-header">
                                <span><?php echo htmlspecialchars($c['commenter_name']); ?></span> 在 <?php echo date('m-d H:i', strtotime($c['created_at'])); ?> 留言：
                            </div>
                            <div class="comment-content">
                                <?php echo htmlspecialchars($c['content']); ?>
                            </div>
                            <div class="comment-target">
                                来源档案：<a href="<?php echo getArticleLink($c['type'], $c['article_id']); ?>"><?php echo htmlspecialchars($c['title']); ?></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="list-card">
            <h3>📝 我的观测记录 (发出的评论)</h3>
            <?php if (empty($my_comments)): ?>
                <div class="empty-state">你还没有在任何档案下留过言哦。</div>
            <?php else: ?>
                <?php foreach ($my_comments as $c): ?>
                    <div class="comment-item">
                        <div class="comment-body">
                            <div class="comment-header">
                                发表于 <?php echo date('Y-m-d H:i', strtotime($c['created_at'])); ?>
                            </div>
                            <div class="comment-content" style="background: #fff0f5;"> <?php echo htmlspecialchars($c['content']); ?>
                            </div>
                            <div class="comment-target">
                                目标档案：<a href="<?php echo getArticleLink($c['type'], $c['article_id'], $c['article_url']); ?>"><?php echo htmlspecialchars($c['title']); ?></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    // 头像实时预览逻辑
    function updatePreview(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

</body>
</html>