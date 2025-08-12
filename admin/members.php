<?php
// admin/members.php
// 會員管理：列表 / 編輯 / 刪除 

// 連線與權限
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_admin();

// 統一時區
date_default_timezone_set('Asia/Taipei');

// ---- 變數初始化 ----
$message    = '';
$action     = '';
$id         = 0;
$page       = 1;
$perPage    = 20;
$postAction = '';
$postId     = 0;
$getMsg     = '';
$editRow    = null;
$listRows   = [];
$total      = 0;
$totalPages = 1;
$offset     = 0;
$hasPrev    = false;
$hasNext    = false;

// ---- 取得 GET / POST 基本參數 ----
if (isset($_GET['action'])) 
        $action = $_GET['action'];
if (isset($_GET['id']))     
        $id = (int)$_GET['id'];
if (isset($_GET['page'])) {
    $page = (int)$_GET['page'];
    if ($page < 1) $page = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) 
        $postAction = $_POST['action'];
    if (isset($_POST['id']))     
        $postId = (int)$_POST['id'];
}

// ---- 處理 POST：更新 or 刪除 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ===== 更新會員 =====
    if ($postAction === 'update' && $postId > 0) {
        // 1) 取得表單欄位
        $pet_name = isset($_POST['pet_name']) ? trim($_POST['pet_name']) : '';
        $email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
        $phone    = isset($_POST['phone'])    ? trim($_POST['phone'])    : '';
        $address  = isset($_POST['address'])  ? trim($_POST['address'])  : '';
        $role     = isset($_POST['role'])     ? trim($_POST['role'])     : '';

        // 2) 必填檢查（這裡維持你原本只檢查 address）
        if ($pet_name === '' || $email === '' || $phone === '' || $role === '') {
            $message = '⚠️ 寵物名、Email與電話皆為必填，請勿留空';
        } else {
            try {
                // 3) 先檢查 Email 是否被「其他會員」使用（避免違反 UNIQUE）
                $checkEmail = $pdo->prepare("
                    SELECT 1
                    FROM users
                    WHERE email = :email
                      AND id <> :id
                    LIMIT 1
                ");
                $checkEmail->execute([
                    'email' => $email,
                    'id'    => $postId, // 排除自己
                ]);
                if ($checkEmail->fetch()) {
                    // 有資料代表重複
                    $message = '⛔ 這個 Email 已被其他會員使用，請改用另一個 Email';
                } else {
                    // 4) 通過檢查才進行更新
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET pet_name = :pet_name,
                            email    = :email,
                            phone    = :phone,
                            address  = :address,
                            role     = :role
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $stmt->execute([
                        'pet_name' => $pet_name,
                        'email'    => $email,
                        'phone'    => $phone,
                        'address'  => $address,
                        'role'     => $role,
                        'id'       => $postId
                    ]);

                    if ($stmt->rowCount() > 0) {
                        $message = '✅ 會員資料已更新';
                    } elseif ($message === '') {
                        $message = 'ℹ️ 無變更或找不到此會員';
                    }
                }
            } catch (Throwable $e) {
                // 你也可以在這裡針對 SQLSTATE 23000（唯一鍵衝突）再細分訊息
                $message = '❌ 更新失敗，請稍後再試';
            }
        }
    }

    // ===== 刪除會員 =====
    if ($postAction === 'delete' && $postId > 0) {
        try {
            $check = $pdo->prepare("
                SELECT COUNT(*) AS cnt 
                FROM reservations 
                WHERE user_id = :uid
            ");
            $check->execute(['uid' => $postId]);
            $cnt = (int)($check->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

            if ($cnt > 0) {
                $message = '⛔ 無法刪除：此會員仍有預約紀錄（請先處理預約或改用軟刪除機制）';
            } else {
                $del = $pdo->prepare("
                    DELETE FROM users 
                    WHERE id = :id LIMIT 1
                ");
                $del->execute(['id' => $postId]);
                $message = ($del->rowCount() > 0) ? '✅ 已刪除會員' : '⚠️ 找不到此會員或已被刪除';
            }
        } catch (Throwable $e) {
            $message = '❌ 刪除失敗，請稍後再試';
        }
    }

    // 回列表
    header("Location: members.php?page=" . urlencode((string)$page) . "&msg=" . urlencode($message));
    exit;
}

// ---- 接收 redirect 帶回的訊息 ----
if (isset($_GET['msg']) && $message === '') {
    $message = $_GET['msg'];
}

// ---- 若 action=edit，載入單筆資料 ----
if ($action === 'edit' && $id > 0) {
    try {
        $one = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $one->execute(['id' => $id]);
        $editRow = $one->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $message = $message ?: '❌ 載入會員資料失敗';
    }
}

// ---- 計算分頁 ----
try {
    $total = (int)($pdo->query("
        SELECT COUNT(*) AS t 
        FROM users
    ")->fetch(PDO::FETCH_ASSOC)['t'] ?? 0);
} catch (Throwable $e) {
    $total = 0;
}

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$hasPrev = ($page > 1);
$hasNext = ($page < $totalPages);

// ---- 取列表 ----
try {
    $stmt = $pdo->prepare("
        SELECT id, name, pet_name, email, role, created_at, phone, address
        FROM users
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $listRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $message = $message ?: '❌ 載入會員列表失敗';
    $listRows = [];
}

// ---- 安全輸出用 ----
$safeMessage    = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$safePage       = htmlspecialchars((string)$page, ENT_QUOTES, 'UTF-8');
$safeTotalPages = htmlspecialchars((string)$totalPages, ENT_QUOTES, 'UTF-8');
$safePrevPage   = htmlspecialchars((string)($page - 1), ENT_QUOTES, 'UTF-8');
$safeNextPage   = htmlspecialchars((string)($page + 1), ENT_QUOTES, 'UTF-8');

foreach ($listRows as &$u) {
    $u['id']         = htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8');
    $u['name']       = htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8');
    $u['pet_name']   = htmlspecialchars($u['pet_name'], ENT_QUOTES, 'UTF-8');
    $u['email']      = htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8');
    $u['role']       = htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8');
    $u['created_at'] = htmlspecialchars($u['created_at'], ENT_QUOTES, 'UTF-8');
    $u['phone']      = htmlspecialchars($u['phone'], ENT_QUOTES, 'UTF-8');
    $u['address']    = htmlspecialchars((string)$u['address'], ENT_QUOTES, 'UTF-8'); // address 可能為 NULL
}
unset($u);

// 預先處理編輯區要用的安全變數
$safeId = $safeName = $safePet = $safeEmail = $safePhone = $safeAddress = $safeRole = $safeCreated = '';
$selMember = $selAdmin = '';

if ($action === 'edit' && $editRow) {
    $safeId      = htmlspecialchars($editRow['id'],         ENT_QUOTES, 'UTF-8');
    $safeName    = htmlspecialchars($editRow['name'],       ENT_QUOTES, 'UTF-8');
    $safePet     = htmlspecialchars($editRow['pet_name'],   ENT_QUOTES, 'UTF-8');
    $safeEmail   = htmlspecialchars($editRow['email'],      ENT_QUOTES, 'UTF-8');
    $safePhone   = htmlspecialchars($editRow['phone'],      ENT_QUOTES, 'UTF-8');
    $safeAddress = htmlspecialchars((string)$editRow['address'], ENT_QUOTES, 'UTF-8');
    $safeRole    = htmlspecialchars($editRow['role'],       ENT_QUOTES, 'UTF-8');
    $safeCreated = htmlspecialchars($editRow['created_at'], ENT_QUOTES, 'UTF-8');
    $selMember   = ($editRow['role'] === 'member') ? 'selected' : '';
    $selAdmin    = ($editRow['role'] === 'admin')  ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>後台｜會員管理</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<h1>會員管理</h1>
<p>
  <a href="index.php">回控制台</a> |
  <a href="../logout.php">登出</a>
</p>

<?php if ($safeMessage !== ''): ?>
  <div><?= $safeMessage ?></div>
<?php endif; ?>

<?php if ($action === 'edit' && $editRow): ?>
  <h2>編輯會員 #<?= $safeId ?></h2>
  <form method="post" action="members.php?page=<?= $safePage ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= $safeId ?>">
    <div>
      <label>姓名（不可編輯）：</label>
      <input type="text" value="<?= $safeName ?>" readonly>
    </div>
    <div>
      <label>寵物名：</label>
      <input type="text" name="pet_name" value="<?= $safePet ?>">
    </div>
    <div>
      <label>Email：</label>
      <input type="email" name="email" value="<?= $safeEmail ?>">
    </div>
    <div>
      <label>電話：</label>
      <input type="text" name="phone" value="<?= $safePhone ?>">
    </div>
    <div>
      <label>地址：</label>
      <input type="text" name="address" value="<?= $safeAddress ?>" required>
    </div>
    <div>
      <label>角色：</label>
      <select name="role">
        <option value="member" <?= $selMember ?>>member</option>
        <option value="admin"  <?= $selAdmin ?>>admin</option>
      </select>
    </div>
    <div>
      <label>建立時間：</label>
      <input type="text" value="<?= $safeCreated ?>" readonly>
    </div>
    <div style="margin-top:8px;">
      <button type="submit">儲存</button>
      <a href="members.php?page=<?= $safePage ?>">取消</a>
    </div>
  </form>
  <hr>
<?php endif; ?>

<h2>會員列表（第 <?= $safePage ?> / <?= $safeTotalPages ?> 頁）</h2>

<table border="1" cellpadding="6" cellspacing="0" width="100%">
  <thead>
    <tr>
      <th>ID</th>
      <th>姓名</th>
      <th>寵物名</th>
      <th>Email</th>
      <th>電話</th>
      <th>地址</th>
      <th>角色</th>
      <th>建立時間</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!empty($listRows)): ?>
      <?php foreach ($listRows as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><?= $u['name'] ?></td>
          <td><?= $u['pet_name'] ?></td>
          <td><?= $u['email'] ?></td>
          <td><?= $u['phone'] ?></td>
          <td><?= $u['address'] ?></td>
          <td><?= $u['role'] ?></td>
          <td><?= $u['created_at'] ?></td>
          <td>
            <a href="members.php?action=edit&id=<?= $u['id'] ?>&page=<?= $safePage ?>">編輯</a>
            <form method="post" action="members.php?page=<?= $safePage ?>" style="display:inline" onsubmit="return confirm('確定要刪除這個會員嗎？若有任何預約將被拒絕刪除');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit">刪除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="9">目前沒有資料</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<div style="margin-top:10px;">
  <?php if ($hasPrev): ?>
    <a href="members.php?page=<?= $safePrevPage ?>">« 上一頁</a>
  <?php else: ?>
    « 上一頁
  <?php endif; ?>
  |
  <?php if ($hasNext): ?>
    <a href="members.php?page=<?= $safeNextPage ?>">下一頁 »</a>
  <?php else: ?>
    下一頁 »
  <?php endif; ?>
</div>

</body>
</html>
