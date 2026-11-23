# Shared enums

[`src/includes/enums.php`](../../../src/includes/enums.php) defines two backed
enums.

## `log_priority`

Backed string enum `Zexa\Includes\log_priority`.

| Case | Value |
| --- | --- |
| `debug` | `debug` |
| `info` | `info` |
| `warning` | `warning` |
| `error` | `error` |
| `critical` | `critical` |

The enum is used by [`Logger`](./logger.md). PHP 8.1 or newer is required.

## `abuse_category`

Backed int enum `Zexa\Includesbuse_category` with the
[AbuseIPDB report categories](https://www.abuseipdb.com/categories). `label()`
returns the name shown on abuseipdb.com; `from_input(mixed $id)` returns the
category for a known integer id and `null` for anything else (used to validate
the `report` action and to name the categories in `reportsSummary`).

| Id | Case | Label |
| --- | --- | --- |
| 1 | `dns_compromise` | DNS Compromise |
| 2 | `dns_poisoning` | DNS Poisoning |
| 3 | `fraud_orders` | Fraud Orders |
| 4 | `ddos_attack` | DDoS Attack |
| 5 | `ftp_brute_force` | FTP Brute-Force |
| 6 | `ping_of_death` | Ping of Death |
| 7 | `phishing` | Phishing |
| 8 | `fraud_voip` | Fraud VoIP |
| 9 | `open_proxy` | Open Proxy |
| 10 | `web_spam` | Web Spam |
| 11 | `email_spam` | Email Spam |
| 12 | `blog_spam` | Blog Spam |
| 13 | `vpn_ip` | VPN IP |
| 14 | `port_scan` | Port Scan |
| 15 | `hacking` | Hacking |
| 16 | `sql_injection` | SQL Injection |
| 17 | `spoofing` | Spoofing |
| 18 | `brute_force` | Brute-Force |
| 19 | `bad_web_bot` | Bad Web Bot |
| 20 | `exploited_host` | Exploited Host |
| 21 | `web_app_attack` | Web App Attack |
| 22 | `ssh` | SSH |
| 23 | `iot_targeted` | IoT Targeted |
