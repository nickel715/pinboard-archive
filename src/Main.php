<?php

namespace PinboardArchive;

use \PinboardBookmark as Bookmark;

class Main
{

    protected $pinboardApi;
    protected $waybackMachine;

    protected $allBookmarks = [];

    protected $available;
    protected $unavailable;

    public function setPinboardApi($pinboardApi)
    {
        $this->pinboardApi = $pinboardApi;
        return $this;
    }

    public function setWaybackMachine($waybackMachine)
    {
        $this->waybackMachine = $waybackMachine;
        return $this;
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
            $this->allBookmarks = $this->pinboardApi->get_all();
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
            if ($this->waybackMachine->isAvailable($bookmark)) {
                ++$this->available;
            } else {
                try {
                    $this->waybackMachine->submitBookmark($bookmark);
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
