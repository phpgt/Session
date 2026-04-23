<?php
namespace GT\Session\Test\Helper\DataProvider;

trait StringProvider {
	public function data_randomString():array {
		$data = [];

		for($i = 0; $i < 10; $i++) {
			$row = [];
			$row []= uniqid("random");
			$data []= $row;
		}

		return $data;
	}
}