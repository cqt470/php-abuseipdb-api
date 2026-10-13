<?php
    namespace Zexa\Services;

    include_once __DIR__."/includes/index.php";

    use Zexa\Includes\frequency_ip_search;
    use Zexa\Includes\Security;

    echo Security::get_ip(frequency_ip_search::Fallback);