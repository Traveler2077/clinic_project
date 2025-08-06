<?php
// 設定資料庫連線參數
$host = 'localhost';       // 主機位置，本機固定為 localhost
$dbname = 'clinic_db';     // 資料庫名稱
$username = 'root';        // 預設帳號（XAMPP 下通常是 root）
$password = '';            // 預設密碼為空（XAMPP 預設）

// 使用 PDO 建立連線（建議寫法，安全、好管理）
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 如果成功連線，$pdo 就是我們的資料庫操作工具
} catch (PDOException $e) {
    // 如果連線錯誤，顯示錯誤訊息並中止程式
    die("資料庫連線失敗：" . $e->getMessage());
}
?>