<?php
    // Usage: php cache-reader.php <cache file> <ip>
    // Prints "1" if the IP is cached, "0" otherwise.
    require __DIR__.'/../../bootstrap.php';

    [, $file, $ip] = $argv;
    echo (new \Zexa\Includes\Cache($file))->check_for_entry('ipAddress', $ip) ? '1' : '0';
