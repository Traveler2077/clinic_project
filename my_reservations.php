<?php
session_start();
require_once 'db.php'; // 這裡的 db.php 會建立 $pdo 連線

// 如果沒有登入就跳回首頁
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$rows = [];

// 從資料庫取得該會員的預約紀錄
$stmt = $pdo->prepare("
    SELECT * 
    FROM reservations 
    WHERE user_id = :user_id 
    ORDER BY date, time
");
$stmt->execute(['user_id' => $user_id]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 預先處理成 $rows（含是否可取消）
if (!empty($reservations)) {
    foreach ($reservations as $res) {
        $rows[] = [
            'id' => $res['id'],
            'pet_name' => htmlspecialchars($res['pet_name']),
            'date' => htmlspecialchars($res['date']),
            'time' => htmlspecialchars($res['time']),
            'status' => htmlspecialchars($res['status']),
            'can_cancel' => ($res['status'] === 'booked' && strtotime($res['date']) > strtotime(date('Y-m-d')))
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>我的預約紀錄</title>
</head>
<body>
    <h2>我的預約紀錄</h2>
    <table>
        <thead>
            <tr>
                <th>寵物名</th>
                <th>日期</th>
                <th>時間</th>
                <th>狀態</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= $row['pet_name'] ?></td>
                        <td><?= $row['date'] ?></td>
                        <td><?= $row['time'] ?></td>
                        <td><?= $row['status'] ?></td>
                        <td>
                            <?php if ($row['can_cancel']): ?>
                                <form action="cancel_reservation.php" method="POST" onsubmit="return confirm('確定要取消這個預約嗎？');">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit">取消</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">目前沒有任何預約紀錄</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <a href="logout.php">登出</a>
</body>
</html>