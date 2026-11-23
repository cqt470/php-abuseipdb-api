<?php
    namespace Zexa\Includes;

    /**
     * Sends what was queued while ENABLE_QUEUE is on. Run by src/cron/flush.php.
     *
     * Discord notifications are grouped by event ("12 failed authentications from 3 IPs")
     * and sent with up to 10 embeds per message.
     */
    class Flusher{
        private Logger $logger;

        public function __construct(){
            $this->logger = new Logger(\LOGS_DIR);
        }

        /**
         * Flushes every queue
         *
         * @return array statistics, printed by the cron script
         */
        public function run(): array{
            $reports = $this->flush_reports();
            $summary = self::reports_embed($reports);

            return [
                "reports" => ["sent" => count($reports["sent"]), "retry" => count($reports["retry"]), "discarded" => count($reports["discarded"])],
                "discord" => $this->flush_discord($summary === null ? [] : [$summary]),
            ];
        }

        /**
         * Merges a new report into the one already queued for the same IP:
         * categories are joined, different comments are concatenated (AbuseIPDB allows 1024 characters),
         * the timestamp of the first report is kept.
         */
        public static function merge_reports(array $queued, array $new): array{
            $categories = array_values(array_unique(array_merge((array)($queued["categories"] ?? []), (array)($new["categories"] ?? []))));

            $comments = array_values(array_unique(array_filter([(string)($queued["comment"] ?? ""), (string)($new["comment"] ?? "")], "strlen")));
            $comment = self::cut(implode("\n", $comments), self::MAX_COMMENT);

            return ["categories" => $categories, "comment" => $comment] + $queued;
        }

        /** AbuseIPDB limit for a report comment */
        public const MAX_COMMENT = 1024;

        /**
         * Sends the queued reports to AbuseIPDB, one /report per IP, at most REPORTS_MAX_PER_FLUSH.
         *
         * - 2xx: sent;
         * - network error, 5xx, 429: temporary, the report stays queued for the next flush.
         *   A 429 that is not the "same IP once in 15 minutes" rule is the daily limit:
         *   the remaining reports are not even tried;
         * - any other 4xx: permanent, the report is discarded (and logged).
         *
         * @return array{sent: array, retry: array, discarded: array} the reports in each outcome, discarded ones with "error"
         */
        public function flush_reports(): array{
            $result = ["sent" => [], "retry" => [], "discarded" => []];

            $queue = Queue::open(Queue::REPORTS);
            $items = $queue->drain(max(1, (int)\REPORTS_MAX_PER_FLUSH));

            $stop = false;
            $done = 0; // items whose outcome is known
            try{
                foreach($items as $item){
                    $ip = (string)($item["ip"] ?? "");
                    if($stop){
                        $result["retry"][] = $item;
                    }elseif($ip === "" || !Security::validate_ip($ip) || !is_array($item["categories"] ?? null)){
                        $result["discarded"][] = $item + ["error" => "malformed queued report"];
                        $this->logger->log("queue", log_priority::warning, "Discarded a malformed queued report: ".json_encode($item));
                    }else{
                        ["code" => $code, "info" => $info] = Security::report_ip($ip, $item["categories"], (string)($item["comment"] ?? ""), (string)($item["timestamp"] ?? date("c")));
                        $detail = (string)($info["errors"][0]["detail"] ?? "");

                        if($code >= 200 && $code < 300){
                            $result["sent"][] = $item;
                            $this->logger->log("api", log_priority::info, "Queued report sent ($ip).");
                        }elseif($code === 0 || $code === 429 || $code >= 500){
                            $result["retry"][] = $item;
                            $this->logger->log("api", log_priority::warning, "Queued report for $ip got HTTP $code, kept for the next flush. $detail");
                            if($code === 429 && !str_contains($detail, "15 minutes")) $stop = true;
                        }else{
                            $result["discarded"][] = $item + ["error" => "HTTP $code".($detail === "" ? "" : ": $detail")];
                            $this->logger->log("api", log_priority::warning, "Queued report for $ip discarded, HTTP $code. $detail");
                        }
                    }
                    $done++;
                }
            }finally{
                // retries go back to the head of the queue, before anything queued meanwhile;
                // after a crash, so do the drained reports that were never handled
                $queue->requeue(array_map(fn($item) => array_diff_key($item, ["error" => true]), [...$result["retry"], ...array_slice($items, $done)]));
            }
            return $result;
        }

        /**
         * The Discord summary of a reports flush, or null if nothing was processed
         */
        public static function reports_embed(array $result): ?array{
            $sent = count($result["sent"]);
            $retry = count($result["retry"]);
            $discarded = count($result["discarded"]);
            if($sent + $retry + $discarded === 0) return null;

            $lines = [];
            foreach($result["sent"] as $item) $lines[] = "✅ `{$item["ip"]}` ".self::category_names($item["categories"] ?? []);
            foreach($result["retry"] as $item) $lines[] = "⏳ `{$item["ip"]}` kept for the next flush";
            foreach($result["discarded"] as $item) $lines[] = "❌ `".($item["ip"] ?? "?")."` ".($item["error"] ?? "");

            return [
                "title" => "Reports: $sent sent, $retry kept for retry, $discarded discarded",
                "description" => self::describe($lines),
                "color" => $discarded > 0 ? "#e74c3c" : ($retry > 0 ? "#f39c12" : "#2ecc71"),
                "timestamp" => date("c"),
            ];
        }

        private static function category_names(array $ids): string{
            $names = array_map(fn($id) => abuse_category::from_input($id)?->label() ?? (string)$id, $ids);
            return $names === [] ? "" : "(".implode(", ", $names).")";
        }

        /**
         * Sends the queued Discord notifications, grouped by event.
         * When Discord fails, the notifications that were not delivered go back to the queue.
         *
         * @param array $extra embeds to send along with the queued ones
         * @return array{notifications: int, messages: int, requeued: int}
         */
        public function flush_discord(array $extra = []): array{
            $stats = ["notifications" => 0, "messages" => 0, "requeued" => 0];
            if(!\ENABLE_DISCORD_WEBHOOK) return $stats;

            $queue = Queue::open(Queue::DISCORD);
            $items = $queue->drain();
            // not queued yet: if they cannot be sent they are queued as generic notifications
            $extra_items = array_map(fn($embed) => ["event" => discord_event::generic->value, "context" => [], "embed" => $embed], $extra);

            $requeue = [];
            $messages = null;
            $next = 0; // first message not handled yet
            try{
                $groups = self::group($items);
                foreach($extra_items as $item) $groups[] = ["embed" => $item["embed"], "items" => [$item]];
                if($groups === []) return $stats;

                $messages = self::chunk($groups);
                $failed = false;
                foreach($messages as $i => $message){
                    if(!$failed && $i < \DISCORD_MAX_PER_FLUSH){
                        if(Webhook::send_embeds(array_column($message, "embed"))){
                            $stats["messages"]++;
                            $next = $i + 1;
                            continue;
                        }
                        $failed = true; // Discord is down or rejecting: keep the rest for the next run
                    }
                    foreach($message as $group) array_push($requeue, ...$group["items"]);
                    $next = $i + 1;
                }
            }catch(\Throwable $e){
                // a crash must not lose what was drained: everything not delivered goes back
                if($messages === null){
                    $requeue = [...$items, ...$extra_items];
                }else{
                    foreach(array_slice($messages, $next) as $message){
                        foreach($message as $group) array_push($requeue, ...$group["items"]);
                    }
                }
                $queue->requeue($requeue);
                $this->logger->log("queue", log_priority::error, "Discord flush crashed, ".count($requeue)." notifications kept in queue: ".$e->getMessage());
                throw $e;
            }

            $queue->requeue($requeue);
            $stats["notifications"] = count($items) + count($extra) - count($requeue);
            $stats["requeued"] = count($requeue);

            if($requeue) $this->logger->log("queue", log_priority::warning, count($requeue)." Discord notifications kept in queue for the next flush.");
            return $stats;
        }

        /**
         * Groups queued notifications by event. Failed authentications, cache additions and
         * expirations become one embed each; any other notification keeps its own embed.
         * A group of a single notification keeps the original embed.
         *
         * @param array $items queued items: {event, context, embed}
         * @return array list of {embed, items}
         */
        public static function group(array $items): array{
            $by_event = [];
            foreach($items as $item){
                if(!is_array($item) || !is_array($item["embed"] ?? null)) continue;
                $event = discord_event::tryFrom((string)($item["event"] ?? "")) ?? discord_event::generic;
                $by_event[$event->value][] = $item;
            }

            $groups = [];
            $grouped = [
                discord_event::failed_auth->value => ["%d failed authentications from %d IPs", "#ff0000"],
                discord_event::cache_added->value => ["%d IPs added to the cache", "#2ecc71"],
                discord_event::cache_expired->value => ["%d IPs expired from the cache", "#444444"],
            ];
            foreach($grouped as $event => [$title, $color]){
                $list = $by_event[$event] ?? [];
                if(count($list) === 1) $groups[] = ["embed" => $list[0]["embed"], "items" => $list];
                if(count($list) < 2) continue;

                // occurrences per IP, most frequent first
                $ips = [];
                foreach($list as $item){
                    $ip = (string)($item["context"]["ip"] ?? "unknown");
                    $ips[$ip] = ($ips[$ip] ?? 0) + 1;
                }
                uksort($ips, fn($a, $b) => [$ips[$b], (string)$a] <=> [$ips[$a], (string)$b]);

                $lines = [];
                foreach($ips as $ip => $count) $lines[] = $count > 1 ? "`$ip` × $count" : "`$ip`";

                $groups[] = [
                    "embed" => [
                        "title" => sprintf($title, count($list), count($ips)),
                        "description" => self::describe($lines),
                        "color" => $color,
                        "timestamp" => date("c"),
                    ],
                    "items" => $list,
                ];
            }

            foreach([discord_event::report_result->value, discord_event::generic->value] as $event){
                foreach($by_event[$event] ?? [] as $item) $groups[] = ["embed" => $item["embed"], "items" => [$item]];
            }

            return $groups;
        }

        /**
         * Joins lines into an embed description within Discord's limit,
         * ending with "… and N more" when they do not fit.
         *
         * @param string[] $lines
         * @param int $limit maximum length in characters
         */
        public static function describe(array $lines, int $limit = Webhook::MAX_DESCRIPTION): string{
            $lines = array_values($lines);
            $total = count($lines);
            $out = [];
            $length = 0;

            foreach($lines as $i => $line){
                $new_length = $length + ($out ? 1 : 0) + self::length($line);
                // always keep room for the "… and N more" line of the items after this one,
                // so the line that replaces them (written below) is guaranteed to fit
                $after = $total - $i - 1;
                $reserve = $after > 0 ? self::length("\n… and $after more") : 0;

                if($new_length + $reserve > $limit){
                    $out[] = "… and ".($total - $i)." more";
                    break;
                }
                $out[] = $line;
                $length = $new_length;
            }

            return implode("\n", $out);
        }

        /**
         * Splits groups into Discord messages: at most 10 embeds and 6000 characters each.
         *
         * @param array $groups list of {embed, items}
         * @return array list of messages, each a list of groups
         */
        public static function chunk(array $groups): array{
            $messages = [];
            $current = [];
            $chars = 0;

            foreach($groups as $group){
                $size = self::embed_chars($group["embed"]);
                if($current && (count($current) >= Webhook::MAX_EMBEDS || $chars + $size > Webhook::MAX_MESSAGE_CHARS)){
                    $messages[] = $current;
                    $current = [];
                    $chars = 0;
                }
                $current[] = $group;
                $chars += $size;
            }
            if($current) $messages[] = $current;

            return $messages;
        }

        private static function embed_chars(array $embed): int{
            return self::length((string)($embed["title"] ?? "")) + self::length((string)($embed["description"] ?? ""));
        }

        // The project has no dependencies, mbstring included (it is not installed on every server):
        // UTF-8 characters are counted and cut with PCRE, which is always part of PHP.

        /**
         * Length of a UTF-8 string in characters. Invalid UTF-8 is counted in bytes,
         * which is never less than its characters, so the limits still hold.
         */
        private static function length(string $text): int{
            $count = preg_match_all('/./su', $text);
            return $count === false ? strlen($text) : $count;
        }

        /**
         * The first $max characters of a UTF-8 string ($max up to 65535).
         * Invalid UTF-8 is cut in bytes.
         */
        private static function cut(string $text, int $max): string{
            if(preg_match('/^.{0,'.$max.'}/su', $text, $match) === 1) return $match[0];
            return substr($text, 0, $max);
        }
    }
