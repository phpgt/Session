<?php
namespace Gt\Session;

use Countable;
use Gt\TypeSafeGetter\TypeSafeGetter;

interface SessionStoreInterface extends SessionContainer, TypeSafeGetter, Countable {
	public function setData(string $key, mixed $value):void;
	public function getData(string $key):mixed;
	public function containsData(string $key):bool;
	public function containsStore(string $key):bool;
	public function removeData(string $key):void;
	public function removeStore(string $key):void;
	public function removeDataOrStore(string $key):void;
	public function write():void;
	public function getStore(
		string $namespace,
		bool $createIfNotExists = false
	):?SessionStoreInterface;
	public function setStore(string $namespace):void;
	public function createStore(string $namespace):SessionStoreInterface;
}
