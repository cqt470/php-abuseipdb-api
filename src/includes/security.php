<?php
    namespace Zexa\Includes;

    class Security{
        public static function get_ip(?bool $research = false): string|array{
            // not production-ready, may be a proxy
            return $_SERVER["HTTP_CLIENT_IP"] ?? "0.0.0.0";
        }
    }

    $security = new Security;