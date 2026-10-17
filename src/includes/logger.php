<?php
    include_once __DIR__."/index.php";
    
    use Zexa\Includes\log_priority;

    class Logger{
        private string $folderpath;

        public function __construct(string $folderpath){
            $path = realpath($folderpath);
            if(!$path || !is_dir($path)) throw new Exception("Folder doesn't exist: $folderpath (resolved: ".($path ?: "realpath failed").")");

            $this->folderpath = $path;
        }

        /**
         * Checks if a file exists and creates if it doesn't exist
         *
         * @param string $filepath
         * @return void
         */
        private function check_for_file(string $filepath){
            
        }

        public function log(string $category, log_priority $priority, string $text){
            $this->check_for_file($priority->value);
        }
    }