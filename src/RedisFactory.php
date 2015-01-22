<?php

namespace PinboardArchive;

use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class RedisFactory implements AbstractFactoryInterface
{
    /**
     * Determine if we can create a service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        $connection = $serviceLocator->get('Config')->redis_connection;
        return ($name == 'Redis' && class_exists('Redis') && !empty($connection));
    }

    /**
     * Create service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     * @return mixed
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        $connection = $serviceLocator->get('Config')->redis_connection;
        $redis = new \Redis;
        if ($redis->connect($connection)) {
            throw new \Exception('Failed to connect to redis');
        }
        return $redis;
    }
}
