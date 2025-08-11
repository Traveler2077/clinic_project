<?php
// 開啟 Session 功能，讓登入後可以記錄使用者狀態
session_start();

// 引入資料庫連線設定檔
require_once 'db.php';

// 訊息變數，用來顯示錯誤或成功提示
$message = '';
$color = 'red';

// 檢查表單是否送出（使用 POST 方法）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 如果按的是「登入」按鈕
    if (isset($_POST['login'])) {
        // 取得使用者輸入的 Email 與密碼
        $email = $_POST['email'];
        $password = $_POST['password'];

        // 查詢資料庫中是否有此 Email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 如果找到該使用者，並且密碼正確
        if ($user && password_verify($password, $user['password'])) {
            // 設定 Session，讓使用者保持登入狀態
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role']; // member or admin

            // 登入成功後導回首頁
            header("Location: index.php");
            exit();
        } else {
            // 登入失敗提示訊息
            $message = "❌ 登入失敗，帳號或密碼錯誤";
        }

    // 如果按的是「註冊」按鈕
    } elseif (isset($_POST['register'])) {
        // 取得註冊用的表單欄位
        $name = $_POST['name'];
        $pet_name = $_POST['pet_name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $phone = $_POST['phone'] ;
        $address = $_POST['address'] ?? null;

        // 先檢查是否已經有人註冊此 Email
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "⚠️ Email 格式不正確";
        }else{
            if ($stmt->fetch()) {
                // 如果找到了，表示 Email 已經被註冊
                $message = "⚠️ 此 Email 已經註冊過了，請使用其他信箱";
            } else {
                // 將密碼加密後存入資料庫（安全性）
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // 將新會員資料寫入資料庫，角色預設為 user
                $stmt = $pdo->prepare("
                INSERT INTO users (name, pet_name, email, password, phone, address, role)
                VALUES (:name,:pet_name, :email, :password, :phone, :address, :role)
                ");
                $stmt->execute([
                    'name' => $name,
                    'pet_name' => $pet_name,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'phone' => $phone,
                    'address' => $address,
                    'role' => 'member'
                ]);
                
                // 提示成功訊息
                $message = "✅ 註冊成功，請使用剛剛的帳號密碼登入";
                $color = 'green';
            }
        }
    }
}
$safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$safe_color = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>登入 / 註冊</title>
</head>
<body>

<!-- 顯示訊息區塊 -->
<?php if (!empty($safe_message)): ?>
<p style="color: <?= $safe_color ?>;">
    <?= $safe_message ?>
</p>
<?php endif; ?>

<h2>會員登入 / 註冊</h2>
<!-- 登入表單 -->
<h3>登入</h3>
<form method="POST" action="">
    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>

    <label>密碼：</label><br>
    <input type="password" name="password" required><br><br>

    <input type="submit" name="login" value="登入">
</form>

<hr>

<h3>註冊</h3>
<form method="POST" action="">
    <label>姓名：</label><br>
    <input type="text" name="name" required><br><br>

    <label>寵物姓名：</label><br>
    <input type="text" name="pet_name" required><br><br>

    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>

    <label>密碼：</label><br>
    <input type="password" name="password" required><br><br>

    <label>電話：</label><br>
    <input type="text" name="phone" required><br><br>

    <label>地址：</label><br>
    <input type="text" name="address"><br><br>

    <input type="submit" name="register" value="註冊">
</form>
</body>
</html>