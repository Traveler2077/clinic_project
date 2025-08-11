<?php
// 如果 Session 還沒啟動，就啟動 Session
// 用 $_SESSION 判斷使用者是否已登入
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * require_login()
 * 檢查使用者是否已登入（不限會員或管理員）
 * - 沒登入就導回登入頁面 auth.php
 */
function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        // 導回首頁
        header('Location: /clinic_project/index.php');
        exit; // 停止後續程式
    }
}

/**
 * require_admin()
 * 檢查使用者是否已登入，而且角色必須是 admin
 * - 沒登入：導回首頁
 * - 有登入但不是管理員：顯示禁止存取訊息
 */
function require_admin(): void {
    // 檢查是否登入
    if (!isset($_SESSION['user_id'])) {
        header('Location: /clinic_project/index.php');
        exit;
    }

    // 檢查角色是否為 admin
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        // 設定 HTTP 狀態碼為 403（Forbidden）
        http_response_code(403);

        // 顯示中文提示
        echo '❌ 拒絕存取：只有管理員可以瀏覽此頁面';
        exit;
    }
}
