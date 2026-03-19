<?php
// ==================================================
// === 投票网站核心展示页面 (vote.php) ===
// ==================================================

// --- 第一部分: 连接到您的数据库“仓库” ---
$db_host = 'localhost';
$db_name = 'zqz_voting';
$db_user = 'zqz_voting';
$db_pass = '789789'; // !! 请确保这里的密码和您设置的一致 !!

// 尝试连接数据库
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 检查连接是否成功
if ($conn->connect_error) {
    // 如果连接失败，就显示错误信息并停止运行
    die("数据库连接失败: " . $conn->connect_error);
}

// 设置字符集
$conn->set_charset("utf8mb4");

// --- 第二部分: 从“仓库”中取出所有角色数据 ---
$characters = [];
$sql = "SELECT id, vndb_id, name, game_title, image_url, votes FROM characters ORDER BY votes DESC, id ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    // 将查询结果一行一行地存入 $characters 数组
    while($row = $result->fetch_assoc()) {
        $characters[] = $row;
    }
}

// 关闭数据库连接，我们已经拿到数据了
$conn->close();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galgame角色投票</title>
    <style>
        /* 这里是您之前编写的所有CSS样式，为了简洁，我暂时折叠了它们 */
        /* 您可以把之前index.html里的<style>标签内的所有内容粘贴到这里 */
        body { font-family: sans-serif; background-color: #f0f2f5; color: #333; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .title { text-align: center; color: #1877f2; }
        .character-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; }
        .character-card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; padding: 15px; transition: transform 0.2s, box-shadow 0.2s; }
        .character-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .character-card img { width: 100%; height: 250px; object-fit: cover; border-radius: 4px; }
        .character-card h3 { font-size: 16px; margin: 10px 0 5px; }
        .character-card p { font-size: 12px; color: #666; margin: 0; }
        .vote-button { background-color: #42b72a; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 10px; font-weight: bold; }
        .vote-count { font-size: 18px; font-weight: bold; color: #dc3545; margin-top: 10px; }
    </style>
</head>
<body>

    <div class="container">
        <h1 class="title">🏆 Galgame角色人气总选举 🏆</h1>

        <div class="character-grid">
            <?php if (empty($characters)): ?>
                <p>数据库中还没有角色数据，请先运行 fetch_vndb_data.php 脚本导入数据！</p>
            <?php else: ?>
                <?php foreach ($characters as $char): ?>
                    <div class="character-card">
                        <img src="<?php echo htmlspecialchars($char['image_url']); ?>" alt="<?php echo htmlspecialchars($char['name']); ?>">
                        <h3><?php echo htmlspecialchars($char['name']); ?></h3>
                        <p><?php echo htmlspecialchars($char['game_title']); ?></p>
                        <div class="vote-count">票数: <?php echo $char['votes']; ?></div>
                        <button class="vote-button" onclick="alert('投票功能将在下一步实现！当前角色ID: <?php echo $char['id']; ?>')">
                            为她投票
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
