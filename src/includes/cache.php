<?php
    namespace Zexa\Includes;
    
    class Cache{
        private string $filepath;

        /**
         * Manages the cache
         *
         * @param string $filepath the filephat of the cache to handle
         */
        public function __construct(string $filepath){
            if(!file_exists($filepath)) file_put_contents($filepath, "");

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
    }