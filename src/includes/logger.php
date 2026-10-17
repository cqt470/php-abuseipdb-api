<?php
    include_once __DIR__."/index.php";
    
    use Zexa\Includes\log_priority;
    use Zexa\Includes\Security;

    class Logger{
        private string $folderpath;

        public function __construct(string $folderpath){
            $path = realpath($folderpath);
            if(!$path || !is_dir($path)) throw new Exception("Folder doesn't exist: $folderpath (resolved: ".($path ?: "realpath failed").")");

            $this->folderpath = $path;
        }

        public function log(string $category, log_priority $priority, string $text, bool $include_ip = false){
            $filepath = $this->folderpath."/$category.log";
            $time = date("c");
            $_priority = strtoupper($priority->value);
            $method = $_SERVER["REQUEST_METHOD"];
            $row = "[$_priority] $time - $method - $text";
            if($include_ip) $row .= " (Origin: ".Security::get_ip().")";
            //if(str_contains($filepath, "check")) var_dump($filepath, is_dir($this->folderpath), is_writable($this->folderpath));

            file_put_contents($filepath, "$row\n", FILE_APPEND);
        }
    }