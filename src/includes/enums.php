<?php
    namespace Zexa\Includes;
    
    enum log_priority: string{
        case debug = "debug";
        case info = "info";
        case warning = "warning";
        case error = "error";
        case critical = "critical";
    }