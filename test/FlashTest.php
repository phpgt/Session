<?php

use Gt\Session\Flash;
use Gt\Session\FlashMessage;
use Gt\Session\SessionStore;
use PHPUnit\Framework\TestCase;

class FlashTest extends TestCase {
	public function testPut():void {
		$sessionStore = self::createMock(SessionStore::class);
		$sessionStore->expects(self::once())
			->method("get")
			->with("queue.test")
			->willReturn(null);
		$sessionStore->expects(self::once())
			->method("set")
			->with(
				self::equalTo("queue.test"),
				self::isInstanceOf(SplQueue::class)
			);

		$sut = new Flash($sessionStore);
		$sut->put("test", "Test message");
	}

	public function testConsume_none():void {
		$queue = self::createMock(SplQueue::class);
		$queue->expects(self::once())
			->method("dequeue")
			->willThrowException(new RuntimeException("Can't shift from an empty datastructure"));
		$sessionStore = self::createMock(SessionStore::class);
		$sessionStore->expects(self::once())
			->method("get")
			->with("queue.test")
			->willReturn($queue);
		$sut = new Flash($sessionStore);
		$flashMessage = $sut->consume("test");
		self::assertNull($flashMessage);
	}

	public function testConsume():void {
		$fm1 = new FlashMessage("test", "First");
		$fm2 = new FlashMessage("test", "Second");

		$queue = self::createMock(SplQueue::class);
		$queue->expects(self::exactly(3))
			->method("dequeue")
			->willReturnOnConsecutiveCalls($fm1, $fm2);

		$sessionStore = self::createMock(SessionStore::class);
		$sessionStore->expects(self::exactly(3))
			->method("get")
			->with("queue.test")
			->willReturn($queue);
		$sessionStore->expects(self::once())
			->method("remove")
			->with("queue.test");
		$sut = new Flash($sessionStore);

		$flashMessage = $sut->consume("test");
		self::assertInstanceOf(FlashMessage::class, $flashMessage);
		self::assertSame("First", $flashMessage->message);
		$flashMessage = $sut->consume("test");
		self::assertInstanceOf(FlashMessage::class, $flashMessage);
		self::assertSame("Second", $flashMessage->message);
		$flashMessage = $sut->consume("test");
		self::assertNull($flashMessage);
	}
}
