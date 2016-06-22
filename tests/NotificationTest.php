<?php

namespace Bugsnag\Tests;

use Bugsnag\Configuration;
use Bugsnag\Diagnostics;
use Bugsnag\Notification;
use GuzzleHttp\Client;

class NotificationTest extends AbstractTestCase
{
    /** @var \Bugsnag\Configuration */
    protected $config;
    /** @var \Bugsnag\Diagnostics */
    protected $diagnostics;
    /** @var \GuzzleHttp\Client */
    protected $guzzle;
    /** @var \Bugsnag\Notification|\PHPUnit_Framework_MockObject_MockObject */
    protected $notification;

    protected function setUp()
    {
        $this->config = new Configuration('6015a72ff14038114c3d12623dfb018f');
        $this->config->beforeNotifyFunction = 'Bugsnag\Tests\before_notify_skip_error';

        $this->diagnostics = new Diagnostics($this->config);

        $this->guzzle = $this->getMockBuilder(Client::class)
                             ->setMethods(['request'])
                             ->getMock();

        $this->notification = new Notification($this->config, $this->guzzle);
    }

    public function testNotification()
    {
        // Expect request to be called
        $this->guzzle->expects($this->once())
                     ->method('request')
                     ->with($this->equalTo('POST'), $this->equalTo('/'), $this->anything());

        // Add an error to the notification and deliver it
        $this->notification->addError($this->getError());
        $this->notification->deliver();
    }

    public function testBeforeNotifySkipsError()
    {
        $this->guzzle->expects($this->never())->method('request');

        $this->notification->addError($this->getError('SkipMe', 'Message'));
        $this->notification->deliver();
    }

    /**
     * Test for ensuring that the addError method calls shouldNotify.
     *
     * If shouldNotify returns false, the error should not be added.
     */
    public function testAddErrorChecksShouldNotifyFalse()
    {
        $config = $this->getMockBuilder(Configuration::class)
                       ->setMethods(['shouldNotify'])
                       ->setConstructorArgs(['key'])
                       ->getMock();

        $config->expects($this->once())
               ->method('shouldNotify')
               ->will($this->returnValue(false));

        $notification = new Notification($config, $this->guzzle);

        $this->assertFalse($notification->addError($this->getError()));
    }

    /**
     * Test for ensuring that the deliver method calls shouldNotify.
     *
     * If shouldNotify returns false, the error should not be sent.
     */
    public function testDeliverChecksShouldNotify()
    {
        $config = $this->getMockBuilder(Configuration::class)
                       ->setMethods(['shouldNotify'])
                       ->setConstructorArgs(['key'])
                       ->getMock();

        $config->expects($this->once())
               ->method('shouldNotify')
               ->will($this->returnValue(false));

        $notification = new Notification($config, $this->guzzle);

        $this->guzzle->expects($this->never())->method('request');

        $notification->addError($this->getError());
        $notification->deliver();
    }

    public function testNoEnvironmentByDefault()
    {
        $_ENV['SOMETHING'] = 'blah';

        $notification = new Notification($this->config, $this->guzzle);
        $notification->addError($this->getError());
        $notificationArray = $notification->toArray();
        $this->assertArrayNotHasKey('Environment', $notificationArray['events'][0]['metaData']);
    }

    public function testEnvironmentPresentWhenRequested()
    {
        $_ENV['SOMETHING'] = 'blah';

        $this->config->sendEnvironment = true;
        $notification = new Notification($this->config, $this->guzzle);
        $notification->addError($this->getError());
        $notificationArray = $notification->toArray();
        $this->assertSame($notificationArray['events'][0]['metaData']['Environment']['SOMETHING'], 'blah');
    }
}

function before_notify_skip_error($error)
{
    return $error->name != 'SkipMe';
}
