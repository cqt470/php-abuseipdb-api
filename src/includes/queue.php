<?php
    namespace Zexa\Includes;

    use Exception;
    use Zexa\Includes\Logger;

    /**
     * A JSON file used as a FIFO queue, with the same locking scheme as Cache:
     * every change is a read-modify-write cycle under an exclusive flock(),
     * and a corrupted file is never overwritten.
     *
     * Items are flushed by the cron script src/cron/flush.php.
     */
    class Queue{
        public const DISCORD = "discord";
        public const REPORTS = "reports";

        private string $filepath;
        private Logger $logger;

        /**
         * @param string $filepath the queue file, created (as an empty list) if missing
         */
        public function __construct(string $filepath){
            $dir = dirname($filepath);
            if(!is_dir($dir)) throw new Exception("Queue folder doesn't exist: $dir");
            if(!file_exists($filepath) && file_put_contents($filepath, "[]", LOCK_EX) === false){
                throw new Exception("Cannot create the queue file: $filepath");
            }

            $this->filepath = $filepath;
            $this->logger = new Logger(\LOGS_DIR);
        }

        /**
         * Is queueing enabled? An env.php without ENABLE_QUEUE (older versions) means no.
         */
        public static function enabled(): bool{
            return defined("ENABLE_QUEUE") && \ENABLE_QUEUE;
        }

        /**
         * Opens one of the named queues in QUEUE_DIR
         *
         * @param string $name Queue::DISCORD or Queue::REPORTS
         */
        public static function open(string $name): self{
            return new self(rtrim(\QUEUE_DIR, "/\\")."/$name.json");
        }

        /**
         * Appends an item to the queue
         *
         * @param array $item
         * @return bool false if the queue file is corrupted (nothing was written)
         */
        public function push(array $item): bool{
            $item["queued_at"] = time();

            return $this->transaction(function(array $items) use ($item): array{
                $items[] = $item;
                return $items;
            });
        }

        /**
         * Appends an item, or merges it into the queued item having the same value for $key
         *
         * @param string $key the key that identifies an item
         * @param array $item the new item, it must contain $key
         * @param callable(array $queued, array $item): array $merge returns the merged item
         * @return bool false if the queue file is corrupted (nothing was written)
         */
        public function push_or_merge(string $key, array $item, callable $merge): bool{
            $item["queued_at"] = time();

            return $this->transaction(function(array $items) use ($key, $item, $merge): array{
                foreach($items as $i => $queued){
                    if(is_array($queued) && ($queued[$key] ?? null) === ($item[$key] ?? null)){
                        $merged = $merge($queued, $item);
                        $merged["queued_at"] = $queued["queued_at"] ?? $item["queued_at"]; // keep the original position/age
                        $items[$i] = $merged;
                        return $items;
                    }
                }

                $items[] = $item;
                return $items;
            });
        }

        /**
         * Atomically removes and returns the oldest items
         *
         * @param int|null $limit how many items at most, null for all of them
         * @return array the removed items, oldest first
         */
        public function drain(?int $limit = null): array{
            $taken = [];
            $this->transaction(function(array $items) use ($limit, &$taken): ?array{
                if($items === []) return null;

                $taken = array_slice($items, 0, $limit);
                return array_slice($items, count($taken));
            });

            return array_values(array_filter($taken, "is_array"));
        }

        /**
         * Puts items back at the head of the queue (e.g. after a temporary failure),
         * before anything queued in the meantime
         *
         * @param array $items
         * @return bool false if the queue file is corrupted (nothing was written)
         */
        public function requeue(array $items): bool{
            if($items === []) return true;

            return $this->transaction(fn(array $queued): array => array_merge(array_values($items), $queued));
        }

        /**
         * Number of queued items (0 if the file is corrupted)
         */
        public function count(): int{
            $fp = fopen($this->filepath, "r");
            if($fp === false) return 0;

            try{
                flock($fp, LOCK_SH);
                return count($this->decode(stream_get_contents($fp)) ?? []);
            }finally{
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        /**
         * Read-modify-write under an exclusive lock
         *
         * @param callable(array): ?array $modify returns the new items or null to skip writing
         * @return bool false if the file is corrupted
         */
        private function transaction(callable $modify): bool{
            $fp = fopen($this->filepath, "c+");
            if($fp === false) throw new Exception("Cannot open the queue file.");

            try{
                if(!flock($fp, LOCK_EX)) throw new Exception("Cannot lock the queue file.");

                $items = $this->decode(stream_get_contents($fp));
                if($items === null) return false; // corrupted file: never overwrite it

                $new = $modify($items);
                if($new === null) return true;

                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode(array_values($new), JSON_UNESCAPED_SLASHES));
                fflush($fp);
                return true;
            }finally{
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        private function decode(string|false $raw): ?array{
            if($raw === false) return null;
            if(trim($raw) === "") return [];

            $items = json_decode($raw, true);
            if(!is_array($items) || !array_is_list($items)){
                $this->logger->log("queue", log_priority::critical, "Queue file is corrupted, it will not be overwritten: {$this->filepath}");
                return null;
            }

            return $items;
        }
    }
