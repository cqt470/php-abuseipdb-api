<?php
    namespace Zexa\Includes;

    use Exception;

    include_once __DIR__."/index.php";

    class Cache{
        private string $filepath;

        /**
         * Manages the cache
         *
         * @param string $filepath the filephat of the cache to handle
         */
        public function __construct(string $filepath){
            if(!file_exists($filepath)) throw new Exception("File doesn't exist.");

            $this->filepath = $filepath;
        }

        /**
         * Does a key identifier with a specified value exist in the cache?
         *
         * @param string $key the key
         * @param string $value the value
         * @todo add more $value datatypes
         * @return boolean
         */
        public function check_for_entry(string $key, string $value): bool{
            $data = json_decode(file_get_contents($this->filepath), true) ?? [];
            foreach($data as $ip){
                if($ip["added_at"] < (time() - \CACHE_TTL)){
                    $this->delete_entry($key, $value);

                    Webhook::use_discord([
                        "title" => "TTL Reached",
                        "description" => "The IP Address \"$value\" has expired and has been deleted from cache.",
                        "color" => "#444444"
                    ]);

                    return false;
                }
                if($ip[$key] == $value) return true;
            }

            return false;
        }

        /**
         * Adds an entry to the cache
         *
         * @param array $ip the IP, used as key
         * @return void
         */
        public function add_entry(array $ip): void{
            $data = json_decode(file_get_contents($this->filepath));

            // cache hit
            if($this->check_for_entry("ipAddress", $ip["data"]["ipAddress"])) return;
            $address = $ip["data"]["ipAddress"];

            $webhook = new Webhook();
            $webhook::use_discord([
                "title" => "New IP added to cache",
                "description" => "The ip \"$address\" was added to the cache.",
                "color" => "#2ecc71",
                "timestamp" => date("c")
            ]);

            $ip["data"]["added_at"] = time();
            $data[] = $ip["data"];
            file_put_contents($this->filepath, json_encode($data, JSON_UNESCAPED_SLASHES));
        }

        /**
         * Gets an entry based on a key and the value it has to have
         *
         * @param string $key the key
         * @param string $value the searched value
         * @return array|null
         */
        public function get_entry(string $key, string $value): ?array{
            $data = json_decode(file_get_contents($this->filepath), true) ?? [];
            foreach($data as $ip){
                if($ip[$key] == $value) return $ip;
            }

            return null;
        }

        /**
         * Deletes an entry from the cache
         *
         * @param string $key the key that has to be searched
         * @param string $value the value that the key needs to have
         * @return void
         */
        public function delete_entry(string $key, string $value): void{
            $new = [];
            $data = json_decode(file_get_contents($this->filepath), true) ?? [];

            foreach($data as $k => $ip){
                if($ip[$key] == $value) continue;
                $new[$k] = $ip;
            }

            file_put_contents($this->filepath, json_encode($new));
        }
    }