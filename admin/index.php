<?php
require_once __DIR__ . '/../db.php';           // 你原本的資料庫連線
require_once __DIR__ . '/../includes/auth_guard.php';

require_admin(); // ← 只有 admin 能進來
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>後台控制台</title>
</head>
<body>
  <h1>後台控制台</h1>
  <p>只有管理者看得到這頁。</p>
  <p>
    <a href="../index.php">回前台</a> |
    <a href="../logout.php">登出</a>
  </p>
</body>
</html>