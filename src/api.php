<?php
    namespace Zexa\Services;

    include_once __DIR__."/../env.php";
    include_once __DIR__."/includes/index.php";

    use Zexa\Includes\Security;

    echo json_encode(Security::get_ip_info());