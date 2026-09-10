<?php
$file = __DIR__ . '/controllers/AdsAgentController.php';

if (function_exists('opcache_invalidate')) {
    $result = opcache_invalidate($file, true);
    echo 'opcache_invalidate: ' . ($result ? 'OK' : 'FAILED') . '<br>';
}

if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo 'opcache_reset: ' . ($result ? 'OK' : 'FAILED') . '<br>';
}

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    echo 'OPcache enabled: ' . ($status['opcache_enabled'] ? 'YES' : 'NO') . '<br>';
    echo 'Cached scripts: ' . $status['opcache_statistics']['num_cached_scripts'] . '<br>';
}

echo 'Done. File mtime: ' . date('Y-m-d H:i:s', filemtime($file));
