<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once 'vendor/autoload.php';

$config = Zend\Config\Factory::fromFile('config.php', true);
$serviceManager = new \Zend\ServiceManager\ServiceManager();
$serviceManager->setService('pinboard-api', new PinboardAPI($config->pinboard_user, $config->pinboard_password));
$serviceManager->setService('wayback-machine', new PinboardArchive\WaybackMachine($serviceManager));

if (class_exists('Redis')) {
    $redis = new Redis;
    if ($redis->connect($config->redis_connection)) {
        $serviceManager->setService('redis', $redis);
    }
}

$main = new PinboardArchive\Main($serviceManager);
echo 'Available: ', $main->getAvailableCount(), PHP_EOL;
echo 'Unavailable: ', $main->getUnavailableCount(), PHP_EOL;
