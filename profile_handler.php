<?php
require_once 'user_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $new_nickname = trim($_POST['nickname']);
    $avatar_path = $_SESSION['avatar']; // 默认保持原样

    // 1. 处理头像上传
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        // 为了防止重名覆盖，用 ID 命名
        $new_file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
        $target_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path)) {
            $avatar_path = $target_path;
        }
    }

    // 2. 更新数据库
    try {
        $stmt = $pdo_auth->prepare("UPDATE users SET nickname = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$new_nickname, $avatar_path, $user_id]);

        // 3. 同步刷新 Session 里的数据，确保全站立即生效
        $_SESSION['nickname'] = $new_nickname;
        $_SESSION['avatar'] = $avatar_path;

        echo "<script>alert('修改成功！'); window.location.href='profile.php';</script>";
    } catch (PDOException $e) {
        die("更新失败: " . $e->getMessage());
    }
}