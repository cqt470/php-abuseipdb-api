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
            return ["discord" => $this->flush_discord()];
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

            $groups = self::group($items);
            foreach($extra as $embed){
                // not queued yet: if it cannot be sent it is queued as a generic notification
                $groups[] = ["embed" => $embed, "items" => [["event" => discord_event::generic->value, "context" => [], "embed" => $embed]]];
            }
            if($groups === []) return $stats;

            $requeue = [];
            $failed = false;
            foreach(self::chunk($groups) as $i => $message){
                if(!$failed && $i < \DISCORD_MAX_PER_FLUSH){
                    if(Webhook::send_embeds(array_column($message, "embed"))){
                        $stats["messages"]++;
                        continue;
                    }
                    $failed = true; // Discord is down or rejecting: keep the rest for the next run
                }
                foreach($message as $group) array_push($requeue, ...$group["items"]);
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
                $new_length = $length + ($out ? 1 : 0) + mb_strlen($line);
                // always keep room for the "… and N more" line of the items after this one,
                // so the line that replaces them (written below) is guaranteed to fit
                $after = $total - $i - 1;
                $reserve = $after > 0 ? mb_strlen("\n… and $after more") : 0;

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
            return mb_strlen((string)($embed["title"] ?? "")) + mb_strlen((string)($embed["description"] ?? ""));
        }
    }
