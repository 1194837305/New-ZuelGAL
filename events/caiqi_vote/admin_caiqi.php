<?php
// === admin_caiqi.php ===
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['pwd']) || $_GET['pwd'] !== 'zuelgal') {
    die("未授权访问：RESTRICTED AREA");
}

$config_path = '../../config.php';
require_once $config_path;

try {
    $conn = db_connect();
    // 处理一键清空今日选票
    if (isset($_POST['action']) && $_POST['action'] === 'reset_votes') {
        $today = date('Y-m-d');
        // 删除今天所有的开火记录，但不影响作品的总票数（tier_votes）
        $conn->query("DELETE FROM event_caiqi_tier_votes WHERE vote_date = '$today'");
        echo "<script>alert('补给完成！今日所有人的弹药库已重新填满。'); window.location.href='admin_caiqi.php?pwd=zuelgal';</script>";
        exit;
    }
    
    // 处理抹杀提案请求
    if (isset($_POST['action']) && $_POST['action'] === 'delete_proposal') {
        $p_id = intval($_POST['proposal_id']);
        
        // 1. 获取该提案包含的游戏 ID（为了后面减去提名数）
        $p_res = $conn->query("SELECT games_data FROM event_caiqi_proposals WHERE id = $p_id");
        if ($p_res && $p_res->num_rows > 0) {
            $p_row = $p_res->fetch_assoc();
            $games = json_decode($p_row['games_data'], true);
            
            $conn->begin_transaction();
            
            // 2. 依次减少作品的提名计数
            if (is_array($games)) {
                foreach ($games as $g) {
                    $g_id = intval($g['id']);
                    $conn->query("UPDATE event_caiqi_works SET nomination_count = nomination_count - 1 WHERE ymgal_id = $g_id");
                }
            }
            
            // 3. 删除针对该提案的投票记录
            $conn->query("DELETE FROM event_caiqi_proposal_votes WHERE proposal_id = $p_id");
            
            // 4. 删除提案本身
            $conn->query("DELETE FROM event_caiqi_proposals WHERE id = $p_id");
            
            // 5. 清理提名数为 0 的僵尸作品（可选，保持库的整洁）
            $conn->query("DELETE FROM event_caiqi_works WHERE nomination_count <= 0");
            
            $conn->commit();
            echo "<script>alert('抹杀成功：该提案及其相关影响已从物理层面彻底消失。'); window.location.href='admin_caiqi.php?pwd=zuelgal';</script>";
        }
        exit;
    }
    
    // 💡 架构师补丁1：创建系统设置表（用于保存当前是阶段1还是阶段2）
    $conn->query("CREATE TABLE IF NOT EXISTS event_caiqi_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // 初始化阶段（默认为 1）
    $conn->query("INSERT IGNORE INTO event_caiqi_settings (setting_key, setting_value) VALUES ('current_stage', '1')");

    // 💡 架构师补丁2：给作品表增加“最后得票时间”，用于淘汰赛同票判定！
    $check_last_voted = $conn->query("SHOW COLUMNS FROM event_caiqi_works LIKE 'last_voted_at'");
    if ($check_last_voted && $check_last_voted->num_rows == 0) {
        $conn->query("ALTER TABLE event_caiqi_works ADD COLUMN last_voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // 处理阶段切换的请求
    if (isset($_POST['action']) && $_POST['action'] === 'switch_stage') {
        $new_stage = intval($_POST['new_stage']);
        $conn->query("UPDATE event_caiqi_settings SET setting_value = '$new_stage' WHERE setting_key = 'current_stage'");
        echo "<script>alert('世界线变动成功！当前已切换至阶段 $new_stage'); window.location.href='admin_caiqi.php?pwd=zuelgal';</script>";
        exit;
    }

    // 获取当前阶段
    $stage_res = $conn->query("SELECT setting_value FROM event_caiqi_settings WHERE setting_key = 'current_stage'");
    $current_stage = $stage_res->fetch_row()[0];

    // 获取统计数据...
    $total_proposals = $conn->query("SELECT COUNT(*) FROM event_caiqi_proposals")->fetch_row()[0];
    $total_votes = $conn->query("SELECT COUNT(*) FROM event_caiqi_proposal_votes")->fetch_row()[0];
    $unique_games = $conn->query("SELECT COUNT(*) FROM event_caiqi_works")->fetch_row()[0];

    $proposals = $conn->query("SELECT id, author_name, ip_address, total_games, votes, created_at FROM event_caiqi_proposals ORDER BY created_at DESC");
    
} catch (Exception $e) {
    die("数据库通讯崩溃：" . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>监控室 - 十二菜器后台</title>
    <style>
        body { background: #0a0a0c; color: #eee; font-family: sans-serif; padding: 20px; }
        .dashboard { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .card { background: #1a1a1e; border: 1px solid #c9171e; border-radius: 8px; padding: 20px; text-align: center; }
        .card h3 { margin: 0; color: #888; font-size: 14px; text-transform: uppercase; }
        .card .num { font-size: 36px; font-weight: bold; color: #c9171e; margin-top: 10px; }
        
        /* 阶段控制器样式 */
        .stage-controller { background: #221111; border: 1px dashed #ff4d4f; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; }
        .stage-btn { padding: 10px 20px; font-weight: bold; cursor: pointer; border: none; border-radius: 4px; transition: 0.3s; }
        .stage-btn.active { background: #c9171e; color: #fff; }
        .stage-btn.inactive { background: #333; color: #888; }
        
        table { width: 100%; border-collapse: collapse; background: #111; border: 1px solid #333; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #222; }
        th { background: #1a0505; color: #c9171e; font-weight: bold; }
    </style>
</head>
<body>
    <h1 style="color: #c9171e; border-bottom: 1px dashed #333; padding-bottom: 10px;">👁️‍🗨️ SYSTEM OVERRIDE监控面板</h1>
    
    <div class="stage-controller">
        <div>
            <h3 style="margin: 0 0 5px 0; color: #fff;">⏳ 宇宙时间轴控制</h3>
            <div style="font-size: 12px; color: #aaa;">当前入口指向：<?= $current_stage == '1' ? '【阶段一】海选盲盒大厅' : '【阶段二】大逃杀定榜台' ?></div>
        </div>
        <form method="POST" style="display: flex; gap: 10px;">
            <input type="hidden" name="action" value="switch_stage">
            <input type="hidden" name="new_stage" id="new_stage_input" value="">
            <button type="submit" class="stage-btn <?= $current_stage == '1' ? 'active' : 'inactive' ?>" onclick="document.getElementById('new_stage_input').value='1'">1. 海选模式</button>
            <button type="submit" class="stage-btn <?= $current_stage == '2' ? 'active' : 'inactive' ?>" onclick="document.getElementById('new_stage_input').value='2'" onclick="return confirm('警告：确定要将全服入口切换为淘汰赛吗？');">2. 淘汰赛模式</button>
        </form>
    </div>

    <div style="background: #112211; border: 1px dashed #22ff22; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h3 style="margin: 0 0 5px 0; color: #fff;">🔋 战略补给物资</h3>
            <div style="font-size: 12px; color: #aaa;">一键清空今日全服开火记录，重置所有人的弹药配额。</div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_votes">
            <button type="submit" class="btn-danger" style="background: #155724; color: #d4edda; border-color: #28a745; padding: 10px 20px; font-weight: bold;" onclick="return confirm('🚨 警告：这将清除今天所有人的投票限制！\n作品已经获得的票数不会减少，但大家可以重新再投一次。\n确定要执行补给吗？');">
                ⚡ 补满今日全服弹药
            </button>
        </form>
    </div>

    <div class="dashboard">
        <div class="card"><h3>收录提案</h3><div class="num"><?= $total_proposals ?></div></div>
        <div class="card"><h3>大厅选票</h3><div class="num"><?= $total_votes ?></div></div>
        <div class="card"><h3>独立游戏</h3><div class="num"><?= $unique_games ?></div></div>
    </div>
    
    <table>
        <tr>
            <th>ID</th>
            <th>代号 (Author)</th>
            <th>物理追踪 (IP)</th>
            <th>包含游戏数</th>
            <th>获得票数</th>
            <th>提交时间</th>
            <th>神级制裁</th>
        </tr>
        <?php if ($proposals && $proposals->num_rows > 0): ?>
            <?php while($row = $proposals->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td style="font-weight: bold; color: #fff;"><?= htmlspecialchars($row['author_name']) ?></td>
                    <td style="font-family: monospace; color: #888;"><?= $row['ip_address'] ?></td>
                    <td><?= $row['total_games'] ?> 部</td>
                    <td style="color: #FFD700; font-weight: bold;"><?= $row['votes'] ?> 票</td>
                    <td style="font-size: 12px; color: #666;"><?= $row['created_at'] ?></td>
                    <td>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="delete_proposal">
                            <input type="hidden" name="proposal_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn-danger" onclick="return confirm('🚨 最终警告：\n确定要永久抹除 [<?= htmlspecialchars($row['author_name']) ?>] 的提案吗？\n此操作会追回所有相关提名数和选票，不可逆！');">
                                抹杀档案
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7" style="text-align: center; color: #666;">数据库中暂无任何动静。</td></tr>
        <?php endif; ?>
    </table>
    
    </body>
</html>