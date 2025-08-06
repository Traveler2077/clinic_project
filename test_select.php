<?php
require 'db.php'; // 引入資料庫連線

// 下 SQL 查詢語法，抓出所有使用者資料
$sql = "SELECT * FROM users";

// 使用 PDO 的 prepare + execute 來執行查詢
$stmt = $pdo->prepare($sql);
$stmt->execute();

// 用 fetchAll 把查到的所有資料變成陣列
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>會員清單</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>名稱</th>
        <th>寵物名字</th>
        <th>Email</th>
        <th>電話</th>
        <th>地址</th>
        <th>角色</th>
        <th>註冊時間</th>
    </tr>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['id']) ?></td>
            <td><?= htmlspecialchars($user['name']) ?></td>
            <td><?= htmlspecialchars($user['pet_name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['phone']) ?></td>
            <td><?= htmlspecialchars($user['address']) ?></td>
            <td><?= htmlspecialchars($user['role']) ?></td>
            <td><?= htmlspecialchars($user['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
