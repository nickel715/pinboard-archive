<?php

namespace PinboardArchive;

use \Zend\ServiceManager\ServiceManager;
use \Zend\ServiceManager\ServiceManagerAwareInterface;
use \PinboardBookmark as Bookmark;

class Main implements ServiceManagerAwareInterface
{

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    protected $allBookmarks = [];

    protected $available;
    protected $unavailable;

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
     * Get amout of bookmarks saved on archive.org
     *
     * @return int
     */
    public function getAvailableCount()
    {
        $this->checkCounts();
        return $this->available;
    }

    /**
     * Get amount of bookmarks NOT saved on archive.org
     *
     * @return int
     */
    public function getUnavailableCount()
    {
        $this->checkCounts();
        return $this->unavailable;
    }

    /**
     * Get all pinboard bookmarks
     *
     * @return Bookmark[]
     */
    protected function getAllBookmarks()
    {
        if (empty($this->allBookmarks)) {
            $this->allBookmarks = $this->serviceManager->get('pinboard-api')->get_all();
        }
        return $this->allBookmarks;
    }

    /**
     * Check if availability counts exists if not trigger calculation
     */
    protected function checkCounts()
    {
        if ($this->available === null || $this->unavailable === null) {
            $this->calculateAvailabilityCounts();
        }
    }

    /**
     * Calculate availability counts
     */
    protected function calculateAvailabilityCounts()
    {
        $this->available = 0;
        $this->unavailable = 0;

        foreach ($this->getAllBookmarks() as $bookmark) {
            if ($this->serviceManager->get('wayback-machine')->isAvailable($bookmark)) {
                ++$this->available;
            } else {
                try {
                    $this->serviceManager->get('wayback-machine')->submitBookmark($bookmark);
                } catch (\Exception $e) {
                    if ($e->getCode() != 2) {
                        echo $e, PHP_EOL;
                    }
                }
                ++$this->unavailable;
            }
        }
    }
}
