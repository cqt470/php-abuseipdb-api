<?php
    // Usage: php queue-writer.php <queue file> <writer id> <count>
    // Pushes <count> items; every second item is drained and requeued, to mix the operations.
    require __DIR__.'/../../bootstrap.php';

    [, $file, $writer, $count] = $argv;
    $queue = new \Zexa\Includes\Queue($file);

    for ($i = 1; $i <= (int)$count; $i++) {
        $queue->push(['writer' => (int)$writer, 'n' => $i]);
        if ($i % 2 === 0) $queue->requeue($queue->drain(1));
    }
