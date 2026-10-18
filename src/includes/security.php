<?php
    namespace Zexa\Includes;

    use Zexa\Includes\Cache;
    use Exception;

    // guard for phpunit
    if(!defined("API_KEY_ABUSEIPDB")){
        include_once __DIR__."/../../env.php";
    }
    
    use Zexa\Includes\Logger;
    $logger = new Logger(\LOGS_DIR);

    class Security{
        /**
         * Default trusted reverse proxies (official Cloudflare ranges).
         * Override by defining TRUSTED_PROXIES in env.php if you sit behind another CDN/proxy.
         * @see https://www.cloudflare.com/he-il/ips/
         */
        private const DEFAULT_TRUSTED_PROXIES = [
            "103.21.244.0/22", "103.22.200.0/22", "103.31.4.0/22", "141.101.64.0/18", "108.162.192.0/18", "190.93.240.0/20", "188.114.96.0/20",
            "197.234.240.0/22", "198.41.128.0/17", "162.158.0.0/15", "104.16.0.0/13", "104.24.0.0/14", "172.64.0.0/13", "131.0.72.0/22", "173.245.48.0/20",

            "2400:cb00::/32", "2405:b500::/32", "2405:8100::/32", "2a06:98c0::/29", "2c0f:f248::/32",
            "2606:4700::/32", "2803:f800::/32"
        ];

        /**
         * Gets the real IP of the user safely.
         * Supports Cloudflare integration and standard direct connections.
         *
         * @return string
         */
        public static function get_ip(): ?string{
            // cloudflare header
            if(!empty($_SERVER["HTTP_CF_CONNECTING_IP"])){
                $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
                if(Security::validate_ip($ip)) return $ip;
            }

            // PHP header
            if(!empty($_SERVER["REMOTE_ADDR"])){
                $ip = $_SERVER["REMOTE_ADDR"];
                if(Security::validate_ip($ip)) return $ip;
            }

            // fallback
            return "127.0.0.1";
        }

        /**
         * Retrieves the IP information using the AbuseIPDB API and stores it in the cache.
         *
         * @param string $ip the IP to check. If null, Security::get_ip() will be used.
         * @param boolean $skip_cache request data from AbuseIPDB API even if the IP has been cached?
         * @return ?array
         */
        public static function get_ip_info(?string $ip = null, bool $skip_cache = false): ?array{
            global $logger;
            $cache = new Cache(\CACHE_FILE);

            $caching = \ENABLE_CACHING;
            if(!$ip) $ip = Security::get_ip();
            if($cache->check_for_entry("ipAddress", $ip) && !$skip_cache && $caching){
                $logger->log("cache", log_priority::info, "IP cache hit: $ip");
                return $cache->get_entry("ipAddress", $ip);
            }

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

            $data = json_decode(file_get_contents(\ABUSEIPDB_BASE_URL."/check?ipAddress=$ip&verbose", false, $context), true);
            
            $cache->add_entry($data);
            $logger->log("cache", log_priority::info, "IP address added to cache: $ip");

            return $data;
        }

        /**
         * Validates an IP to see if it could be valid
         *
         * @param string $ip
         * @return boolean
         */
        public static function validate_ip(string $ip): bool{
            return filter_var($ip, FILTER_VALIDATE_IP);
        }

        /**
         * Returns the SHA256 hash of a string using the SALT_SHA256 salt or the specified one, if provided
         *
         * @param string $input the string
         * @param ?string $custom_salt the salt. If not provided, SALT_SHA256 will be used
         * @return string
         */
        public static function sha256(string $input, ?string $custom_salt = null): string{
            $salt = $custom_salt ?? \SALT_SHA256;
            return hash("sha256", $input.$salt);
        }
    }

    $security = new Security;