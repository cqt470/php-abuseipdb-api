<?php
    /**
     * @see https://stackoverflow.com/questions/34034730/how-to-enable-color-for-php-cli
     */
    $colors = [
        "default" => "\e[39m",
        "black" => "\e[30m",
        "red" => "\e[31m",
        "green" => "\e[32m",
        "yellow" => "\e[33m",
        "blue" => "\e[34m",
        "magenta" => "\e[35m",
        "cyan" => "\e[36m",
        "light_gray" => "\e[37m",
        "dark_gray" => "\e[90m",
        "light_red" => "\e[91m",
        "light_green" => "\e[92m",
        "light_yellow" => "\e[93m",
        "light_blue" => "\e[94m",
        "light_magenta" => "\e[95m",
        "light_cyan" => "\e[96m",
        "white" => "\e[97m",
    ];

    echo $colors["default"]."Test your check API.\n";