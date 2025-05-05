<?php
namespace Gt\Session\Test;

use DateTime;
use Gt\Session\Handler;
use Gt\Session\Session;
use Gt\Session\SessionStore;
use Gt\Session\Test\Helper\FunctionMocker;
use Gt\Session\Test\Helper\DataProvider\KeyValuePairProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SessionHandler;

class SessionStoreTest extends TestCase {
	use KeyValuePairProvider;

	protected function setUp():void {
		FunctionMocker::mock("session_start");
		FunctionMocker::mock("session_id");
	}

	protected static function createStaticMock(string $className):MockObject {
		$tc = new SessionStoreTest("");
		return $tc->createMock($className);
	}

	/** @dataProvider data_randomKeyValuePairs */
	public static function testGetSetDotNotation(array $keyValuePairs, Handler $handler):void {
		$session = new Session($handler);

		$sessionNamespace = "gt.test.session";

		foreach($keyValuePairs as $key => $value) {
			$fullKey = "$sessionNamespace.$key";
			$session->set($fullKey, $value);
		}

		foreach($keyValuePairs as $key => $value) {
			$fullKey = "$sessionNamespace.$key";
			self::assertEquals($value, $session->get($fullKey));
		}
	}

	public function testRemoveSelf():void {
		/** @var MockObject|SessionHandler $handler */
		$handler = $this->getMockBuilder(Handler::class)
			->getMock();
		$session = new Session($handler);

		$leafStore = $session->getStore(
			"gt.test.session.trunk.leaf",
			true
		);
		$trunkStore = $session->getStore(
			"gt.test.session.trunk"
		);

		$trunkStore->remove();

		self::assertNull($session->getStore("gt.test.session.trunk"));
		self::assertNull($session->getStore("gt.test.session.trunk.leaf"));
		self::assertInstanceOf(
			SessionStore::class,
			$session->getStore("gt.test.session")
		);
	}

	public function testGetCreatesNonExistantStore() {
		/** @var MockObject|SessionHandler $handler */
		$handler = $this->getMockBuilder(Handler::class)
			->getMock();
		$session = new Session($handler);

		$trunkStore = $session->getStore(
			"gt.test.session",
			true
		);
		$leafStore = $session->getStore(
			"gt.test.session.leaf",
			true
		);

		self::assertNotNull($leafStore);
	}

	public function testGetString():void {
		$session = $this->createMock(Session::class);
		$sut = new SessionStore("test", $session);

		$numericValue = rand(1000, 9999);
		$sut->set("test.value", $numericValue);

		self::assertSame((string)$numericValue, $sut->getString("test.value"));
	}

	public function testGetInt():void {
		$session = $this->createMock(Session::class);
		$sut = new SessionStore("test", $session);

		$numericStringValue = (string)rand(1000, 9999);
		$sut->set("test.value", $numericStringValue);

		self::assertSame((int)$numericStringValue, $sut->getInt("test.value"));
	}

	public function testGetFloat():void {
		$session = $this->createMock(Session::class);
		$sut = new SessionStore("test", $session);

		$numericStringValue = (string)(rand(1000, 9999) - 0.105);
		$sut->set("test.value", $numericStringValue);

		self::assertSame((float)$numericStringValue, $sut->getFloat("test.value"));
	}

	public function testGetBool():void {
		$session = $this->createMock(Session::class);
		$sut = new SessionStore("test", $session);

		$numericValue = rand(0, 1);
		$sut->set("test.value", $numericValue);

		self::assertSame((bool)$numericValue, $sut->getBool("test.value"));
	}

	public function testGetDateTime():void {
		$session = $this->createMock(Session::class);
		$sut = new SessionStore("test", $session);

		$numericValue = time();
		$sut->set("test.value", $numericValue);

		$dateTime = new DateTime();
		$dateTime->setTimestamp($numericValue);
		self::assertEquals($dateTime, $sut->getDateTime("test.value"));
	}

	public function testCount():void {
		$session = $this->createMock(Session::class);
		$sut = new SessionStore("test", $session);

		$rawData = [
			"one" => "first",
			"two" => "second",
			"three" => "third",
		];

		foreach($rawData as $key => $value) {
			$sut->set($key, $value);
		}

		self::assertCount(3, $sut);
	}


	public function testSessionStoreIsIterable():void {
		$session = $this->createMock(Session::class);
		$sut = new SessionStore("test", $session);

		$rawData = [
			"key1" => "value1",
			"key2" => "value2",
			"key3" => "value3",
		];

		foreach($rawData as $key => $value) {
			$sut->set($key, $value);
		}

		foreach($sut as $key => $value) {
			self::assertArrayHasKey($key, $rawData);
			self::assertSame($rawData[$key], $value);
		}
	}
}
