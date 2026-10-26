<?php

declare(strict_types=1);

/*
 * Application bootstrap shared by public/index.php, bin/console and the test suite.
 * Loads env, boots Eloquent, registers routes and returns the HTTP Kernel.
 */

use App\Core\Kernel;
use App\Core\Router;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

$basePath = dirname(__DIR__);

require_once $basePath . '/vendor/autoload.php';

// Real environment variables (Render) win; .env only fills gaps in local dev.
Dotenv::createImmutable($basePath)->safeLoad();

$appConfig = require $basePath . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

// Eloquent ORM (standalone, outside Laravel)
$capsule = new Capsule();
$capsule->addConnection(require $basePath . '/config/database.php');
$capsule->setEventDispatcher(new Dispatcher(new Container()));
$capsule->setAsGlobal();
$capsule->bootEloquent();

$router = new Router();
(require $basePath . '/routes/api.php')($router);

$kernel = new Kernel($router);
foreach ((require $basePath . '/config/middleware.php') as $alias => $class) {
    $kernel->alias($alias, $class);
}

return $kernel;
