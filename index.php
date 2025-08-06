<?php
echo "網站安裝成功 🎉";
?>

<?php
session_start();
?>

<h2>診所一頁式首頁</h2>

<?php if (isset($_SESSION['name'])): ?>
    <p>👋 你好，<?= htmlspecialchars($_SESSION['name']) ?>！</p>
    <p><a href="logout.php">登出</a></p>
<?php else: ?>
    <p><a href="auth.php">登入 / 註冊</a></p>
<?php endif; ?>