<?php

namespace PinboardArchive;

use \Zend\ServiceManager\ServiceManager;
use \Zend\ServiceManager\ServiceManagerAwareInterface;
use \PinboardBookmark as Bookmark;
use Zend\Http\Client;
use Zend\Http\Client\Adapter\Curl;

class WaybackMachine implements ServiceManagerAwareInterface
{
    /**
     * Redis hash with urls blocked by robots.txt
     */
    const REDIS_BLOCKED = 'pinboard-archive:blocked';

    /**
     * Redis hash with urls available on archive.org
     */
    const REDIS_AVAILABLE = 'pinboard-archive:available';

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * Set service manager
     *
     * @param ServiceManager $serviceManager
     */
    public function __construct(ServiceManager $serviceManager)
    {
        $this->setServiceManager($serviceManager);
    }

    /**
     * Set service manager
     *
     * @param ServiceManager $serviceManager
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    /**
     * Submit bookmark to archive.org
     *
     * @param Bookmark $bookmark
     */
    public function submitBookmark(Bookmark $bookmark)
    {
        if ($this->isCached($bookmark, self::REDIS_BLOCKED)) {
            return;
        }
        $baseUrl = 'https://web.archive.org/save/';
        $client = new Client;
        $client->setAdapter(new Curl);
        $client->setUri($baseUrl . $bookmark->url);
        $response = $client->send();
        if (strpos($response->getBody(), 'cannot be crawled') !== false) {
            $this->setCached($bookmark, self::REDIS_BLOCKED);
            throw new \Exception('Page cannot be crawled or displayed due to robots.txt', 2);
        }
        if (!$response->isSuccess()) {
            $message = sprintf(
                "request failed! tried to submit \"%s\", \nresponse: %s",
                $bookmark->url,
                $response->renderStatusLine()
            );
            throw new \Exception($message, 1);
        }
    }

    /**
     * Check if given bookmark is archived
     *
     * @param  Bookmark $bookmark
     * @return boolean
     */
    public function isAvailable(Bookmark $bookmark)
    {
        if ($this->isCached($bookmark, self::REDIS_AVAILABLE)) {
            return true;
        }
        if ($this->isCached($bookmark, self::REDIS_BLOCKED)) {
            return false;
        }
        $client = new Client;
        $client->setAdapter(new Curl);
        $client->setUri('http://archive.org/wayback/available');
        $client->setParameterGet(['url' => $bookmark->url]);
        $response = json_decode($client->send()->getBody());
        $isAvailable = !empty($response->archived_snapshots->closest);
        if ($isAvailable) {
            $this->setCached($bookmark, self::REDIS_AVAILABLE);
        }
        return $isAvailable;
    }

    public function isCached(Bookmark $bookmark, $redisKey)
    {
        if ($this->serviceManager->has('redis')) {
            return $this->serviceManager->get('redis')->hget($redisKey, $bookmark->url);
        }
        return false;
    }

    protected function setCached(Bookmark $bookmark, $redisKey)
    {
        if ($this->serviceManager->has('redis')) {
            $redis = $this->serviceManager->get('redis');
            $redis->hset($redisKey, $bookmark->url, 1);
            if ($redis->ttl($redisKey) < 0) {
                $redis->expire($redisKey, 4*7*24*60*60);
            }
        }
    }
}
