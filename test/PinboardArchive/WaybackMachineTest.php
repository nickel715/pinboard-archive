<?php

use PinboardArchive\WaybackMachine;
use Zend\Http\Response;

class WaybackMachineTest extends \PHPUnit_Framework_TestCase
{
    protected $sut;
    protected $url = 'http://google.com';

    public function setUp()
    {
        $this->sut = new WaybackMachine;
    }

    private function getClientMock()
    {
        $clientMock = $this->getMock('Zend\Http\Client');
        $this->sut->setClient($clientMock);
        return $clientMock;
    }

    private function mockSubmitClient($response)
    {
        $clientMock = $this->getClientMock();
        $clientMock->expects($this->atLeastOnce())
            ->method('setUri')
            ->with('https://web.archive.org/save/'.$this->url);
        $clientMock->expects($this->once())
            ->method('send')
            ->willReturn($response);
        return $clientMock;
    }

    private function getBookmark()
    {
        $bookmark = new PinboardBookmark;
        $bookmark->url = $this->url;
        return $bookmark;
    }

    private function getRedisMock()
    {
        $redisMock = $this->getMock('Redis', ['hGet', 'hSet', 'expire', 'ttl']);
        $this->sut->setRedis($redisMock);
        return $redisMock;
    }

    private function getCannotCrawledResponse()
    {
        $response = $this->getMock('Zend\Http\Response', ['getBody']);
        $response->expects($this->atLeastOnce())
            ->method('getBody')
            ->willReturn('Page cannot be crawled or displayed due to robots.txt');
        return $response;
    }

    private function mockAvailableClient($response)
    {
        $clientMock = $this->getClientMock();
        $clientMock->expects($this->atLeastOnce())
            ->method('setUri')
            ->with('http://archive.org/wayback/available');
        $clientMock->expects($this->atLeastOnce())
            ->method('setParameterGet')
            ->with(['url' => $this->url]);
        $clientMock->expects($this->once())
            ->method('send')
            ->willReturn($response);
        return $clientMock;
    }

    public function testSubmitBookmarkWorking()
    {
        $this->mockSubmitClient(new Response);
        $this->sut->submitBookmark($this->getBookmark());
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage cannot be crawled
     * @excpetedExceptionCode 2
     */
    public function testSubmitBookmarkNotCrawlable()
    {
        $this->mockSubmitClient($this->getCannotCrawledResponse());
        $this->sut->submitBookmark($this->getBookmark());
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage request failed
     * @excpetedExceptionCode 1
     */
    public function testSubmitBookmarkHttpError()
    {
        $response = new Response;
        $response->setStatusCode(Response::STATUS_CODE_403);
        $this->mockSubmitClient($response);
        $this->sut->submitBookmark($this->getBookmark());
    }

    public function testSubmitBookmarkWithCached()
    {
        $redisMock = $this->getRedisMock();
        $redisMock->expects($this->atLeastOnce())
            ->method('hGet')
            ->with(WaybackMachine::REDIS_BLOCKED, $this->url)
            ->willReturn(1);

        $clientMock = $this->getClientMock();
        $clientMock->expects($this->never())
            ->method('setUri');
        $clientMock->expects($this->never())
            ->method('send');

        $this->sut->submitBookmark($this->getBookmark());
    }

    /**
     * @expectedException Exception
     */
    public function testSubmitBookmarkSetCached()
    {
        $redisMock = $this->getRedisMock();
        $redisMock->expects($this->once())
            ->method('hSet')
            ->with(WaybackMachine::REDIS_BLOCKED, $this->url, 1);
        $redisMock->expects($this->once())
            ->method('expire')
            ->with(WaybackMachine::REDIS_BLOCKED, $this->isType('int'));
        $redisMock->expects($this->once())
            ->method('ttl')
            ->with(WaybackMachine::REDIS_BLOCKED)
            ->willReturn(-1);
        $this->mockSubmitClient($this->getCannotCrawledResponse());
        $this->sut->submitBookmark($this->getBookmark());
    }

    /**
     * @expectedException Exception
     */
    public function testSubmitBookmarkSetCachedSkipExpire()
    {
        $redisMock = $this->getRedisMock();
        $redisMock->expects($this->once())
            ->method('ttl')
            ->with(WaybackMachine::REDIS_BLOCKED)
            ->willReturn(0);
        $redisMock->expects($this->never())
            ->method('expire');
        $this->mockSubmitClient($this->getCannotCrawledResponse());
        $this->sut->submitBookmark($this->getBookmark());
    }


    public function testIsNotAvailable()
    {
        $this->mockAvailableClient(new Response);
        $this->getRedisMock()
            ->expects($this->never())
            ->method('hSet');
        $this->assertFalse($this->sut->isAvailable($this->getBookmark()));
    }

    public function testIsAvailable()
    {
        $response = $this->getMock('Zend\Http\Response', ['getBody']);
        $response->expects($this->atLeastOnce())
            ->method('getBody')
            ->willReturn('{"archived_snapshots": {"closest": {"available": true}}}');
        $this->getRedisMock()
            ->expects($this->once())
            ->method('hSet')
            ->with(WaybackMachine::REDIS_AVAILABLE, $this->url, 1);
        $this->mockAvailableClient($response);
        $this->assertTrue($this->sut->isAvailable($this->getBookmark()));
    }

    public function testIsAvailableCached()
    {
        $redisMock = $this->getRedisMock();
        $redisMock->expects($this->once())
            ->method('hGet')
            ->with(WaybackMachine::REDIS_AVAILABLE)
            ->willReturn(1);
        $clientMock = $this->getClientMock();
        $clientMock->expects($this->never())
            ->method('send');
        $this->assertTrue($this->sut->isAvailable($this->getBookmark()));
    }

    public function testIsNotAvailableCached()
    {
        $map = [[WaybackMachine::REDIS_BLOCKED, $this->url, 1]];
        $redisMock = $this->getRedisMock();
        $redisMock->expects($this->atLeastOnce())
            ->method('hGet')
            ->will($this->returnValueMap($map));
        var_dump($redisMock->hGet(WaybackMachine::REDIS_BLOCKED, $this->url));
        $clientMock = $this->getClientMock();
        $clientMock->expects($this->never())
            ->method('send');
        $this->assertFalse($this->sut->isAvailable($this->getBookmark()));
    }
}
