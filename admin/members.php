<?php
// admin/members.php
// 會員管理：列表 / 編輯（不可改姓名）/ 刪除（方法A：若有預約則拒絕）

// 連線與權限
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_admin();

// 統一時區
date_default_timezone_set('Asia/Taipei');

// ---- 變數初始化 ----
$message    = '';     // 顯示於頁面的訊息（純文字；頁面用 nl2br 顯示換行）
$action     = '';
$id         = 0;
$page       = 1;
$errs       = [];
$perPage    = 20;
$postAction = '';
$postId     = 0;
$editRow    = null;   // 編輯表單用的資料（可能來自 DB 或來自剛剛提交的值）
$listRows   = [];
$total      = 0;
$totalPages = 1;
$offset     = 0;
$hasPrev    = false;
$hasNext    = false;

// ---- 取得 GET 參數 ----
if (isset($_GET['action'])) 
    $action = $_GET['action'];
if (isset($_GET['id']))     
    $id = (int)$_GET['id'];
if (isset($_GET['page'])) {
    $page = (int)$_GET['page'];
    if ($page < 1) $page = 1;
}

// ---- 先抓 POST 基本參數（讓下面可以用）----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) 
        $postAction = $_POST['action'];
    if (isset($_POST['id']))     
        $postId = (int)$_POST['id'];
}

