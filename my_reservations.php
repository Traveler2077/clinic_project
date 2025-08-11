<?php
session_start();
require_once 'db.php'; // 這裡的 db.php 會建立 $pdo 連線
require_once __DIR__ . '/includes/auth_guard.php'; // 引入共用守門
require_login(); // 統一檢查：未登入 → 回首頁

// 統一 PHP 端時區
date_default_timezone_set('Asia/Taipei');

$message = '';
$user_id = $_SESSION['user_id'];
$rows = [];
// 狀態顯示對應中文
$statusMap = [
    'booked' => '已預約',
    'cancelled' => '已取消',
    'completed' => '已完成'
];

// 從資料庫取得最近 5 筆預約紀錄（依日期、時間倒序）
$stmt = $pdo->prepare("
    SELECT * 
    FROM reservations 
    WHERE user_id = :user_id 
    ORDER BY date DESC, time DESC
    LIMIT 5
");
$stmt->execute(['user_id' => $user_id]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 預先處理成 $rows 陣列（含轉換狀態、可否取消）
if (!empty($reservations)) {
    foreach ($reservations as $res) {
        if (isset($statusMap[$res['status']])) {
            $statusText = $statusMap[$res['status']];
        } else {
            $statusText = $res['status'];
        }
        $rows[] = [
            'id' => htmlspecialchars($res['id'], ENT_QUOTES, 'UTF-8'),
            'pet_name' => htmlspecialchars($res['pet_name'], ENT_QUOTES, 'UTF-8'),
            'date' => htmlspecialchars($res['date'], ENT_QUOTES, 'UTF-8'),
            'time' => htmlspecialchars($res['time'], ENT_QUOTES, 'UTF-8'),
            'status' => htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'),
            'can_cancel' => ($res['status'] === 'booked' && strtotime($res['date']) > strtotime(date('Y-m-d')))
        ];
    }
}

// 若恰好顯示 5 筆，顯示提醒訊息
if (count($rows) === 5) {
    $message = '（僅顯示最近 5 筆預約資料）';
}
$safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
?>

<!-- HTML 表單畫面 -->
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>我的預約紀錄</title>
</head>
<body>
    <h2>我的預約紀錄</h2>
    <?php if (!empty($safe_message)): ?>
        <p><?= ($safe_message)?></p>
    <?php endif; ?>
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