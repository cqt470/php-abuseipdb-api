<?php
    namespace Zexa\Services;

    include_once __DIR__."/includes/security.php";

    use Zexa\Includes\Security;

    echo Security::get_ip();