<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(__DIR__.'/../.env')) {
    $dotenv = new Dotenv();
    $dotenv->load(__DIR__.'/../.env');
}

$env = !empty(getenv('APP_ENV')) ? getenv('APP_ENV') : 'prod';
$debug = (bool) (getenv('APP_DEBUG') ?? ('prod' !== $env));

if(!in_array($env, ['dev', 'test', 'prod'])) {
    throw new InvalidArgumentException("APP_ENV should be one of ['dev', 'test' or 'prod'], '$env' is given.");
}

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $env;
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = $debug;
