<?php
require_once 'db.php'; // 引入資料庫連線

// 如果是從表單送出來的資料，就進入處理區塊
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];       // 使用者輸入的名稱
    $email = $_POST['email'];     // 使用者輸入的 email
    $password = $_POST['password']; // 原始密碼

    // 使用 password_hash() 加密密碼（非常重要）
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 預備 SQL 指令（使用 ? 占位符，防止 SQL Injection）
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");

    // 使用 try...catch 捕捉錯誤
    try {
        if ($stmt->execute([$name, $email, $hashedPassword])) {
            $message = "✅ 註冊成功，請前往登入頁面！";
            // 可以用 header 自動導向：header("Location: login.php");
        } else {
            $message = "❌ 註冊失敗，請稍後再試。";
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = "⚠️ 這個 Email 已經被註冊過了，請使用其他 Email。";
        } else {
            $message = "😢 發生錯誤：" . $e->getMessage();
        }
    }
}
?>

<!-- HTML 表單畫面 -->
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>會員註冊</title>
</head>
<body>
    <h2>註冊帳號</h2>

    <!-- 如果有送出表單，且$message 不為空，就顯示訊息 -->
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($message)){
        echo '<p style="color:red;">' . $message . '</p>';
    }
    ?>

    <!-- 註冊表單 -->
    <form method="POST" action="">
        姓名：<input type="text" name="name" required><br><br>
        Email：<input type="email" name="email" required><br><br>
        密碼：<input type="password" name="password" required><br><br>
        <button type="submit">註冊</button>
    </form>
</body>
</html>
