<?php
/**
 * GSAA Global — site configuration.
 * Fill in the real values below before deploying. Never commit this file
 * with real secrets to a public git repo.
 */

// ---- Database -------------------------------------------------------
define('DB_DRIVER', 'mysql');           // 'mysql' for production
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'gsaa_global');
define('DB_USER', 'CHANGE_ME');
define('DB_PASS', 'CHANGE_ME');

// For local testing only, you can switch to a SQLite file instead of MySQL:
// define('DB_DRIVER', 'sqlite');
// define('DB_SQLITE_PATH', __DIR__ . '/data/gsaa.sqlite');

// ---- Site ------------------------------------------------------------
define('SITE_NAME', 'GSAA Global');
define('SITE_URL', 'https://gsaaglobal.com'); // no trailing slash

// ---- Razorpay ----------------------------------------------------------
// Get these from https://dashboard.razorpay.com/app/keys (Live keys for
// production, Test keys while developing).
define('RAZORPAY_KEY_ID', 'rzp_test_CHANGE_ME');
define('RAZORPAY_KEY_SECRET', 'CHANGE_ME');
// Set this in Razorpay Dashboard -> Settings -> Webhooks, same string here.
define('RAZORPAY_WEBHOOK_SECRET', 'CHANGE_ME');

// ---- Security ----------------------------------------------------------
// Run: php -r "echo bin2hex(random_bytes(32));"  and paste the result here.
define('APP_SECRET', 'CHANGE_ME_32_BYTE_RANDOM_HEX_STRING');

// Session cookie lifetime (seconds)
define('SESSION_LIFETIME', 60 * 60 * 8); // 8 hours

// Max failed login attempts allowed per identifier in LOGIN_THROTTLE_WINDOW
define('LOGIN_THROTTLE_MAX', 6);
define('LOGIN_THROTTLE_WINDOW', 15 * 60); // 15 minutes

// ---- Uploads -------------------------------------------------------
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5 MB
define('UPLOAD_DIR', __DIR__ . '/uploads');

// ---- Error display ---------------------------------------------------
// Set to false on the live production server.
define('APP_DEBUG', true);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

date_default_timezone_set('Asia/Kolkata');
