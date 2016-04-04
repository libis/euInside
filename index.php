<?php
/**
 * User: NaeemM
 * Date: 30/03/2016
 */

require 'vendor/autoload.php';

$app = new Slim\App();

$app->get('/', function() use($app) {
    echo "Welcome to Slim 3.0 based API";
});

$app->run();