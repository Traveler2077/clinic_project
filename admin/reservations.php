<?php
// ------------------------------------------------------------
// 後台・預約管理（列表 / 日期篩選 / 狀態變更）
// 說明：列出預約，支援用「日期」查詢，並可把預約狀態改成
//      booked（已預約）/ cancelled（已取消）/ completed（已完成）。
// ------------------------------------------------------------

// 引入資料庫與「權限守門」
// - 用 __DIR__ 避免相對路徑搞錯
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
// 僅允許管理員瀏覽
require_admin();

// 基本設定（時區）
date_default_timezone_set('Asia/Taipei');

$filterDate = '';
$action = '';
$id = 0;
$message = '';
$rows = [];
$statusMap = [
    'booked'    => '已預約',
    'cancelled' => '已取消',
    'completed' => '已完成',
];
$safeMessage    = '';
$safeFilterDate = '';


// ===== 讀取 GET 參數（日期篩選，可空白） =====
if (isset($_GET['date'])) {
    $filterDate = trim($_GET['date']);
}
// ===== 處理 POST（狀態變更） =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 從表單接收資料：id = 預約主鍵、action = 要改成的狀態
    if (isset($_POST['id'])) {
        $id = (int)$_POST['id'];
    }   
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    }
    $allowedStatuses = ['booked', 'cancelled', 'completed']; // 允許的狀態（只接受這三種）

     // 參數檢查：id 要 > 0、action 必須在允許清單內
    if ($id > 0 && in_array($action, $allowedStatuses, true)) {
        try {
            $stmt = $pdo->prepare("UPDATE reservations SET status = :s WHERE id = :id");
            $stmt->execute([
                's'  => $action,
                'id' => $id
            ]);
            if ($stmt->rowCount() > 0) {
                $message = '✅ 已更新預約狀態';
            } else {
                $message = '⚠️ 找不到該筆預約或狀態未變更';
            }
        } catch (Throwable $e) {
            $message = '❌ 更新失敗，請稍後再試';
        }
    } else {
        $message = '⚠️ 參數有誤';
    }
}

// 查詢資料（列表）
// 有指定日期就查當天，否則預設列出最近 50 筆（依日期/時間/ID 逆序）
// -JOIN users 讓列表能顯示會員名稱與 Email，比只看到 user_id 直覺
try {
    if ($filterDate !== '') {
        $stmt = $pdo->prepare("
            SELECT
                r.*,                        -- reservations 表全部欄位
                u.name  AS user_name,       -- 會員姓名（取別名方便前端使用）
                u.email AS user_email       -- 會員 Email（取別名方便前端使用）
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            WHERE r.date = :d               -- 只取某一天
            ORDER BY r.date DESC, r.time DESC, r.id DESC
        ");
        $stmt->execute(['d' => $filterDate]);
    } else {
        $stmt = $pdo->query("
            SELECT
                r.*,
                u.name  AS user_name,
                u.email AS user_email
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            ORDER BY r.date DESC, r.time DESC, r.id DESC
            LIMIT 50
        ");
    }
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // 統一轉義
    foreach ($results as $r) {
        $rows[] = [
            'id'         => htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8'),
            'user_name'  => htmlspecialchars($r['user_name'], ENT_QUOTES, 'UTF-8'),
            'user_email' => htmlspecialchars($r['user_email'], ENT_QUOTES, 'UTF-8'),
            'pet_name'   => htmlspecialchars($r['pet_name'], ENT_QUOTES, 'UTF-8'),
            'date'       => htmlspecialchars($r['date'], ENT_QUOTES, 'UTF-8'),
            'time'       => htmlspecialchars($r['time'], ENT_QUOTES, 'UTF-8'),
            'status'     => htmlspecialchars(($statusMap[$r['status']] ?? $r['status']), ENT_QUOTES, 'UTF-8')
        ];
    }
} catch (Throwable $e) {
    $rows = [];
    // 若列表載入失敗、但上面剛好沒有任何訊息，就顯示一個通用的
    if ($message === '') {
        $message = '❌ 載入預約清單時發生錯誤';
    }
}
$safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$safeFilterDate = htmlspecialchars($filterDate, ENT_QUOTES, 'UTF-8');

?>

<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>後台｜預約管理</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

  <h1>預約管理</h1>
  <p>
    <a href="index.php">回控制台</a> |
    <a href="../logout.php">登出</a>
  </p>

  <?php if ($safeMessage !== ''): ?>
    <div><?= $safeMessage ?></div>
  <?php endif; ?>

  <form method="get" action="">
    <label>日期：</label>
    <input type="date" name="date" value="<?= $safeFilterDate ?>">
    <button type="submit">查詢</button>
    <?php if ($safeFilterDate !== ''): ?>
      <a href="reservations.php">清除篩選</a>
    <?php endif; ?>
  </form>

  <table border="1" cellpadding="4" cellspacing="0" width="100%">
    <thead>
      <tr>
        <th>ID</th>
        <th>會員</th>
        <th>Email</th>
        <th>寵物名</th>
        <th>日期</th>
        <th>時間</th>
        <th>狀態</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($rows)): ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['user_name'] ?></td>
            <td><?= $row['user_email'] ?></td>
            <td><?= $row['pet_name'] ?></td>
            <td><?= $row['date'] ?></td>
            <td><?= $row['time'] ?></td>
            <td><?= $row['status'] ?></td>
            <td>
              <form method="post" action="" style="display:inline">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <input type="hidden" name="action" value="booked">
                <button type="submit">設為已預約</button>
              </form>
              <form method="post" action="" style="display:inline">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <input type="hidden" name="action" value="cancelled">
                <button type="submit">設為已取消</button>
              </form>
              <form method="post" action="" style="display:inline">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <input type="hidden" name="action" value="completed">
                <button type="submit">設為已完成</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="8">沒有符合條件的資料</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

</body>
</html>