<?php
/**
 * consistency_check.php
 * 用途：掃描專案的一致性問題（只讀取、不改檔）
 * 使用方式：瀏覽器開啟 http://localhost/你的專案/consistency_check.php
 * 或在 CLI 執行：php consistency_check.php
 */

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Taipei');

$root = __DIR__;
$targets = ['php' => [], 'sql' => []];

// 收集檔案
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
    if ($ext === 'php') $targets['php'][] = $file->getPathname();
    if ($ext === 'sql') $targets['sql'][] = $file->getPathname();
}

// 小工具：讀檔為行陣列
function readLines($path) {
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    return $lines === false ? [] : $lines;
}

// 小工具：模式搜尋並回傳 [file, lineNo, lineText]
function grepFiles($files, $pattern, $flags = 0) {
    $results = [];
    foreach ($files as $f) {
        $lines = readLines($f);
        foreach ($lines as $i => $line) {
            if (preg_match($pattern, $line)) {
                $results[] = [
                    'file' => $f,
                    'line' => $i + 1,
                    'text' => trim($line)
                ];
            }
        }
    }
    return $results;
}

// 1) 檢查是否混用 $pdo / $conn
$pdoPrepare = grepFiles($targets['php'], '/\$pdo\s*->\s*prepare\s*\(/i');
$connPrepare = grepFiles($targets['php'], '/\$conn\s*->\s*prepare\s*\(/i');

// 2) 檢查註冊 INSERT 欄位是否包含 phone、pet_name（初步字串檢查）
$insertUsers = grepFiles($targets['php'], '/INSERT\s+INTO\s+`?users`?/i');
$mentionPhone = grepFiles($targets['php'], '/\bphone\b/i');
$mentionPet   = grepFiles($targets['php'], '/\bpet_name\b/i');

// 3) 角色字串使用處
$roleUser   = grepFiles($targets['php'], "/'user'/i");
$roleMember = grepFiles($targets['php'], "/'member'/i");

// 4) reservations 外鍵是否無 ON DELETE CASCADE
$reservationFK = grepFiles($targets['sql'], '/FOREIGN\s+KEY\s*\(\s*user_id\s*\)\s+REFERENCES\s+`?users`?\s*\(`?id`?\)/i');
$onDeleteCascade = grepFiles($targets['sql'], '/ON\s+DELETE\s+CASCADE/i');

// 5) my_reservations.php 的 LIMIT 5
$limit5 = array_filter(
    grepFiles($targets['php'], '/\bLIMIT\s+5\b/i'),
    fn($hit) => stripos(basename($hit['file']), 'my_reservations.php') !== false
);

// 額外：未登入導向目標是否混用 auth.php / index.php
$redirectAuth  = grepFiles($targets['php'], '/header\s*\(\s*[\'"]Location:\s*auth\.php/iu');
$redirectIndex = grepFiles($targets['php'], '/header\s*\(\s*[\'"]Location:\s*index\.php/iu');

// SQL 結構（users/reservations）的 NOT NULL 線索（快篩）
$usersSql = array_filter($targets['sql'], fn($p) => stripos(basename($p), 'users.sql') !== false);
$resvSql  = array_filter($targets['sql'], fn($p) => stripos(basename($p), 'reservations.sql') !== false);

// 輸出
function title($t){ echo "\n==== {$t} ====\n"; }
function dumpHits($arr, $max=999) {
    if (!$arr) { echo "（未找到）\n"; return; }
    $c = 0;
    foreach ($arr as $hit) {
        if (++$c > $max) break;
        $rel = str_replace(getcwd().DIRECTORY_SEPARATOR, '', $hit['file']);
        echo "{$rel}:{$hit['line']} | {$hit['text']}\n";
    }
}

// 報告開始
echo "一致性檢查報告（".date('Y-m-d H:i:s')."）\n專案路徑：{$root}\n";

// 1)
title('1) $pdo->prepare / $conn->prepare 使用狀況');
echo "- 出現 $pdo->prepare：".count($pdoPrepare)." 處\n";
dumpHits($pdoPrepare, 20);
echo "- 出現 $conn->prepare：".count($connPrepare)." 處\n";
dumpHits($connPrepare, 20);

// 2)
title('2) 註冊 INSERT 與 phone / pet_name 提及情況（快篩）');
echo "- 出現 INSERT INTO users：".count($insertUsers)." 處\n";
dumpHits($insertUsers, 20);
echo "- 全專案提及 phone：".count($mentionPhone)." 處\n";
dumpHits($mentionPhone, 10);
echo "- 全專案提及 pet_name：".count($mentionPet)." 處\n";
dumpHits($mentionPet, 10);
echo "※ 若 INSERT INTO users 沒同時含有 phone、pet_name，且 users.sql 設為 NOT NULL，註冊將報錯。\n";

// 3)
title("3) 角色字串使用處 'user' / 'member'");
echo "- 出現 'user'：".count($roleUser)." 處\n";
dumpHits($roleUser, 20);
echo "- 出現 'member'：".count($roleMember)." 處\n";
dumpHits($roleMember, 20);
echo "※ 若資料表 enum 為('member','admin')，程式請避免判斷 'user'。\n";

// 4)
title('4) reservations 外鍵 / ON DELETE CASCADE');
echo "- 外鍵宣告（user_id -> users.id）出現：".count($reservationFK)." 處\n";
dumpHits($reservationFK, 10);
echo "- ON DELETE CASCADE 出現：".count($onDeleteCascade)." 處\n";
dumpHits($onDeleteCascade, 10);
echo "※ 若沒出現 ON DELETE CASCADE，刪除會員時可能卡住或需人工先清除預約。\n";

// 5)
title('5) my_reservations.php 是否限制 LIMIT 5');
if ($limit5) {
    dumpHits($limit5, 10);
} else {
    echo "（my_reservations.php 未查到 LIMIT 5 或檔名不同）\n";
}

// 額外
title('額外) 未登入導向是否混用 auth.php / index.php');
echo "- 導向 auth.php：".count($redirectAuth)." 處\n";
dumpHits($redirectAuth, 10);
echo "- 導向 index.php：".count($redirectIndex)." 處\n";
dumpHits($redirectIndex, 10);

// SQL 快篩提醒
title('SQL 快篩提醒');
if ($usersSql) {
    foreach ($usersSql as $p) {
        echo "已偵測 users.sql：".basename($p)."\n";
    }
} else {
    echo "（未偵測到 users.sql）\n";
}
if ($resvSql) {
    foreach ($resvSql as $p) {
        echo "已偵測 reservations.sql：".basename($p)."\n";
    }
} else {
    echo "（未偵測到 reservations.sql）\n";
}

echo "\n完成。請將以上輸出貼回來，我會標示每一點的檔名與行號，並提供不改動功能的微調建議清單。\n";
