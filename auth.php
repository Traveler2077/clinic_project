<?php
// 開啟 Session 功能，讓登入後可以記錄使用者狀態
session_start();

// 引入資料庫連線設定檔
require_once 'db.php';

// 訊息變數，用來顯示錯誤或成功提示
$message = '';

// 檢查表單是否送出（使用 POST 方法）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 如果按的是「登入」按鈕
    if (isset($_POST['login'])) {
        // 取得使用者輸入的 Email 與密碼
        $email = $_POST['email'];
        $password = $_POST['password'];

        // 查詢資料庫中是否有此 Email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 如果找到該使用者，並且密碼正確
        if ($user && password_verify($password, $user['password'])) {
            // 設定 Session，讓使用者保持登入狀態
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role']; // user or admin

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
        $email = $_POST['email'];
        $password = $_POST['password'];

        // 先檢查是否已經有人註冊此 Email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            // 如果找到了，表示 Email 已經被註冊
            $message = "⚠️ 此 Email 已經註冊過了，請使用其他信箱";
        } else {
            // 將密碼加密後存入資料庫（安全性）
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // 將新會員資料寫入資料庫，角色預設為 user
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role)VALUES (:name, :email, :password, 'user')");
            $stmt->execute(['name' => $name, 'email' => $email, 'password' => $hashedPassword]);
            
            // 提示成功訊息
            $message = "✅ 註冊成功，請使用剛剛的帳號密碼登入";
        }
    }
}
?>