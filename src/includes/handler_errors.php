<?php
    declare(strict_types=1);

    use Zexa\Includes\log_priority;
    use Zexa\Includes\Logger;

    set_error_handler(function (int $severity, string $message, string $file, int $line): bool{
        if(!(error_reporting() && $severity)) return false; // respects @ and error_reporting
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(function (Throwable $e): void{
        $message = sprintf(
            "[%s] Uncaught %s: %s in %s:%d",
            date('c'), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()
        );

        error_log($message);

        http_response_code(500);
        header("Content-Type: application/json");
        echo json_encode(["message" => "internal server error! :(", "success" => false]);

        try{
            $logger = new Logger(\LOGS_DIR);
            $logger->log("errors", log_priority::critical, "Uncaught Exception. $message");
        }catch(\Throwable $e){
            return;
        }

        exit;
    });