// ---- 處理 POST：更新 or 刪除 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ========== 更新會員（失敗不跳轉，停留同頁） ==========
    if ($postAction === 'update' && $postId > 0) {
        // 取使用者輸入（trim 避免空白字元誤判）
        $pet_name = trim($_POST['pet_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $role     = trim($_POST['role'] ?? '');
      
        // 必填檢查（地址預設非必填；若要必填，取消下一行註解）
        if ($pet_name === '') 
            $errs[] = '寵物名為必填';
        if ($email    === '') 
            $errs[] = 'Email 為必填';
        if ($phone    === '') 
            $errs[] = '電話為必填';
        if ($role     === '') 
            $errs[] = '角色為必填';
        // if ($address  === '') 
        // $errs[] = '地址為必填'; // 

        // 角色白名單
        if ($role !== '' && !in_array($role, ['member','admin'], true)) {
            $errs[] = '角色值不合法';
        }

        // 若目前沒有錯，再做 Email 重複檢查
        if (empty($errs)) {
            try {
                $checkEmail = $pdo->prepare("
                    SELECT 1 
                    FROM users 
                    WHERE email = :email AND id <> :id
                    LIMIT 1
                ");
                $checkEmail->execute(['email' => $email, 'id' => $postId]);
                if ($checkEmail->fetch()) {
                    $errs[] = '這個 Email 已被其他會員使用，請改用另一個 Email';
                }
            } catch (Throwable $e) {
                $errs[] = '檢查 Email 是否重複時發生錯誤';
            }
        }

        if (!empty($errs)) {//  ❌ 有錯誤：留在本頁，顯示錯誤 + 保留剛輸入的值
            
            //  取出不可編欄位（name / created_at），以便表單顯示
            try {
                $q = $pdo->prepare("
                    SELECT id, 
                           name, 
                           created_at 
                    FROM users 
                    WHERE id = :id 
                    LIMIT 1
                ");
                $q->execute(['id' => $postId]);
                $base = $q->fetch(PDO::FETCH_ASSOC);
                if ($base === false) {
                    $base = ['id' => $postId, 'name' => '', 'created_at' => ''];
                }
            } catch (Throwable $e) {
                $base = ['id' => $postId, 'name' => '', 'created_at' => ''];
            }

            // 用剛輸入的值 + 不可編欄位 組出 $editRow
            $editRow = [
                'id'         => $base['id'],
                'name'       => $base['name'],
                'pet_name'   => $pet_name,
                'email'      => $email,
                'phone'      => $phone,
                'address'    => $address,
                'role'       => $role,
                'created_at' => $base['created_at'],
            ];

            // 組成純文字訊息（使用nl2br）
            $message = '⚠️ 請修正以下問題：' . "\n" . '• ' . implode("\n• ", $errs);
            // 維持在編輯模式，不 redirect
            $action = 'edit';
        } else {
            // ✅ 無錯誤 → 進行 UPDATE，成功才 redirect
            try {
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
                } else {
                    $message = 'ℹ️ 無變更或找不到此會員';
                }
            } catch (Throwable $e) {
                $message = '❌ 更新失敗，請稍後再試';
            }

            // 成功或無變更 → 回列表（維持原本行為）
            header("Location: members.php?page=" . urlencode((string)$page) . "&msg=" . urlencode($message));
            exit;
        }
    }
    // ========== 刪除會員 ==========
    if ($postAction === 'delete' && $postId > 0) {
        try {
            // 開始交易
            $pdo->beginTransaction();

            // 先刪預約（子表）
            $delRes = $pdo->prepare("DELETE FROM reservations WHERE user_id = :uid");
            $delRes->execute(['uid' => $postId]);

            // 再刪會員（父表）
            $delUser = $pdo->prepare("DELETE FROM users WHERE id = :id LIMIT 1");
            $delUser->execute(['id' => $postId]);

            if ($delUser->rowCount() > 0) {
                $pdo->commit();
                $message = '✅ 已刪除會員（含其所有預約）';
            } else {
                // 找不到會員 → 回滾
                $pdo->rollBack();
                $message = '⚠️ 找不到此會員或已被刪除';
            }
        } catch (Throwable $e) {
            // 任一處失敗都回滾
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = '❌ 刪除失敗，請稍後再試';
        }

        header("Location: members.php?page=" . urlencode((string)$page) . "&msg=" . urlencode($message));
        exit;
    }
}

// ---- 若 action=edit 且不是剛剛提交有錯誤的情況，載入 DB 單筆資料 ----
if ($action === 'edit' && $id > 0 && $editRow === null) {
    try {
        $one = $pdo->prepare("
            SELECT *
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $one->execute(['id' => $id]);
        $editRow = $one->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if ($message === '') {
            $message = '❌ 載入會員資料失敗';
        }
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
if ($page > $totalPages) 
    $page = $totalPages;
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
    if ($message === '') {
        $message = '❌ 載入會員列表失敗';
    }
    $listRows = [];
}

// ---- 安全輸出用 ----
$safeMessage    = htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); // 純文字轉義，HTML 用 nl2br 顯示換行
$safePage       = htmlspecialchars((string)$page, ENT_QUOTES, 'UTF-8');
$safeTotalPages = htmlspecialchars((string)$totalPages, ENT_QUOTES, 'UTF-8');
$safePrevPage   = htmlspecialchars((string)($page - 1), ENT_QUOTES, 'UTF-8');
$safeNextPage   = htmlspecialchars((string)($page + 1), ENT_QUOTES, 'UTF-8');

// 轉義列表每一列
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

// 預先處理編輯區要用的安全變數（只有在要顯示表單時才會使用）
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
    if ($editRow['role'] === 'member') {
        $selMember = 'selected';
    } elseif ($editRow['role'] === 'admin') {
        $selAdmin = 'selected';
    }
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
  <!-- 用 nl2br 顯示換行；$safeMessage 已轉義，安全 -->
  <div><?= nl2br($safeMessage) ?></div>
<?php endif; ?>

<?php if ($action === 'edit' && $editRow): ?>
  <h2>編輯會員 #<?= $safeId ?></h2>
  <form method="post" action="members.php?page=<?=$safePage ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= $safeId ?>">

    <div>
      <label>姓名（不可編輯）：</label>
      <input type="text" value="<?= $safeName ?>" readonly>
    </div>

    <div>
      <label>寵物名：</label>
      <input type="text" name="pet_name" value="<?= $safePet ?>" required>
    </div>

    <div>
      <label>Email：</label>
      <input type="email" name="email" value="<?= $safeEmail ?>" required>
    </div>

    <div>
      <label>電話：</label>
      <input type="text" name="phone" value="<?= $safePhone ?>" required>
    </div>

    <div>
      <label>地址：</label>
      <input type="text" name="address" value="<?= $safeAddress ?>">
      <!-- 若要地址也必填，請在上面 input 加上 required 並將後端檢查打開 -->
    </div>

    <div>
      <label>角色：</label>
      <select name="role" required>
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
            <form method="post" action="members.php?page=<?= $safePage ?>" style="display:inline" 
                  onsubmit="return confirm('確定要刪除這個會員嗎？其所有預約也會一併刪除！');">
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