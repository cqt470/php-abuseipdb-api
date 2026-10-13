<?php
    namespace Zexa\Includes;

    use Exception;

    class Security{
        /**
         * Returns the IP given by ifconfig.me/api
         *
         * @return string
         */
        public static function research_ip(): string{
            return file_get_contents("https://ifconfig.me/ip");
        }

        /**
         * Gets the IP of the user.
         *
         * @param frequency_ip_search $research always check for the IP using ifconfig, or only when not available?
         * @return string
         */
        public static function get_ip(frequency_ip_search $research = frequency_ip_search::Fallback): string{
            if($research == frequency_ip_search::Always) return Security::research_ip();

            $ip = $_SERVER["HTTP_CLIENT_IP"] ?? null;

            // TODO: potential string injection
            if($research != frequency_ip_search::Fallback) throw new Exception("Illegal enum value \"$research\"");
            // TODO: use more robust checks
            if($ip) return $ip;

            return Security::research_ip();
        }
    }

    $security = new Security;