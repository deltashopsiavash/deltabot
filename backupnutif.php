<?php
// Auto Backup runner - called by cron (e.g. each minute)
// Place this file in /var/www/html/backupnutif.php

@ini_set('display_errors', '0');
@error_reporting(0);

$baseCandidates = [
    __DIR__ . '/deltabot/baseInfo.php',
    __DIR__ . '/deltabot-main/baseInfo.php',
    __DIR__ . '/baseInfo.php',
];

$baseInfo = null;
foreach ($baseCandidates as $p) {
    if (file_exists($p)) { $baseInfo = $p; break; }
}
if (!$baseInfo) {
    http_response_code(404);
    echo "baseInfo.php not found";
    exit;
}
require $baseInfo;

$BOT_TOKEN = $botToken ?? null;
$ADMIN_ID  = (int)($admin ?? 0);

$dbHost = $dbhost ?? 'localhost';
$dbName = $dbname ?? null;
$dbUser = $dbuser ?? null;
$dbPass = $dbpass ?? null;

if (!$BOT_TOKEN || !$ADMIN_ID || !$dbName || !$dbUser) {
    http_response_code(500);
    echo "missing config values";
    exit;
}

$mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo "db connect error";
    exit;
}

// Read AUTO_BACKUP_STATE from table `setting` (JSON: {enabled:0/1, interval: minutes, last: unix})
$enabled = 0; $interval = 2; $last = 0;
$res = $mysqli->query("SELECT `value` FROM `setting` WHERE `type`='AUTO_BACKUP_STATE' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $j = json_decode($row['value'], true);
    if (is_array($j)) {
        $enabled  = (int)($j['enabled'] ?? 0);
        $interval = (int)($j['interval'] ?? 2);
        $last     = (int)($j['last'] ?? 0);
    }
}
if ($interval < 1) $interval = 1;

if (!$enabled) { echo "AUTO_BACKUP_STATE disabled"; exit; }
if (time() - $last < ($interval * 60)) { echo "not time yet"; exit; }

// Make dump
$dump = trim(shell_exec("command -v mysqldump 2>/dev/null"));
if (!$dump) {
    http_response_code(500);
    echo "mysqldump not found";
    exit;
}

$tmp = sys_get_temp_dir() . '/deltabot_auto_backup_' . date('Ymd_His') . '.sql';
$cmd = escapeshellcmd($dump)
    . " --single-transaction --quick --lock-tables=false"
    . " -h " . escapeshellarg($dbHost)
    . " -u " . escapeshellarg($dbUser)
    . " -p" . escapeshellarg((string)$dbPass)
    . " " . escapeshellarg($dbName)
    . " > " . escapeshellarg($tmp) . " 2>/dev/null";

@system($cmd, $rc);
if ($rc !== 0 || !file_exists($tmp) || filesize($tmp) < 10) {
    @unlink($tmp);
    http_response_code(500);
    echo "dump failed";
    exit;
}

// Send to Telegram
$ch = curl_init("https://api.telegram.org/bot{$BOT_TOKEN}/sendDocument");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'chat_id'   => $ADMIN_ID,
    'caption'   => "🗄 بکاپ خودکار دیتابیس\n" . date('Y-m-d H:i:s'),
    'document'  => new CURLFile($tmp),
]);
$resp = curl_exec($ch);
curl_close($ch);

// Update last timestamp
$state = json_encode(['enabled'=>1,'interval'=>$interval,'last'=>time()], JSON_UNESCAPED_UNICODE);
$stmt = $mysqli->prepare("UPDATE `setting` SET `value`=? WHERE `type`='AUTO_BACKUP_STATE'");
if ($stmt) {
    $stmt->bind_param("s", $state);
    $stmt->execute();
    $stmt->close();
}

@unlink($tmp);
echo "OK";
