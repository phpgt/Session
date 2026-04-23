<?php
namespace GT\Session\Test\Helper;

class FunctionMocker {
	public static $mockCalls = [];
	public static $callState = [];

	public static function mock(string $functionName) {
		self::$mockCalls[$functionName] = [];

		require_once(implode(DIRECTORY_SEPARATOR, [
			__DIR__,
			"FunctionOverride",
			"$functionName.php",
		]));
	}
}