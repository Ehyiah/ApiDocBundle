<?php

use Ehyiah\ApiDocBundle\Tests\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

$kernel = new TestKernel('dev', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
