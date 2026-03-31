<?php
// 1. 开启缓冲区，防止乱码破坏 JSON
ob_start();

// 2. 引入配置
require_once 'user_config.php'; 

// 3. 强制声明返回格式为 JSON
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// ==========================================
// 模块 1: 处理注册逻辑
// ==========================================
if ($action === 'register') {
    $username = $_POST['username'] ?? '';
    $nickname = $_POST['nickname'] ?? '';
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $avatarPath = '/assets/default-avatar.png'; // 默认头像

    // 必填项校验
    if (!$username || !$password || !$email) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => '请填写完整必填项']);
        exit;
    }

    // 头像上传处理
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $uploadDir = 'uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true); 

        $fileExt = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($fileExt), $allowed)) {
            $newFileName = $username . '_' . time() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                $avatarPath = '/' . $targetPath; 
            }
        }
    }
    // --- 新增：全局唯一性交叉校验 (防冲突逻辑) ---
    try {
        // 这里的逻辑是：查一下库里有没有任何记录，其 账号/昵称/邮箱 碰巧等于你现在想填的这三个值中的任何一个
        $checkSql = "SELECT username, nickname, email FROM users 
                     WHERE username IN (?, ?, ?) 
                        OR nickname IN (?, ?, ?) 
                        OR email IN (?, ?, ?) 
                     LIMIT 1";
        
        $checkParams = [
            $username, $nickname, $email, // 检查 username 列
            $username, $nickname, $email, // 检查 nickname 列
            $username, $nickname, $email  // 检查 email 列
        ];
        
        $checkStmt = $pdo_auth->prepare($checkSql);
        $checkStmt->execute($checkParams);
        $conflict = $checkStmt->fetch();

        if ($conflict) {
            ob_clean();
            // 为了专业感，不具体透露是哪一列冲突，统一告知标识符已被占用
            echo json_encode([
                'success' => false, 
                'message' => '注册失败：该账号、昵称或邮箱已被他人占用（包含交叉占用），请尝试其他组合。'
            ]);
            exit;
        }
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => '安全校验失败: ' . $e->getMessage()]);
        exit;
    }
    // 密码加密
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        // 注意：这里统一使用 $pdo_auth。如果你的 user_config 里叫 $pdo，请修改这里。
        $stmt = $pdo_auth->prepare("INSERT INTO users (username, nickname, email, password, avatar) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $nickname, $email, $hashedPassword, $avatarPath]);
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => '📝 申请已提交！请等待社团管理员审核，通过后即可登录。']);
        exit;
    } catch (PDOException $e) {
        ob_clean();
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'message' => '用户名或邮箱已存在']);
        } else {
            echo json_encode(['success' => false, 'message' => '注册失败: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ==========================================
// 模块 2: 处理登录逻辑
// ==========================================
if ($action === 'login') {
    $account  = $_POST['account'] ?? ''; // 用户名或邮箱
    $password = $_POST['password'] ?? '';

    try {
        // 注意：这里也是 $pdo_auth
        $stmt = $pdo_auth->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$account, $account]);
        $user = $stmt->fetch();
        
        // 校验密码
        if ($user && password_verify($password, $user['password'])) {
            // --- 核心修改：状态拦截器 ---
            if ($user['status'] == 0) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => '☕ 账号审核中：您的申请已收到，请等待管理员通过。']);
                exit;
            } elseif ($user['status'] == 2) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => '🚫 审核未通过：您的入社申请已被拒绝，如有疑问请联系社长。']);
                exit;
            }
            
            
            // 验证成功，保存 Session (这三行代码非常关键！)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nickname'] = $user['nickname'];
            $_SESSION['avatar'] = $user['avatar'];
            
            ob_clean(); 
            echo json_encode([
                'success' => true, 
                'message' => '欢迎回来，' . $user['nickname'],
                'user' => [
                    'nickname' => $user['nickname'],
                    'avatar' => $user['avatar']
                ]
            ]);
            exit;
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => '账号或密码错误']);
            exit;
        }
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => '数据库查询失败: ' . $e->getMessage()]);
        exit;
    }
}

// 如果都不是，返回错误
ob_clean();
echo json_encode(['success' => false, 'message' => '未知的请求类型']);
exit;
?>