<?php
    // Usage: php cache-writer.php <cache file> <octet> <count>
    require __DIR__.'/../../bootstrap.php';

    [, $file, $octet, $count] = $argv;
    $cache = new \Zexa\Includes\Cache($file);

    for ($i = 1; $i <= (int)$count; $i++) {
        $cache->add_entry(['data' => ['ipAddress' => "10.$octet.0.$i"]]);
    }
