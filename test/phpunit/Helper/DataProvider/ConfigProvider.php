<?php
namespace GT\Session\Test\Helper\DataProvider;

use GT\Session\Handler;

trait ConfigProvider {
	public function data_randomConfig():array {
		$data = [];
		$configKeyList = [
			"save_path","name","cookie_lifetime","cookie_path",
			"cookie_domain","cookie_secure","cookie_httponly",
		];

		for($i = 0; $i < 10; $i++) {
			$row = [];
			$configItem = [];

			foreach($configKeyList as $configKey) {
				$configItem[$configKey] = uniqid($configKey);
			}

			$row []= $configItem;
			$row []= self::createStaticMock(Handler::class);
			$data []= $row;
		}

		return $data;
	}
}
