<?php
require_once 'user_config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // 没登录的直接踢回首页
    exit;
}

// 从 Session 获取当前信息
$nickname = $_SESSION['nickname'] ?? $_SESSION['username'];
$avatar = $_SESSION['avatar'] ?? 'assets/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>个人中心 - ZuelGal</title>
    <style>
        :root { --primary: #ff6699; --bg: #fdf6f8; }
        body { background: var(--bg); font-family: "Microsoft YaHei", sans-serif; display: flex; justify-content: center; padding-top: 50px; }
        .profile-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 400px; text-align: center; }
        .avatar-preview { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; color: #666; font-size: 14px; }
        input[type="text"], input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 25px; cursor: pointer; width: 100%; font-size: 16px; transition: 0.3s; }
        .btn-save:hover { opacity: 0.9; transform: scale(1.02); }
        .back-link { display: block; margin-top: 15px; color: #999; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>

<?php include_once "player.php"; ?>

<div class="profile-card">
    <img src="<?php echo $avatar; ?>" class="avatar-preview" id="preview">
    <h2><?php echo htmlspecialchars($nickname); ?> 的个人中心</h2>
    
    <form action="profile_handler.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>更换昵称</label>
            <input type="text" name="nickname" value="<?php echo htmlspecialchars($nickname); ?>" required>
        </div>
        
        <div class="form-group">
            <label>上传新头像 (建议 200x200)</label>
            <input type="file" name="avatar" accept="image/*" onchange="updatePreview(this)">
        </div>
        
        <button type="submit" class="btn-save">保存修改</button>
    </form>
    
    <a href="index.php" class="back-link">← 返回首页</a>
</div>

<script>
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