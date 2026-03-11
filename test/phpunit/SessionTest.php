<?php
namespace Gt\Session\Test;

use DateTime;
use Gt\Session\Handler;
use Gt\Session\Session;
use Gt\Session\Test\Helper\DataProvider\ConfigProvider;
use Gt\Session\Test\Helper\DataProvider\StringProvider;
use Gt\Session\Test\Helper\FunctionMocker;
use Gt\Session\Test\Helper\DataProvider\KeyValuePairProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase {
	use KeyValuePairProvider;
	use StringProvider;
	use ConfigProvider;

	protected function setUp():void {
		FunctionMocker::mock("session_start");
		FunctionMocker::mock("session_id");
		FunctionMocker::mock("session_destroy");
	}

	protected static function createStaticMock(string $className):MockObject {
		$tc = new SessionStoreTest("");
		return $tc->createMock($className);
	}

	public function testSessionStarts():void {
		self::assertEmpty(FunctionMocker::$mockCalls["session_start"]);
		$handler = self::createMock(Handler::class);
		new Session($handler);
		self::assertCount(
			1,
			FunctionMocker::$mockCalls["session_start"]
		);
	}

	public function testSessionStartPreservesDsnSavePath():void {
		$handler = self::createMock(Handler::class);
		$savePath = "valkey://cache.internal:6379/0?ttl=1440";

		new Session($handler, [
			"save_path" => $savePath,
		]);

		$sessionStartParameter = FunctionMocker::$mockCalls["session_start"][0][0];
		self::assertSame($savePath, $sessionStartParameter["save_path"]);
	}

	public function testWriteSessionDataCalled() {
		$handler = self::createMock(Handler::class);
		$handler->expects($this->exactly(2))
			->method("write");
		$session = new Session($handler);

		$session->set("test-key", "test-value");
		$session->remove("test-key");
	}

	public function testGetString():void {
		$handler = self::createMock(Handler::class);
		$sut = new Session($handler);

		$numericValue = rand(1000, 9999);
		$sut->set("test.value", $numericValue);

		self::assertSame((string)$numericValue, $sut->getString("test.value"));
	}

	public function testGetInt():void {
		$handler = self::createMock(Handler::class);
		$sut = new Session($handler);

		$numericStringValue = (string)rand(1000, 9999);
		$sut->set("test.value", $numericStringValue);

		self::assertSame((int)$numericStringValue, $sut->getInt("test.value"));
	}

	public function testGetFloat():void {
		$handler = self::createMock(Handler::class);
		$sut = new Session($handler);

		$numericStringValue = (string)(rand(1000, 9999) - 0.105);
		$sut->set("test.value", $numericStringValue);

		self::assertSame((float)$numericStringValue, $sut->getFloat("test.value"));
	}

	public function testGetBool():void {
		$handler = self::createMock(Handler::class);
		$sut = new Session($handler);

		$numericValue = rand(0, 1);
		$sut->set("test.value", $numericValue);

		self::assertSame((bool)$numericValue, $sut->getBool("test.value"));
	}

	public function testGetDateTime():void {
		$handler = self::createMock(Handler::class);
		$sut = new Session($handler);

		$numericValue = time();
		$sut->set("test.value", $numericValue);

		$dateTime = new DateTime();
		$dateTime->setTimestamp($numericValue);
		self::assertEquals($dateTime, $sut->getDateTime("test.value"));
	}

	/** @dataProvider data_randomConfig */
	private static function testSessionStartsWithConfig(array $config, Handler $handler):void {
		new Session($handler, $config);
		$sessionStartParameter = FunctionMocker::$mockCalls["session_start"][0][0];

		foreach($config as $key => $value) {
// For Windows compatibility, save_path is handled differently (see Session::getAbsolutePath)
			if($key === "save_path") {
				continue;
			}
			self::assertEquals($value, $sessionStartParameter[$key]);
		}
	}

	/** @dataProvider data_randomConfig */
	private static function testSessionStartDestroysFailedSession(array $config, Handler $handler):void {
		FunctionMocker::$callState["session_start__fail"] = true;
		new Session($handler, $config);
		self::assertCount(1, FunctionMocker::$mockCalls["session_destroy"]);
	}

	/** @dataProvider data_randomString */
	private static function testGetReturnsNull(string $randomString, Handler $handler):void {
		$session = new Session($handler);
		self::assertNull($session->get($randomString));
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testSetGet(array $keyValuePairs, Handler $handler):void {
		$session = new Session($handler);

		foreach($keyValuePairs as $key => $value) {
			$session->set($key, $value);
		}

		foreach($keyValuePairs as $key => $value) {
			self::assertEquals($value, $session->get($key));
		}
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testSetGetNamespaced(array $keyValuePairs, Handler $handler):void {
		$session = new Session($handler);

		foreach($keyValuePairs as $key => $value) {
			$newKey = implode(".", [
				uniqid("namespace1"),
				uniqid("namespace2"),
				$key,
			]);
			$keyValuePairs[$newKey] = $value;
			unset($keyValuePairs[$key]);
		}

		foreach($keyValuePairs as $key => $value) {
			$session->set($key, $value);
		}

		foreach($keyValuePairs as $key => $value) {
			self::assertEquals($value, $session->get($key));
		}
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testSetGetNamespacedSameParentNamespace(array $keyValuePairs, Handler $handler):void {
		$session = new Session($handler);

		$parentNamespace = implode(".", [
			uniqid("namespace1-"),
			uniqid("namespace2-"),
		]);

		foreach($keyValuePairs as $key => $value) {
			$newKey = implode(".", [
				$parentNamespace,
				$key,
			]);
			$keyValuePairs[$newKey] = $value;
			unset($keyValuePairs[$key]);
		}

		foreach($keyValuePairs as $key => $value) {
			$session->set($key, $value);
		}

		foreach($keyValuePairs as $key => $value) {
			self::assertEquals($value, $session->get($key));
		}
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testSetGetNotExistsOtherKey(array $keyValuePairs, Handler $handler):void {
		$session = new Session($handler);

		foreach($keyValuePairs as $key => $value) {
			$session->set($key, $value);
		}

// Test that other keys' values do not match.
		foreach($keyValuePairs as $key => $value) {
			do {
				$otherKey = array_rand($keyValuePairs);
			}while($otherKey === $key);

			self::assertNotEquals($value, $session->get($otherKey));
		}

// Test that unknown keys' values do not match.
		foreach($keyValuePairs as $key => $value) {
			self::assertNotEquals(
				$value,
				$session->get("$key-oh-no")
			);
		}
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testContains(array $keyValuePairs, Handler $handler):void {
		$session = new Session($handler);

		foreach($keyValuePairs as $key => $value) {
			$session->set($key, $value);
		}

		foreach($keyValuePairs as $key => $value) {
			self::assertTrue($session->contains($key));
		}
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testNotContains(array $keyValuePairs, Handler $handler):void {
		$session = new Session($handler);

		foreach($keyValuePairs as $key => $value) {
			$session->set($key, $value);
		}

		foreach($keyValuePairs as $key => $value) {
			self::assertFalse($session->contains("$key-oh-no"));
		}
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testRemove(array $keyValuePairs, Handler $handler):void {
		$session = new Session($handler);

		foreach($keyValuePairs as $key => $value) {
			$session->set($key, $value);
		}

		uasort($keyValuePairs, function() {
			return rand(-1, 1);
		});

		foreach($keyValuePairs as $key => $value) {
			self::assertTrue($session->contains($key));
			$session->remove($key);
			self::assertFalse($session->contains($key));
		}
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testNamespaceKeyIsRemovedFromSession(array $keyValuePairs, Handler $handler):void {
		$session = new Session($handler);

		$parentNamespace = implode(".", [
			uniqid("namespace1-"),
			uniqid("namespace2-"),
		]);

		foreach($keyValuePairs as $key => $value) {
			$fullKey = implode(".", [
				$parentNamespace,
				$key,
			]);

			$session->set($fullKey, $value);
		}

		$keyToRemove = array_rand($keyValuePairs);
		$fullKeyToRemove = implode(".", [
			$parentNamespace,
			$keyToRemove,
		]);
		$store = $session->getStore($parentNamespace);
		$store->remove($keyToRemove);
		unset($keyValuePairs[$keyToRemove]);

		foreach($keyValuePairs as $key => $value) {
			$fullKey = implode(".", [
				$parentNamespace,
				$key,
			]);

			self::assertTrue($session->contains($fullKey));
		}

		self::assertFalse($session->contains($fullKeyToRemove));
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testNamespaceKeyIsRemovedFromStore(array $keyValuePairs, Handler $handler): void {
		$session = new Session($handler);

		$parentNamespace = implode(".", [
			uniqid("namespace1-"),
			uniqid("namespace2-"),
		]);

		foreach ($keyValuePairs as $key => $value) {
			$fullKey = implode(".", [
				$parentNamespace,
				$key,
			]);

			$session->set($fullKey, $value);
		}

		$keyToRemove = array_rand($keyValuePairs);
		$store = $session->getStore($parentNamespace);
		$store->remove($keyToRemove);
		unset($keyValuePairs[$keyToRemove]);

		foreach ($keyValuePairs as $key => $value) {
			self::assertTrue($store->contains($key));
		}

		self::assertFalse($store->contains($keyToRemove));
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testRemoveNamespace(array $keyValuePairs, Handler $handler) {
		$session = new Session($handler);

		$namespace1 = uniqid("namespace1-");
		$namespace2 = uniqid("namespace2-");
		$namespace2a = uniqid("namespace2a-");

		$parentNamespace = implode(".", [
			$namespace1,
			$namespace2,
		]);

		foreach($keyValuePairs as $key => $value) {
			$fullKey = implode(".", [
				$parentNamespace,
				$key,
			]);

			$session->set($fullKey, $value);
		}

		$parent2Namespace = implode(".", [
			$namespace1,
			$namespace2a,
		]);
		$fullKey2 = implode(".", [
			$parent2Namespace,
			"test-key",
		]);
		$session->set($fullKey2, "example sibling value");

		$session->remove($parentNamespace);

		self::assertNull($session->getStore($parentNamespace));
		self::assertFalse($session->contains($parentNamespace));
	}

	/** @dataProvider data_randomKeyValuePairs */
	private static function testRemoveSiblingNamespace(array $keyValuePairs, Handler $handler) {
		$session = new Session($handler);

		$namespace0 = uniqid("namespace0-");
		$namespace1a = uniqid("namespace1a-");
		$namespace1b = uniqid("namespace1b-");

		foreach($keyValuePairs as $key => $value) {
			$fullKeyA = implode(".", [
				$namespace0,
				$namespace1a,
				$key,
			]);
			$fullKeyB = implode(".", [
				$namespace0,
				$namespace1b,
				$key,
			]);

			$session->set($fullKeyA, "a-value-" . $value);
			$session->set($fullKeyB, "b-value-" . $value);
		}

		foreach($keyValuePairs as $key => $value) {
			$fullKeyA = implode(".", [
				$namespace0,
				$namespace1a,
				$key,
			]);
			$fullKeyB = implode(".", [
				$namespace0,
				$namespace1b,
				$key,
			]);

			self::assertEquals(
				"a-value-" . $value,
				$session->get($fullKeyA)
			);
			self::assertEquals(
				"b-value-" . $value,
				$session->get($fullKeyB)
			);
		}

		$session->remove(implode(".", [
			$namespace0,
			$namespace1a,
		]));
		self::assertFalse($session->contains($namespace1a));

		foreach($keyValuePairs as $key => $value) {
			$fullKeyA = implode(".", [
				$namespace0,
				$namespace1a,
				$key,
			]);
			$fullKeyB = implode(".", [
				$namespace0,
				$namespace1b,
				$key,
			]);

			self::assertTrue(
				$session->contains($fullKeyB)
			);
			self::assertEquals(
				"b-value-" . $value,
				$session->get($fullKeyB)
			);

			self::assertFalse(
				$session->contains($fullKeyA)
			);
			self::assertNull(
				$session->get($fullKeyA)
			);
		}
	}
}
