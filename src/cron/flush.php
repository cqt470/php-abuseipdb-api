<?php
    // Sends what was queued while ENABLE_QUEUE is on (Discord notifications, reports).
    // Command line only, schedule it with cron, e.g. every 10 minutes:
    //
    //     */10 * * * * php /path/to/src/cron/flush.php
    namespace Zexa\Cron;

    if(PHP_SAPI !== "cli"){
        http_response_code(403);
        exit("This script runs from the command line only.\n");
    }

    include_once __DIR__."/../includes/index.php";

    use Zexa\Includes\Flusher;
    use Zexa\Includes\Logger;
    use Zexa\Includes\log_priority;

    $logger = new Logger(\LOGS_DIR);

    $dir = rtrim(\QUEUE_DIR, "/\\");
    if(!is_dir($dir)){
        $logger->log("queue", log_priority::error, "Flush skipped: the queue folder doesn't exist ($dir).");
        fwrite(STDERR, "The queue folder doesn't exist: $dir\n");
        exit(1);
    }

    // a flush that takes longer than the cron interval must not overlap with the next one
    $lock = fopen("$dir/flush.lock", "c");
    if($lock === false || !flock($lock, LOCK_EX | LOCK_NB)){
        $logger->log("queue", log_priority::info, "Flush skipped: another flush is running.");
        echo "Another flush is running.\n";
        exit(0);
    }

    try{
        $stats = (new Flusher())->run();
        $logger->log("queue", log_priority::info, "Flush done: ".json_encode($stats));
        echo json_encode($stats), "\n";
    }finally{
        flock($lock, LOCK_UN);
        fclose($lock);
    }
