<?php
namespace GT\Session;

interface SessionContainer {
	public function get(string $key):mixed;
	public function set(string $key, mixed $value):void;
	public function contains(string $key):bool;
	public function remove(string $key):void;
}
