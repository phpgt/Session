<?php
namespace GT\Session\Test;

use GT\Session\SessionArrayWrapper;
use PHPUnit\Framework\TestCase;

class SessionArrayWrapperTest extends TestCase {
	public function testContains() {
		$sessionArray = [];
		$key = uniqid();
		$sut = new SessionArrayWrapper($sessionArray);
		self::assertFalse($sut->contains($key));
		$sut->set($key, "test-value");
		self::assertTrue($sut->contains($key));
	}

	public function testSet() {
		$sessionArray = [];
		$key = uniqid();
		$value = uniqid();
		$sut = new SessionArrayWrapper($sessionArray);
		$sut->set($key, $value);
		self::assertEquals($value, $sessionArray[$key]);
	}

	public function testGet() {
		$sessionArray = [];
		$key = uniqid();
		$value = uniqid();
		$sut = new SessionArrayWrapper($sessionArray);
		self::assertNull($sut->get($key));
		self::assertArrayNotHasKey($key, $sessionArray);
		$sut->set($key, $value);
		self::assertEquals($value, $sut->get($key));
		self::assertEquals($value, $sessionArray[$key]);
	}

	public function testRemove() {
		$key = uniqid();
		$value = uniqid();
		$sessionArray = [
			$key => $value,
		];
		$sut = new SessionArrayWrapper($sessionArray);
		self::assertTrue($sut->contains($key));
		$sut->remove($key);
		self::assertFalse($sut->contains($key));
		self::assertArrayNotHasKey($key, $sessionArray);
	}
}