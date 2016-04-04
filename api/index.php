<?php
/**
 * User: NaeemM
 * Date: 30/03/2016
 */

//$baseDir = realpath(__DIR__ . '/..');


require '../vendor/autoload.php';
include_once 'DmtController.php';

$app = new Slim\App();

$app->get('/users','\DmtController:getName');
$app->get('/status','\DmtController:getStatus');
$app->get('/list','\DmtController:getList');
$app->get('/statistics','\DmtController:getStatistics');

$app->post('/datamapping/{provider}/{batch}/{action}','\DmtController:dataMapping');
$app->get('/datamapping/{provider}/{batch}/fetch','\DmtController:fetchMappingResult');

$app->run();