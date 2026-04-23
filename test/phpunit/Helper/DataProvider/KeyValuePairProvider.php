<?php
namespace GT\Session\Test\Helper\DataProvider;

use GT\Session\Handler;
use GT\Session\Test\SessionStoreTest;

trait KeyValuePairProvider {
	public static function data_randomKeyValuePairs():array {
		$data = [];

		for($i = 0; $i < 10; $i++) {
			$row = [];

			$numberKeys = rand(2, 10);
			$config = [];
			for($j = 0; $j < $numberKeys; $j++) {
				$key = uniqid("key");
				$value = uniqid("value");

				$config[$key] = $value;
			}

			$row []= $config;
			$row []= self::createStaticMock(Handler::class);
			$data []= $row;
		}

		return $data;
	}
}
