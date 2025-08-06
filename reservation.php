<?php
session_start();
date_default_timezone_set('Asia/Taipei');
require_once 'db.php';

// 如果沒有登入，導回登入頁
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

// 顯示提示訊息用變數
$message = '';

// 如果有送出表單（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $pet_name = $_POST['pet_name'] ?? '';
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';

    // ✅ 第一步：檢查該會員是否已有預約（只允許一筆）
    $check_user = $pdo->prepare("SELECT * FROM reservations WHERE user_id = :user_id AND status = 'booked'");
    $check_user->execute(['user_id' => $user_id]);

    if ($check_user->rowCount() > 0) {
        $message = "⚠️ 您已經有預約，請先取消原有預約再重新預約";
    } else {
        // ✅ 第二步：檢查該時段是否已被其他人預約
        $check_slot = $pdo->prepare("SELECT * FROM reservations WHERE date = :date AND time = :time AND status = 'booked'");
        $check_slot->execute(['date' => $date, 'time' => $time]);

        if ($check_slot->fetch()) {
            $message = "⚠️ 此時段已被預約，請選擇其他時間";
        } else {
            // ✅ 第三步：寫入新的預約紀錄
            $stmt = $pdo->prepare("
                INSERT INTO reservations (user_id, pet_name, date, time, status)
                VALUES (:user_id, :pet_name, :date, :time, 'booked')
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'pet_name' => $pet_name,
                'date' => $date,
                'time' => $time
            ]);

            $message = "✅ 預約成功！";
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
