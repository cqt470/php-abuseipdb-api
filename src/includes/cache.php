<?php
    namespace Zexa\Includes;

    use Exception;
    use Zexa\Includes\Logger;

    class Cache{
        private string $filepath;
        private Logger $logger;

        /**
         * Manages the cache
         *
         * @param string $filepath the filepath of the cache to handle
         */
        public function __construct(string $filepath){
            if(!file_exists($filepath)) throw new Exception("File doesn't exist.");
            $this->logger = new Logger(\LOGS_DIR);

            $this->filepath = $filepath;
        }

        /**
         * Does a key identifier with a specified value exist in the cache?
         * Expired entries found during the scan are evicted.
         *
         * The scan runs under a shared lock, so concurrent checks never wait for
         * each other; the exclusive lock is taken only when something must be evicted.
         *
         * @param string $key the key
         * @param string $value the value
         * @todo add more $value datatypes
         * @return boolean
         */
        public function check_for_entry(string $key, string $value): bool{
            return $this->find_entry($key, $value) !== null;
        }

        /**
         * Returns the valid (not expired) entry whose key has the specified value.
         * Expired entries found during the scan are evicted.
         *
         * One read of the file is enough: the scan runs under a shared lock, and the
         * exclusive lock is taken only when something must be evicted.
         *
         * @param string $key the key
         * @param string $value the value
         * @return array|null the entry, or null if it is not cached (or the file is corrupted)
         */
        public function find_entry(string $key, string $value): ?array{
            $data = $this->read();
            if($data === null) return null; // corrupted file, already logged by decode()

            [$keep, $stale] = $this->split_expired($data, $key);
            if(!$stale) return self::match($keep, $key, $value);

            // something expired: redo the scan under the exclusive lock, the file may have changed meanwhile
            $found = null;
            $expired = [];
            $this->transaction(function(array $data) use ($key, $value, &$found, &$expired): ?array{
                [$keep, $expired] = $this->split_expired($data, $key);
                $found = self::match($keep, $key, $value);

                return $expired ? $keep : null; // rewrite only if something was evicted
            });

            $this->notify_expired($expired);
            return $found;
        }

        /**
         * First entry whose key has the specified value.
         */
        private static function match(array $entries, string $key, string $value): ?array{
            foreach($entries as $ip){
                if(is_array($ip) && ($ip[$key] ?? null) == $value) return $ip;
            }

            return null;
        }

        /**
         * Adds an entry to the cache, replacing an existing one for the same address
         *
         * @param array $ip the IP, used as key
         * @return void
         */
        public function add_entry(array $ip): void{
            // malformed payload, likely due to a 4xx or 5xx response
            if(!isset($ip["data"]["ipAddress"])) return;

            $address = $ip["data"]["ipAddress"];
            $entry = $ip["data"];
            $entry["added_at"] = time();

            $refresh = false;
            $written = false;
            $expired = [];

            $this->transaction(function(array $data) use ($address, $entry, &$refresh, &$written, &$expired): array{
                [$keep, $expired] = $this->split_expired($data, "ipAddress");

                $new = [];
                foreach($keep as $item){
                    // already cached: replace the old entry with the fresh data
                    if(is_array($item) && ($item["ipAddress"] ?? null) == $address){
                        $refresh = true;
                        continue;
                    }
                    $new[] = $item;
                }
                $new[] = $entry;
                $written = true;

                return $new;
            });

            $this->notify_expired($expired);
            if(!$written) return; // corrupted file, nothing was saved

            if($refresh){
                $this->logger->log("cache", log_priority::info, "IP refreshed in cache ($address).");
                return;
            }

            $this->logger->log("cache", log_priority::info, "IP added to cache ($address).");
            Webhook::use_discord([
                "title" => "New IP added to cache",
                "description" => "The ip `$address` was added to the cache",
                "color" => "#2ecc71",
                "timestamp" => date("c")
            ], discord_event::cache_added, ["ip" => $address]);
        }

        /**
         * Gets an entry based on a key and the value it has to have
         *
         * @param string $key the key
         * @param string $value the searched value
         * @return array|null
         */
        public function get_entry(string $key, string $value): ?array{
            return self::match($this->read() ?? [], $key, $value);
        }

        /**
         * Deletes an entry from the cache
         *
         * @param string $key the key that has to be searched
         * @param string $value the value that the key needs to have
         * @return void
         */
        public function delete_entry(string $key, string $value): void{
            $this->transaction(function(array $data) use ($key, $value): array{
                $new = [];
                foreach($data as $ip){
                    if(is_array($ip) && ($ip[$key] ?? null) == $value) continue;
                    $new[] = $ip;
                }

                return $new;
            });
        }

        /**
         * Separates expired entries from valid ones.
         * Malformed entries (no key) are kept untouched; a missing added_at counts as expired.
         *
         * @return array{0: array, 1: array} [entries to keep, values of the expired entries]
         */
        private function split_expired(array $data, string $key): array{
            $keep = [];
            $expired = [];
            $limit = time() - \CACHE_TTL;

            foreach($data as $ip){
                if(is_array($ip) && isset($ip[$key]) && (!is_int($ip["added_at"] ?? null) || $ip["added_at"] < $limit)){
                    $expired[] = $ip[$key];
                    continue;
                }
                $keep[] = $ip;
            }

            return [$keep, $expired];
        }

        /**
         * Logs and notifies evicted entries. Called outside the file lock,
         * so a slow Discord never blocks other requests.
         */
        private function notify_expired(array $expired): void{
            foreach($expired as $value){
                $this->logger->log("cache", log_priority::info, "IP expired ($value).");
                Webhook::use_discord([
                    "title" => "TTL Reached",
                    "description" => "The IP Address `$value` has expired and has been deleted from cache",
                    "color" => "#444444"
                ], discord_event::cache_expired, ["ip" => $value]);
            }
        }

        /**
         * Runs a read-modify-write cycle under an exclusive lock, so concurrent
         * requests cannot overwrite each other's changes.
         *
         * @param callable(array): ?array $modify receives the entries, returns the new ones or null to skip writing
         * @return void
         */
        private function transaction(callable $modify): void{
            $fp = fopen($this->filepath, "c+");
            if($fp === false) throw new Exception("Cannot open the cache file.");

            try{
                if(!flock($fp, LOCK_EX)) throw new Exception("Cannot lock the cache file.");

                $data = $this->decode(stream_get_contents($fp));
                if($data === null) return; // corrupted file: never overwrite it

                $new = $modify($data);
                if($new === null) return;

                ftruncate($fp, 0);
                rewind($fp);
                // array_values keeps the file a JSON list, not an object
                fwrite($fp, json_encode(array_values($new), JSON_UNESCAPED_SLASHES));
                fflush($fp);
            }finally{
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        /**
         * Reads the cache file under a shared lock.
         *
         * @return array|null the entries, or null if the file is corrupted
         */
        private function read(): ?array{
            $fp = fopen($this->filepath, "r");
            if($fp === false) return null;

            try{
                flock($fp, LOCK_SH);
                return $this->decode(stream_get_contents($fp));
            }finally{
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        /**
         * Decodes the raw cache content.
         *
         * @return array|null the entries, or null if the content is corrupted
         */
        private function decode(string|false $raw): ?array{
            if($raw === false) return null;
            if(trim($raw) === "") return [];

            $data = json_decode($raw, true);
            if(!is_array($data)){
                $this->logger->log("cache", log_priority::critical, "Cache file is corrupted, it will not be overwritten: {$this->filepath}");
                return null;
            }

            return $data;
        }
    }
