<?php
session_start();
require_once 'db.php';
// 統一 PHP 端時區
date_default_timezone_set('Asia/Taipei');
// 統一資料庫端時區，避免用 CURDATE()/CURTIME() 判斷時與 DB 時區不同
try { $pdo->exec("SET time_zone = '+08:00'"); } catch (Throwable $e)

// 如果沒有登入，導回登入頁
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

// 顯示提示訊息用變數
$message = '';
$color = 'red';

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
                        $message = '⚠️ 您已有未來的預約，請先取消後再預約';
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

                            // （可選）成功後導頁：開啟這兩行即可
                            // header('Location: my_reservations.php');
                            // exit();
                        }
                    }
                }
            }
        }
    }
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
<?php
// 如果 $message 有內容就顯示
if (!empty($message)) {
    // 根據訊息開頭是否是 ✅ 來決定顏色
    if (str_starts_with($message, '✅')) {
        $color = 'green';
    } else {
        $color = 'red';
    }

    // 顯示訊息段落
    echo '<p style="color:' . $color . ';">' . $message . '</p>';
}
?>

<!-- 預約表單 -->
<form method="POST" action="">
 
    <label>會員姓名：</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($_SESSION['name']) ?>" readonly><br><br>

    <label>寵物姓名：</label><br>
    <input type="text" name="pet_name" required><br><br>

    <label>預約日期：</label><br>
    <input type="date" name="date" 
            min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
            max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
            required><br><br>

    <label>預約時段（每小時）：</label><br>
    <select name="time" required>
        <option value="">請選擇時間</option>
        <?php
        for ($h = 9; $h <= 17; $h++) {
            $timeStr = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00:00';
            $label = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            echo "<option value=\"$timeStr\">$label</option>";
        }
        ?>
    </select><br><br>

    <input type="submit" value="送出預約">
</form>

</body>
</html>
