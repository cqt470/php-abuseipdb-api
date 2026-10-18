<?php
    declare(strict_types=1);

    include_once __DIR__."/env.test.php";

    @mkdir(dirname(\CACHE_FILE), 0777, true);
    @mkdir(\LOGS_DIR, 0777, true);

    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $_SERVER['REQUEST_URI']    = $_SERVER['REQUEST_URI']    ?? '/';
    $_SERVER['REMOTE_ADDR']    = $_SERVER['REMOTE_ADDR']    ?? '127.0.0.1';
    
    include_once __DIR__."/../src/includes/index.php";