<?php
session_start();         // 開啟 session 功能
session_unset();         // 清除所有 session 資料
session_destroy();       // 銷毀 session
header("Location: index.php");  // 導回首頁
exit();
?>