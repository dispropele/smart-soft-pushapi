<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {

    Request::setTrustedProxies(['127.0.0.1', '10.0.0.0/8'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
    
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
