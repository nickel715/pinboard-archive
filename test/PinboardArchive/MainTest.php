<?php

class MainTest extends \PHPUnit_Framework_TestCase
{
    protected $sut;

    public function setUp()
    {
        $this->sut = new PinboardArchive\Main;
    }

    protected function simulateGetAllBookmarks(array $bookmarks)
    {
        $pinboardApiMock = $this->getMockBuilder('PinboardAPI')
            ->disableOriginalConstructor()
            ->getMock();
        $pinboardApiMock->method('get_all')->willReturn($bookmarks);
        $this->sut->setPinboardApi($pinboardApiMock);
        return $pinboardApiMock;
    }

    protected function getWaybackMachineMock()
    {
        $waybackMachineMock = $this->getMock(
            'PinboardArchive\WaybackMachine',
            ['isAvailable', 'submitBookmark']
        );
        $this->sut->setWaybackMachine($waybackMachineMock);
        return $waybackMachineMock;
    }

    protected function assertCounts($available, $unavailable)
    {
        $this->assertEquals($available, $this->sut->getAvailableCount());
        $this->assertEquals($unavailable, $this->sut->getUnavailableCount());
    }

    public function testCounts()
    {
        $bookmarks = [new PinboardBookmark, new PinboardBookmark, new PinboardBookmark];
        $this->assertNotSame($bookmarks[0], $bookmarks[1]);

        $this->simulateGetAllBookmarks($bookmarks);

        $waybackMachineMock = $this->getWaybackMachineMock();

        $waybackMachineMock->expects($this->exactly(count($bookmarks)))
            ->method('isAvailable')
            ->withConsecutive([$bookmarks[0]], [$bookmarks[1]], [$bookmarks[2]])
            ->will($this->onConsecutiveCalls(true, false, true));

        $waybackMachineMock->expects($this->once())
            ->method('submitBookmark')
            ->with($this->identicalTo($bookmarks[1]));

        $this->assertCounts(2, 1);
    }

    public function testSubmitException()
    {
        $this->simulateGetAllBookmarks([new PinboardBookmark]);
        $waybackMachineMock = $this->getWaybackMachineMock();

        $waybackMachineMock->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);

        $waybackMachineMock->expects($this->once())
            ->method('submitBookmark')
            ->will($this->throwException(new Exception('')));

        $this->assertCounts(0, 1);
    }
}
