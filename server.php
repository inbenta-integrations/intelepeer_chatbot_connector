<?php

require "vendor/autoload.php";

use Inbenta\IntelepeerConnector\IntelepeerConnector;

//Instance new IntelepeerConnector
$appPath=__DIR__.'/';
$app = new IntelepeerConnector($appPath);

//Handle the incoming request
$app->handleRequest();
