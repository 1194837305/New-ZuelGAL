<?php
session_start();
// 开启报错以便观察
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'user_config.php';
$pdo = $pdo_auth;
// 【核心修正】将你的变量名对齐为代码使用的名称
if (isset($pdo_auth)) {
    $pdo = $pdo_auth;
} else {
    die("错误：在 user_config.php 中未找到 \$pdo_auth 变量。");
}
// 1. 架构师级安全防线：硬核权限校验
// 假设 user_id = 1 或 2 是管理员，请根据你数据库里自己的 ID 进行修改！
$admin_ids = [2]; 
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], $admin_ids)) {
    die("<h2 style='color:red; text-align:center; margin-top:100px;'>⚠️ 权限不足：此区域属于社团最高机密。</h2>");
}

// admin_dashboard.php 顶部的处理逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $pdo = $pdo_auth; // 再次确保变量对齐
    $article_id = intval($_POST['article_id']);
    $action = $_POST['action'];
    $new_status = ($action === 'approve') ? 1 : 2;

    try {
        $stmt = $pdo->prepare("UPDATE club_articles SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $article_id]);
        echo json_encode(['success' => true, 'message' => '操作成功']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 3. 拉取待审核与已公开的文章
$stmt_pending = $pdo->query("SELECT * FROM club_articles WHERE status = 0 ORDER BY created_at DESC");
$pending_articles = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

$stmt_approved = $pdo->query("SELECT * FROM club_articles WHERE status = 1 ORDER BY created_at DESC LIMIT 20");
$approved_articles = $stmt_approved->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>ZuelGal - 内容审核中心</title>
    <style>
        body { background: #0a0a0c; color: #ddd; font-family: sans-serif; margin: 0; padding: 40px; }
        .dashboard { max-width: 1200px; margin: 0 auto; }
        .header { border-bottom: 2px solid #c9171e; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;}
        h1 { margin: 0; color: #fff; }
        .table-wrap { background: #111; border: 1px solid #333; border-radius: 8px; overflow: hidden; margin-bottom: 40px;}
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #222; }
        th { background: #1a1a1c; color: #888; font-weight: normal; font-size: 14px; }
        tr:hover { background: rgba(255,255,255,0.02); }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .bg-text { background: #2d3748; color: #bee3f8; }
        .bg-pdf { background: #742a2a; color: #fed7d7; }
        .bg-bili { background: #276749; color: #c6f6d5; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold; margin-right: 5px; }
        .btn-approve { background: #38a169; color: #fff; }
        .btn-reject { background: #e53e3e; color: #fff; }
        .btn-view { background: #4a5568; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <h1>🛡️ 资料仓库 - 审核控制台</h1>
            <a href="index.php" style="color:#888;">返回主站</a>
        </div>

        <h3 style="color:#c9171e;">⏳ 待审核队列 (<?php echo count($pending_articles); ?>)</h3>
        <div class="table-wrap">
            <table>
                <tr><th>ID</th><th>类型</th><th>标题</th><th>提交时间</th><th>操作</th></tr>
                <?php foreach ($pending_articles as $art): ?>
                <tr id="row-<?php echo $art['id']; ?>">
                    <td>#<?php echo $art['id']; ?></td>
                    <td><span class="badge bg-<?php echo substr($art['type'],0,4); ?>"><?php echo strtoupper($art['type']); ?></span></td>
                    <td><?php echo htmlspecialchars($art['title']); ?></td>
                    <td style="color:#888; font-size:12px;"><?php echo $art['created_at']; ?></td>
                    <td>
                        <a href="articles/article.php?id=<?php echo $art['id']; ?>&preview=1" target="_blank" class="btn btn-view">预览</a>
                        <button class="btn btn-approve" onclick="handleAudit(<?php echo $art['id']; ?>, 'approve')">通过</button>
                        <button class="btn btn-reject" onclick="handleAudit(<?php echo $art['id']; ?>, 'reject')">打回</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($pending_articles)) echo "<tr><td colspan='5' style='text-align:center; color:#666;'>暂无待审核内容</td></tr>"; ?>
            </table>
        </div>

        <h3 style="color:#888;">✅ 已发布档案 (Top 20)</h3>
        <div class="table-wrap" style="opacity: 0.8;">
            <table>
                <tr><th>ID</th><th>类型</th><th>标题</th><th>阅读量</th></tr>
                <?php foreach ($approved_articles as $art): ?>
                <tr>
                    <td>#<?php echo $art['id']; ?></td>
                    <td><span class="badge bg-<?php echo substr($art['type'],0,4); ?>"><?php echo strtoupper($art['type']); ?></span></td>
                    <td><a href="article.php?id=<?php echo $art['id']; ?>" target="_blank" style="color:#ccc;"><?php echo htmlspecialchars($art['title']); ?></a></td>
                    <td><?php echo $art['view_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <script>
    async function handleAudit(id, action) {
        if(!confirm(action === 'approve' ? '确定通过并公开该文章吗？' : '确定打回该文章吗？')) return;
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('article_id', id);

        const response = await fetch('admin_dashboard.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('row-' + id).remove();
        } else {
            alert('操作失败');
        }
    }
    </script>
</body>
</html>