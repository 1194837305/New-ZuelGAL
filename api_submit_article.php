<?php
session_start();
header('Content-Type: application/json');

require_once 'user_config.php';
$pdo = $pdo_auth; // 变量对齐

// 1. 权限拦截
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => '您的登录已过期，请重新登录']);
    exit;
}

// 2. 接收参数
$user_id = $_SESSION['user_id'];
$type = $_POST['type'] ?? '';
$title = trim($_POST['title'] ?? '');
$summary = trim($_POST['summary'] ?? '');
$cover_url = trim($_POST['cover_url'] ?? '');
$content = $_POST['content'] ?? ''; // 如果是 pdf，这里可能是空的

if (empty($title) || empty($type)) {
    echo json_encode(['status' => 'error', 'message' => '参数不完整']);
    exit;
}

$final_content = $content;

// 3. 针对 PDF 文件的特殊处理
if ($type === 'pdf') {
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'PDF 文件上传失败或未选择']);
        exit;
    }
    
    $file = $_FILES['pdf_file'];
    $max_size = 20 * 1024 * 1024; // 20MB
    if ($file['size'] > $max_size) {
        echo json_encode(['status' => 'error', 'message' => 'PDF 不能超过 20MB']);
        exit;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        echo json_encode(['status' => 'error', 'message' => '只能上传 PDF 格式']);
        exit;
    }

    $upload_dir = 'assets/uploads/pdfs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // 严密防线：避免用户上传恶意 php 文件伪装成 pdf
    $new_filename = 'doc_' . time() . '_' . mt_rand(100, 999) . '.pdf';
    $target_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        echo json_encode(['status' => 'error', 'message' => 'PDF 保存失败，请检查权限']);
        exit;
    }
    
    // PDF 模式下，content 存储的是文件路径
    $final_content = '/' . $target_path; 
}

// 4. 数据落库 (使用 PDO 预处理防止 SQL 注入)
try {
    $stmt = $pdo->prepare("
        INSERT INTO club_articles (user_id, type, title, summary, content, cover_url, status, created_at) 
        VALUES (:user_id, :type, :title, :summary, :content, :cover_url, 0, NOW())
    ");
    
    $stmt->execute([
        ':user_id'   => $user_id,
        ':type'      => $type,
        ':title'     => $title,
        ':summary'   => mb_substr($summary, 0, 200),
        ':content'   => $final_content,
        ':cover_url' => $cover_url // 新增：绑定封面链接
    ]);

    echo json_encode(['status' => 'success', 'message' => '提交成功，进入审核队列']);

} catch (PDOException $e) {
    // 错误日志，生产环境不要把 $e->getMessage() 直接暴露给用户
    echo json_encode(['status' => 'error', 'message' => '数据库写入异常: ' . $e->getMessage()]);
}
?>