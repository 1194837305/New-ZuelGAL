<?php
header('Content-Type: application/json');
require_once 'user_config.php';
$pdo = $pdo_auth;

try {
    // 联表查询：获取文章的同时，拿到作者的 nickname
    $stmt = $pdo->query("
        SELECT a.*, u.nickname as author_name 
        FROM club_articles a 
        LEFT JOIN users u ON a.user_id = u.id 
        WHERE a.status = 1 
        ORDER BY a.created_at DESC
    ");
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 格式化输出，适配 index.php 的字段名
    $results = [];
    foreach ($articles as $art) {
        $results[] = [
            'id'      => $art['id'],
            'title'   => $art['title'],
            'summary' => $art['summary'] ?: '该作者很懒，没有写摘要...',
            'date'    => date('Y-m-d', strtotime($art['created_at'])),
            'author'  => $art['author_name'] ?: '佚名',
            'type'    => $art['type'],
            'cover'   => $art['cover_url'],
            // 根据类型生成跳转链接
            'link'    => ($art['type'] === 'pdf') 
                 ? "/articles/index.html?id=" . $art['id'] . "&file=" . urlencode($art['content']) . "&title=" . urlencode($art['title'])
                 : ($art['type'] === 'text' ? "articles/article.php?id=" . $art['id'] : $art['content'])
        ];
    }

    echo json_encode($results);
} catch (Exception $e) {
    echo json_encode([]);
}