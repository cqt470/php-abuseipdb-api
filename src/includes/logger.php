<?php
    class Logger{
        private string $folderpath;

        public function __construct(string $folderpath){
            if(!file_exists($folderpath)) throw new Exception("File doesn't exist.");

            $this->folderpath = $folderpath;
        }
    }