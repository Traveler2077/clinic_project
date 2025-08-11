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
            // rowCount() > 0 代表有成功更新（找不到 id 或原狀態相同則可能為 0）
            if ($stmt->rowCount() > 0) {
                $message = '✅ 已更新預約狀態';
            } else 
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
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
    // 若列表載入失敗、但上面剛好沒有任何訊息，就顯示一個通用的
    if ($message === '') {
        $message = '❌ 載入預約清單時發生錯誤';
    }
}
?>

<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>後台｜預約管理（極簡）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

  <h1>預約管理</h1>

  <p>
    <a href="index.php">回控制台</a> |
    <a href="../logout.php">登出</a>
  </p>

  <!-- 提示訊息（使用 htmlspecialchars 跳脫） -->
  <?php if ($message !== ''): ?>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <!-- 篩選：日期（不輸入日期時 → 顯示最近 50 筆） -->
  <form method="get" action="">
    <label>日期查詢：</label>
    <input type="date" name="date" value="<?= htmlspecialchars($filterDate, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">查詢</button>
    <?php if ($filterDate !== ''): ?>
      <!-- 重新回到同頁但不帶參數，就等於清除篩選 -->
      <a href="reservations.php">清除篩選</a>
    <?php endif; ?>
    <small>（不輸入日期時，預設顯示最近 50 筆）</small>
  </form>

  <hr>

  <!-- 預約列表 -->
  <table border="1" cellpadding="6" cellspacing="0">
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
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['user_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['user_email'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['pet_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['date'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['time'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(($statusMap[$r['status']] ?? $r['status']), ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <!-- 三種狀態按鈕（POST 到同一頁）：用 action 指定要改成的狀態 -->
            <form method="post" action="" onsubmit="return confirm('將狀態改為【已預約】?');" style="display:inline;">
              <input type="hidden" name="id" value="<?= htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="booked">
              <button type="submit">設為已預約</button>
            </form>
            <form method="post" action="" onsubmit="return confirm('將狀態改為【已取消】?');" style="display:inline;">
              <input type="hidden" name="id" value="<?= htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="cancelled">
              <button type="submit">設為已取消</button>
            </form>
            <form method="post" action="" onsubmit="return confirm('將狀態改為【已完成】?');" style="display:inline;">
              <input type="hidden" name="id" value="<?= htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8') ?>">
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
