<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: index.php');
    exit();
}

require_once(__DIR__ . '/../db_connect.php'); 

try {
    $pdo = connectDb();
    $stmt = $pdo->query("SELECT * FROM news ORDER BY created_at DESC");
    $news_list = $stmt->fetchAll();
} catch (PDOException $e) {
    exit('データベースエラー: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>お知らせ管理</title>
    </head>
<body>
    <h1>お知らせ管理</h1>
    
    <p>
        <a href="admin_monsters.php">モンスター管理</a> | 
        <a href="admin_generate_exp.php">経験値テーブル管理</a> | 
        <a href="admin_drops.php">アイテムドロップ管理</a> | 
        <a href="logout.php">ログアウト</a>
    </p>
    <hr>
    <h2>新しいお知らせを追加</h2>
    <form action="add_news.php" method="post">
        <p>タイトル: <input type="text" name="title" size="50" required></p>
        <p>リンクURL: <input type="url" name="link_url" size="50" required></p>
        <p>リンクテキスト: <input type="text" name="link_text" size="50" required></p>
        <button type="submit">追加する</button>
    </form>
    <hr>
    <h2>お知らせ一覧</h2>
    <table border="1">
        <tr>
            <th>投稿日時</th>
            <th>タイトル</th>
            <th>リンク</th>
            <th>操作</th>
        </tr>
        <?php foreach ($news_list as $news): ?>
        <tr>
            <td><?php echo date('Y/m/d H:i', strtotime($news['created_at'])); ?></td>
            <td><?php echo htmlspecialchars($news['title'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><a href="<?php echo htmlspecialchars($news['link_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?php echo htmlspecialchars($news['link_text'], ENT_QUOTES, 'UTF-8'); ?></a></td>
            <td>
                <form action="delete_news.php" method="post" onsubmit="return confirm('本当に削除しますか？');">
                    <input type="hidden" name="id" value="<?php echo $news['id']; ?>">
                    <button type="submit">削除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>