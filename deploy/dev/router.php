<?php

// Router for PHP's built-in server (local dev only, see bin/dev).
// Mirrors the Caddy/.htaccess setup: real files are served as-is, everything else goes to index.php.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#(^|/)\.(env|git)#', $path)) {
    http_response_code(404);

    return true;
}

if ($path !== '/' && is_file(__DIR__ . '/../..' . $path)) {
    return false;
}

require __DIR__ . '/../../index.php';
