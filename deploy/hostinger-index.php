<?php
// Copy this file as public_html/index.php when flujo/ is outside public_html/.
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
$project = dirname(__DIR__).'/flujo';
if (file_exists($maintenance = $project.'/storage/framework/maintenance.php')) {
    require $maintenance;
}
require $project.'/vendor/autoload.php';
$app = require_once $project.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
