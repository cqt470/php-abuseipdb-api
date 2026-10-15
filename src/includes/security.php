<?php
    namespace Zexa\Includes;

    use Zexa\Includes\Cache;
    use Exception;

    include_once __DIR__."/index.php";
    include_once __DIR__."/../../env.php";

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

            // TODO: use more robust checks!
            $ip = $_SERVER["HTTP_CLIENT_IP"] ?? null;

            // TODO: potential string injection
            if($research != frequency_ip_search::Fallback) throw new Exception("Illegal enum value \"$research\"");
            if($ip) return $ip;

            return Security::research_ip();
        }

        /**
         * Retrieves the IP information using the AbuseIPDB API and stores it in the cache.
         *
         * @param string $ip the IP to check. If null, Security::get_ip() will be used.
         * @param boolean $skip_cache request data from AbuseIPDB API even if the IP has been cached?
         * @return ?array
         */
        public static function get_ip_info(?string $ip = null, bool $skip_cache = false): ?array{
            $cache = new Cache(__DIR__."/../../cache/ips.json");

            $caching = \ENABLE_CACHING;
            if(!$ip) $ip = Security::get_ip();
            if($cache->check_for_entry("ipAddress", $ip) && !$skip_cache && $caching) return $cache->get_entry("ipAddress", $ip);

            // retrieve data
            $key = \API_KEY_ABUSEIPDB;
            $context = stream_context_create([
                "http" => [
                    "method" => "GET",
                    "header" => "Key: $key\r\nAccept: application/json\r\n",
                    "ignore_errors" => true, // reads errors on 4xx and 5xx
                    "timeout" => 16,
                ]
            ]);

            $data = json_decode(file_get_contents("https://api.abuseipdb.com/api/v2/check?ipAddress=$ip&verbose&key=$key", false, $context), true);
            
            $cache->add_entry($data);

            return $data;
        }

        /**
         * Validates an IP to see if it could be valid
         *
         * @param string $ip
         * @return boolean
         */
        public static function validate_ip(string $ip): bool{
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        }
    }

    $security = new Security;