<?php
// 僅引入守門（含 session_start），並限制管理員
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_admin();

// 統一 PHP 端時區
date_default_timezone_set('Asia/Taipei');

// 今天日期（YYYY-mm-dd）
$today = date('Y-m-d');

// 查今天已預約（booked）數量
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt
    FROM reservations
    WHERE date = :today
      AND status = 'booked'
");
$stmt->execute(['today' => $today]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $todayCount = (int)$row['cnt'];
} else {
    $todayCount = 0;
}

?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>後台控制台</title>
</head>
<body>
  <h1>後台控制台</h1>

  <p>今日預約數(booked):<?= htmlspecialchars((string)$todayCount, ENT_QUOTES, 'UTF-8') ?></p>
  <p>日期：<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?></p>

  <p>
    <a href="reservations.php">預約管理</a> |
    <a href="../index.php">回前台首頁</a> |
    <a href="../logout.php">登出</a>
  </p>
</body>
</html>