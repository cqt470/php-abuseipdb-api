<?php
    namespace Zexa\Includes;

    use Zexa\Includes\Cache;
    use Exception;

    // guard for phpunit
    if(!defined("API_KEY_ABUSEIPDB")){
        include_once __DIR__."/../../env.php";
    }

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
        
        private static ?Logger $logger = null;

        private static function logger(): Logger{
            return self::$logger ??= new Logger(\LOGS_DIR);
        }

        /**
         * Gets the real IP of the user safely.
         * Supports Cloudflare integration and standard direct connections.
         *
         * @return string
         */
        public static function get_ip(): ?string{
            $remote = $_SERVER["REMOTE_ADDR"] ?? null;
            $cloudflare_ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? null;
            $general_proxy = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? null;

            // no usable IP at all
            if(empty($remote) || !self::validate_ip($remote)){
                return "127.0.0.1";
            }

            // if is not a trusted proxy IP, return it instead of reading cloudflare's/proxy's
            if(!self::is_trusted_proxy($remote)) return $remote;

            // the origin IP is trusted so the header it transports can be trusted
            if(!empty($cloudflare_ip) && self::validate_ip($cloudflare_ip)) return $cloudflare_ip;
            if(!empty($general_proxy)){
                $first = trim(explode(",", $general_proxy)[0]);
                if(self::validate_ip($first)) return $first;
            }

            // trusted proxy but no usable client IP in headers
            return $remote;
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

        public static function is_trusted_proxy(string $ip): bool{
            foreach(self::get_trusted_proxies() as $cidr){
                if(self::ip_in_cidr($ip, $cidr)) return true;
            }

            return false;
        }

        private static function get_trusted_proxies(): array{
            if(!defined("TRUSTED_PROXIES")) return self::DEFAULT_TRUSTED_PROXIES;
            if(empty(\TRUSTED_PROXIES)) return self::DEFAULT_TRUSTED_PROXIES;
            return \TRUSTED_PROXIES;
        }

        /**
         * Checks wether a given IP is part of a given CIDR. Supports both IPv4 and IPv6.
         *
         * @param string $ip Origin IP
         * @param string $cidr The CIDR
         * @return boolean
         */
        public static function ip_in_cidr(string $ip, string $cidr): bool{
            if(str_contains($cidr, '/')){
                [$subnet, $mask] = explode('/', $cidr, 2);
            } else {
                $subnet = $cidr;
                $mask = str_contains($ip, ':') ? 128 : 32;
            }
            $mask = (int) $mask;

            $ip_bin     = @inet_pton($ip);
            $subnet_bin = @inet_pton($subnet);
            if($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin)){
                return false;
            }

            $bytes = intdiv($mask, 8);
            $bits  = $mask % 8;

            if($bytes > 0 && substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)){
                return false;
            }
            if($bits === 0) return true;

            $mask_byte = chr((0xFF << (8 - $bits)) & 0xFF);
            return ($ip_bin[$bytes] & $mask_byte) === ($subnet_bin[$bytes] & $mask_byte);
        }

        /**
         * Retrieves the IP information using the AbuseIPDB API and stores it in the cache.
         *
         * @param string $ip the IP to check. If null, Security::get_ip() will be used.
         * @param boolean $skip_cache request data from AbuseIPDB API even if the IP has been cached?
         * @return array|int
         */
        public static function get_ip_info(?string $ip = null, bool $skip_cache = false): array|int{
            $cache = new Cache(\CACHE_FILE);

            $caching = \ENABLE_CACHING;
            if(!$ip) $ip = Security::get_ip();
            if($cache->check_for_entry("ipAddress", $ip) && !$skip_cache && $caching){
                self::logger()->log("cache", log_priority::info, "IP cache hit: $ip");
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

            $http_code = 0;
            if(isset($http_response_header) && preg_match("{HTTP\/\S*\s(\d{3})}", $http_response_header[0], $matches)) $http_code = (int)$matches[1];
            switch($http_code){
                case "200":
                    $cache->add_entry($data);
                    self::logger()->log("cache", log_priority::info, "IP address added to cache: $ip");
                    return $data;
                default:
                    self::logger()->log("cache", log_priority::warning, "Got a \"$http_code\" HTTP code from the API");
                    return $http_code;
            }
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