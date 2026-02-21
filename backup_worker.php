<?php
// DeltaBot Backup Worker (async)
// Usage:
//   php backup_worker.php backup <BOT_TOKEN> <CHAT_ID> [prefix] [db_name]
//   php backup_worker.php restore <BOT_TOKEN> <CHAT_ID> <SQL_FILE_PATH> [db_name]
//
// This script runs in background (nohup) so main bot won't hang.

if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only"); }

$mode = $argv[1] ?? '';
$token = $argv[2] ?? '';
$chatId = $argv[3] ?? '';
$arg4 = $argv[4] ?? '';
$arg5 = $argv[5] ?? '';

if (!$mode || !$token || !$chatId) {
    fwrite(STDERR, "Invalid args\n");
    exit(1);
}

require_once __DIR__ . "/config.php";

// override token for this worker run
$GLOBALS['botToken'] = $token;
$botToken = $token;

// Optional: override DB for child bots
if($arg5){
    $childDb = trim($arg5);
    if($childDb !== '' && isset($GLOBALS['dbUserName']) && isset($GLOBALS['dbPassword'])){
        // reconnect global $connection
        if(isset($GLOBALS['connection']) && $GLOBALS['connection']){
            @mysqli_close($GLOBALS['connection']);
        }
        $conn2 = new mysqli('localhost', $GLOBALS['dbUserName'], $GLOBALS['dbPassword'], $childDb);
        if(!$conn2->connect_error){
            $conn2->set_charset('utf8mb4');
            $GLOBALS['connection'] = $conn2;
            $GLOBALS['dbName'] = $childDb;
        }
    }
}

function tg($method, $data){
    global $botToken;
    $url = "https://api.telegram.org/bot{$botToken}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}


function startsWith($hay, $needle){
    return substr($hay, 0, strlen($needle)) === $needle;
}

function sendMsg($chatId, $text){
    return tg('sendMessage', ['chat_id'=>$chatId,'text'=>$text]);
}

function sendDoc($chatId, $filePath, $caption=''){
    if(!file_exists($filePath)) return ['ok'=>false,'description'=>'file not found'];
    $post = [
        'chat_id'=>$chatId,
        'caption'=>$caption,
        'document'=> new CURLFile($filePath)
    ];
    return tg('sendDocument', $post);
}

function shellAvailable(){
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    foreach (['shell_exec','exec','system','passthru'] as $f){
        if (!in_array($f, $disabled, true) && function_exists($f)) return true;
    }
    return false;
}

function restoreWithMysqlCli($sqlPath){
    global $dbhost, $dbuser, $dbpass, $dbname;
    if(!shellAvailable()) return false;
    $which = @shell_exec('command -v mysql 2>/dev/null');
    $which = is_string($which) ? trim($which) : '';
    if($which === '' || !is_executable($which)) return false;

    $hostArg = $dbhost ? ('--host=' . escapeshellarg($dbhost)) : '';
    // Use MYSQL_PWD to avoid showing password in process list (best-effort)
    $env = 'MYSQL_PWD=' . escapeshellarg($dbpass);
    $cmd = "$env $which $hostArg --user=".escapeshellarg($dbuser)." ".escapeshellarg($dbname)." < ".escapeshellarg($sqlPath)." 2>&1";
    @shell_exec($cmd);
    return true;
}

function restoreStreaming($sqlPath){
    global $connection;
    $fh = @fopen($sqlPath, 'r');
    if(!$fh) return false;
    @set_time_limit(600);
    if(function_exists('ignore_user_abort')) @ignore_user_abort(true);

    $buffer = '';
    $ok = true;
    while(!feof($fh)){
        $chunk = fread($fh, 1024*1024); // 1MB
        if($chunk === false) break;
        $buffer .= $chunk;

        // Process complete statements ending with ";\n"
        while(($pos = strpos($buffer, ";\n")) !== false){
            $stmt = substr($buffer, 0, $pos+2);
            $buffer = substr($buffer, $pos+2);

            $stmtTrim = trim($stmt);
            if($stmtTrim === '' || startsWith($stmtTrim, '--') || startsWith($stmtTrim, '/*')){
                continue;
            }
            if(!$connection->query($stmtTrim)){
                $ok = false;
                // keep going, but mark failed
            }
        }
    }
    // last statement
    $tail = trim($buffer);
    if($tail !== '' && !startsWith($tail, '--') && !startsWith($tail, '/*')){
        if(!$connection->query($tail)) $ok = false;
    }
    fclose($fh);
    return $ok;
}

if($mode === 'backup'){
    $prefix = $arg4 ?: 'deltabot_backup';
    sendMsg($chatId, "⏳ در حال ساخت بکاپ... لطفا صبر کنید");
    $path = dbCreateSqlBackupFile($prefix);
    if(!$path){
        sendMsg($chatId, "❌ خطا در ساخت بکاپ (دسترسی/تنظیمات دیتابیس).");
        exit(0);
    }
    if(@filesize($path) > 49*1024*1024){
        sendMsg($chatId, "❌ حجم بکاپ خیلی زیاد است و تلگرام اجازه ارسال نمی‌دهد.\nمسیر فایل: {$path}");
        exit(0);
    }
    $cap = "🗄 بکاپ دیتابیس\n".date('Y-m-d H:i:s');
    $res = sendDoc($chatId, $path, $cap);
    if(isset($res['ok']) && $res['ok']){
        @unlink($path);
        sendMsg($chatId, "✅ بکاپ ارسال شد.");
    }else{
        sendMsg($chatId, "❌ ارسال بکاپ ناموفق بود.\nمسیر فایل: {$path}");
    }
    exit(0);
}

if($mode === 'restore'){
    $sqlPath = $arg4;
    if(!$sqlPath || !file_exists($sqlPath)){
        sendMsg($chatId, "❌ فایل بکاپ پیدا نشد.");
        exit(0);
    }
    sendMsg($chatId, "⏳ در حال بازگردانی بکاپ... لطفا صبر کنید");
    $ok = false;
    // Prefer mysql CLI if available
    if(restoreWithMysqlCli($sqlPath)){
        $ok = true;
    }else{
        $ok = restoreStreaming($sqlPath);
    }
    if($ok){
        sendMsg($chatId, "✅ بکاپ با موفقیت بازگردانی شد.");
        // keep file for audit then delete
        @unlink($sqlPath);
    }else{
        sendMsg($chatId, "❌ خطا در بازگردانی بکاپ. فایل SQL یا اجرای کوئری‌ها مشکل دارد.\nمسیر فایل: {$sqlPath}");
    }
    exit(0);
}

sendMsg($chatId, "❌ دستور نامعتبر.");
exit(1);
