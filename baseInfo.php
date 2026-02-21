<?php
// Auto-generated baseInfo.php
// You can set these via environment variables as well.
$botToken = getenv('DELTABOT_TOKEN') ?: 'PUT_TELEGRAM_BOT_TOKEN_HERE';
$admin    = getenv('DELTABOT_ADMIN') ?: 'PUT_ADMIN_CHAT_ID_HERE';

// DB connection
$dbhost   = getenv('DELTABOT_DB_HOST') ?: 'localhost';
$dbname   = getenv('DELTABOT_DB_NAME') ?: 'delta';
$dbuser   = getenv('DELTABOT_DB_USER') ?: 'PUT_DB_USERNAME_HERE';
$dbpass   = getenv('DELTABOT_DB_PASS') ?: 'PUT_DB_PASSWORD_HERE';
?>