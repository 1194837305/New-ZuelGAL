<?php
// api_upload_image.php
session_start();
header('Content-Type: application/json');
require_once 'user_config.php';
// 权限拦截
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '无权限，请先登录']);
    exit;
}

if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => '没有检测到文件']);
    exit;
}

$file = $_FILES['image'];
$max_size = 5 * 1024 * 1024; // 限制最大 5MB
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => '图片体积不能超过 5MB']);
    exit;
}

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => '不支持的图片格式']);
    exit;
}

// 创建长久存储目录 (需确保 www 用户有权限写入)
$upload_dir = 'assets/uploads/images/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 采用 用户ID + 时间戳 + 随机码 防止文件重名冲突
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = 'img_' . $_SESSION['user_id'] . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
$target_path = $upload_dir . $new_filename;

if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // 返回绝对或相对路径给前端 Quill
    echo json_encode([
        'success' => true, 
        'url' => '/' . $target_path // 注意这里加了斜杠，适配你的根目录路由
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '服务器写入文件失败，请检查目录权限']);
}
?>