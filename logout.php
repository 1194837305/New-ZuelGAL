<?php
session_start();
session_destroy(); // 销毁所有登录数据
header("Location: index.php"); // 跳回首页
exit;
?>