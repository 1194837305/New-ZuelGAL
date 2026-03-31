<?php
// 强制开启报错并捕捉致命错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// 引入配置并捕获可能的路径错误
if (!file_exists('user_config.php')) {
    echo json_encode(['success' => false, 'message' => '找不到 user_config.php']);
    exit;
}
require_once 'user_config.php';

// 变量对齐
if (isset($pdo_auth)) { 
    $pdo = $pdo_auth; 
} else { 
    echo json_encode(['success' => false, 'message' => '数据库配置变量对齐失败']); 
    exit; 
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$article_id = intval($_POST['article_id'] ?? $_GET['article_id'] ?? 0);

if (!$article_id) {
    echo json_encode(['success' => false, 'message' => '档案ID丢失']);
    exit;
}

try {
    // 1. 获取评论列表 (无需登录即可查看)
    if ($action === 'get_comments') {
        $stmt = $pdo->prepare("
            SELECT c.content, c.created_at, u.nickname, u.avatar 
            FROM club_article_comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.article_id = ? 
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$article_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $is_liked = false;
        if (isset($_SESSION['user_id'])) {
            $check_like = $pdo->prepare("SELECT 1 FROM club_article_likes WHERE article_id = ? AND user_id = ?");
            $check_like->execute([$article_id, $_SESSION['user_id']]);
            $is_liked = (bool)$check_like->fetch();
        }

        echo json_encode(['success' => true, 'comments' => $comments, 'is_liked' => $is_liked]);
        exit;
    }

    // --- 以下操作必须登录 ---
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => '只有社员（已登录）才能进行此操作哦']);
        exit;
    }
    $user_id = $_SESSION['user_id'];

    // 2. 提交评论
    if ($action === 'comment') {
        $content = trim($_POST['content'] ?? '');
        if (mb_strlen($content) < 2) {
            echo json_encode(['success' => false, 'message' => '评论至少需要2个字符']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO club_article_comments (article_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$article_id, $user_id, htmlspecialchars($content)]);
        echo json_encode(['success' => true, 'message' => '评论发布成功']);
        exit;
    }

    // 3. 切换点赞状态
    if ($action === 'toggle_like') {
        $pdo->beginTransaction();
        
        $check = $pdo->prepare("SELECT 1 FROM club_article_likes WHERE article_id = ? AND user_id = ?");
        $check->execute([$article_id, $user_id]);
        
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM club_article_likes WHERE article_id = ? AND user_id = ?")->execute([$article_id, $user_id]);
            $pdo->prepare("UPDATE club_articles SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?")->execute([$article_id]);
            $action_result = 'unliked';
        } else {
            $pdo->prepare("INSERT INTO club_article_likes (article_id, user_id) VALUES (?, ?)")->execute([$article_id, $user_id]);
            $pdo->prepare("UPDATE club_articles SET like_count = like_count + 1 WHERE id = ?")->execute([$article_id]);
            $action_result = 'liked';
        }
        
        $count_stmt = $pdo->prepare("SELECT like_count FROM club_articles WHERE id = ?");
        $count_stmt->execute([$article_id]);
        $new_count = $count_stmt->fetchColumn();
        
        $pdo->commit();
        echo json_encode(['success' => true, 'status' => $action_result, 'new_count' => $new_count]);
        exit;
    }

} catch (PDOException $e) {
    // 【终极追踪】：如果是表不存在等数据库错误，这里会原形毕露
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['success' => false, 'message' => '数据库错误: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['success' => false, 'message' => '系统错误: ' . $e->getMessage()]);
}
?>