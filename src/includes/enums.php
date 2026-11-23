<?php
    namespace Zexa\Includes;
    
    enum log_priority: string{
        case debug = "debug";
        case info = "info";
        case warning = "warning";
        case error = "error";
        case critical = "critical";
    }

    /**
     * AbuseIPDB report categories.
     * @see https://www.abuseipdb.com/categories
     */
    enum abuse_category: int{
        case dns_compromise = 1;
        case dns_poisoning = 2;
        case fraud_orders = 3;
        case ddos_attack = 4;
        case ftp_brute_force = 5;
        case ping_of_death = 6;
        case phishing = 7;
        case fraud_voip = 8;
        case open_proxy = 9;
        case web_spam = 10;
        case email_spam = 11;
        case blog_spam = 12;
        case vpn_ip = 13;
        case port_scan = 14;
        case hacking = 15;
        case sql_injection = 16;
        case spoofing = 17;
        case brute_force = 18;
        case bad_web_bot = 19;
        case exploited_host = 20;
        case web_app_attack = 21;
        case ssh = 22;
        case iot_targeted = 23;

        /**
         * Human readable name, as shown on abuseipdb.com
         */
        public function label(): string{
            return match($this){
                self::dns_compromise => "DNS Compromise",
                self::dns_poisoning => "DNS Poisoning",
                self::fraud_orders => "Fraud Orders",
                self::ddos_attack => "DDoS Attack",
                self::ftp_brute_force => "FTP Brute-Force",
                self::ping_of_death => "Ping of Death",
                self::phishing => "Phishing",
                self::fraud_voip => "Fraud VoIP",
                self::open_proxy => "Open Proxy",
                self::web_spam => "Web Spam",
                self::email_spam => "Email Spam",
                self::blog_spam => "Blog Spam",
                self::vpn_ip => "VPN IP",
                self::port_scan => "Port Scan",
                self::hacking => "Hacking",
                self::sql_injection => "SQL Injection",
                self::spoofing => "Spoofing",
                self::brute_force => "Brute-Force",
                self::bad_web_bot => "Bad Web Bot",
                self::exploited_host => "Exploited Host",
                self::web_app_attack => "Web App Attack",
                self::ssh => "SSH",
                self::iot_targeted => "IoT Targeted",
            };
        }

        /**
         * The category with this id, or null for anything that is not a known id
         * (non integers included, so client input can be passed as is).
         */
        public static function from_input(mixed $id): ?self{
            return is_int($id) ? self::tryFrom($id) : null;
        }
    }


    /**
     * Kind of a Discord notification. When notifications are queued,
     * the cron flush groups them by event (e.g. "12 failed authentications from 3 IPs").
     */
    enum discord_event: string{
        case failed_auth = "failed_auth";
        case cache_added = "cache_added";
        case cache_expired = "cache_expired";
        case report_result = "report_result";
        case generic = "generic";
    }
