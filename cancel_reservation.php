<?php
session_start();
require_once 'db.php'; // 資料庫連線 ($pdo)

// 確保使用者有登入
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 確保是 POST 請求，且有收到預約 ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $reservationId = $_POST['id'];
    $userId = $_SESSION['user_id'];

    // 安全地取消自己的預約（僅限 status = 'booked'）
    $stmt = $pdo->prepare("
        UPDATE reservations 
        SET status = 'cancelled'
        WHERE id = :id 
        AND user_id = :user_id 
        AND status = 'booked'
    ");
    $stmt->execute([
        'id' => $reservationId,
        'user_id' => $userId
    ]);

// 無論成功或失敗都回到預約頁
header('Location: my_reservations.php');
exit;