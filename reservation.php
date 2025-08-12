<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/includes/auth_guard.php'; // 引入共用守門
require_login(); // 統一檢查：未登入 → 回首頁

// 統一 PHP 端時區
date_default_timezone_set('Asia/Taipei');
// 統一資料庫端時區，避免用 CURDATE()/CURTIME() 判斷時與 DB 時區不同
try { 
    $pdo->exec("SET time_zone = '+08:00'"); 
} catch (Throwable $e){
    //忽略失敗或寫入 log
    error_log($e->getMessage());
}

// 顯示提示訊息用變數
$message = '';
$color = 'red';
$minDate = (new DateTime('tomorrow'))->format('Y-m-d');    // 從明天開始
$maxDate = (new DateTime('+30 days'))->format('Y-m-d');    // 到30天後

// 設定安全的會員名稱
if (isset($_SESSION['name'])) {
    $name_value = $_SESSION['name'];
} else {
    $name_value = '';
}

// 建立時段陣列
$times = [];
for ($h = 9; $h <= 17; $h++) {
    $times[] = [
        'value' => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00:00', // 送到資料庫的值
        'label' => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'     // 顯示給使用者看的
    ];
}

// 如果有送出表單（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    if (isset($_POST['pet_name'])) {
        // 去除前後空白，避免誤存空格
        $pet_name = trim($_POST['pet_name']);
    } else {
        // 如果沒有填，設為空字串
        $pet_name = '';
    }
    if (isset($_POST['date'])) {
        $date = $_POST['date']; 
    } else {
        $date = '';
    }
    if (isset($_POST['time'])) {
        $time = $_POST['time']; 
    } else {
        $time = '';
    }

    // --- 基本後端驗證（避免繞過前端 required） ---
    if ($pet_name === '' || $date === '' || $time === '') {
        $message = '⚠️ 欄位不可空白';
    } else {
        // 日期不得早於「明天」
        if ($date < $minDate) {
            $message = '⚠️ 只能預約從明天開始的日期';
        } else {
            // 時間格式驗證：期待 HH:MM:SS
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                $message = '⚠️ 時間格式不正確';
            } else {
                // 限制整點 09:00:00 ~ 17:00:00
                $hour = (int)substr($time, 0, 2);
                $min  = substr($time, 3, 2);
                $sec  = substr($time, 6, 2);
                if ($min !== '00' || $sec !== '00' || $hour < 9 || $hour > 17) {
                    $message = '⚠️ 只接受整點 09:00~17:00';
                } else {
                    // 1) 檢查此會員是否已有「未來（含當下）」的預約（狀態 booked）
                    $check_user = $pdo->prepare("
                        SELECT 1
                        FROM reservations
                        WHERE user_id = :user_id
                        AND status  = 'booked'
                        AND (
                                date > CURDATE()
                            OR (date = CURDATE() AND time >= CURTIME())
                        )
                        LIMIT 1
                    ");
                    $check_user->execute(['user_id' => $user_id]);

                    if ($check_user->fetch()) {
                        $message = '⚠️ 您已有預約，請先取消後再預約';
                    } else {
                        // 2) 檢查該日期+時間是否已被其他人預約（唯一時段）
                        $check_slot = $pdo->prepare("
                            SELECT 1
                            FROM reservations
                            WHERE date   = :date
                            AND time   = :time
                            AND status = 'booked'
                            LIMIT 1
                        ");
                        $check_slot->execute(['date' => $date, 'time' => $time]);

                        if ($check_slot->fetch()) {
                            $message = '⚠️ 此時段已被預約，請選擇其他時間';
                        } else {
                            // 3) 寫入新的預約紀錄
                            $insert = $pdo->prepare("
                                INSERT INTO reservations (user_id, pet_name, date, time, status)
                                VALUES (:user_id, :pet_name, :date, :time, 'booked')
                            ");
                            $insert->execute([
                                'user_id'  => $user_id,
                                'pet_name' => $pet_name,
                                'date'     => $date,
                                'time'     => $time,
                            ]);

                            $message = '✅ 預約成功！';
                            $color   = 'green';

                            // 成功後導頁
                            // header('Location: my_reservations.php');
                            // exit();
                        }
                    }
                }
            }
        }
    }
}
$safe_name = htmlspecialchars($name_value, ENT_QUOTES, 'UTF-8');
$safe_color = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');
$safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$safe_minDate = htmlspecialchars($minDate, ENT_QUOTES, 'UTF-8');
$safe_maxDate = htmlspecialchars($maxDate, ENT_QUOTES, 'UTF-8');
$safe_times = [];
foreach ($times as $t) {
    $safe_times[] = [
        'value' => htmlspecialchars($t['value'], ENT_QUOTES, 'UTF-8'),
        'label' => htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8')
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>預約頁面</title>
</head>
<body>

<h2>📅 預約診療</h2>

<!-- 顯示訊息 -->
<?php if (!empty($safe_message)): ?>
    <p style="color: <?= $safe_color ?>;">
        <?= $safe_message ?>
    </p>
<?php endif; ?>

<!-- 預約表單 -->
<form method="POST" action="">
    <label>會員姓名：</label><br>
    <input type="text" value="<?= $safe_name ?>" readonly><br><br>

    <label>寵物姓名：</label><br>
    <input type="text" name="pet_name" required><br><br>

    <label>預約日期：</label><br>
    <input type="date" name="date" 
        min="<?= $safe_minDate ?>" 
        max="<?= $safe_maxDate ?>" 
        required><br><br>

    <label>預約時段（每小時）：</label><br>
    <select name="time" required>
        <option value="">請選擇時間</option>
        <?php foreach ($safe_times as $t): ?>
            <option value="<?= $t['value'] ?>"><?= $t['label'] ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <input type="submit" value="送出預約">
</form>

<hr>
<p>
    <!-- | <a href="my_reservations.php">查看我的預約</a> -->
</p>
</body>
</html>
