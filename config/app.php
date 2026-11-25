<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'SERVE');
define('APP_FULL_NAME', 'SERVE - Service Marketplace & Management System');
define('BASE_URL', '/serve');
define('BASE_PATH', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');