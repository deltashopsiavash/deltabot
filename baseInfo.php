<?php
/**
 * IMPORTANT:
 * This file contains your bot and database credentials.
 * If you installed using delta.sh, this file is usually generated automatically.
 *
 * If you replaced files manually and the bot stopped responding, create/fill this file.
 *
 * You can also set these via environment variables (recommended):
 *  - DELTABOT_TOKEN
 *  - DELTABOT_ADMIN
 *  - DELTABOT_DB_USER
 *  - DELTABOT_DB_PASS
 *  - DELTABOT_DB_NAME
 */

$botToken  = getenv('DELTABOT_TOKEN') ?: 'PUT_TELEGRAM_BOT_TOKEN_HERE';
$admin     = intval(getenv('DELTABOT_ADMIN') ?: 0);

$dbUserName = getenv('DELTABOT_DB_USER') ?: 'PUT_DB_USERNAME_HERE';
$dbPassword = getenv('DELTABOT_DB_PASS') ?: 'PUT_DB_PASSWORD_HERE';
$dbName     = getenv('DELTABOT_DB_NAME') ?: 'delta';
