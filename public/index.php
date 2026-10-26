<?php

declare(strict_types=1);

/*
 * SmartPOS API front controller. Every HTTP request enters here.
 */

use App\Core\Request;

/** @var \App\Core\Kernel $kernel */
$kernel = require dirname(__DIR__) . '/bootstrap/app.php';

$kernel->handle(Request::capture())->send();
