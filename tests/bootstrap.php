<?php
    declare(strict_types=1);

    include_once __DIR__."/env.test.php";

    @mkdir(dirname(\CACHE_FILE), 0777, true);
    @mkdir(\LOGS_DIR, 0777, true);
    
    include_once __DIR__."/../src/includes/index.php";