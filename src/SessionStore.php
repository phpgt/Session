<?php
namespace Gt\Session;

use ArrayIterator;
use Countable;
use Gt\TypeSafeGetter\NullableTypeSafeGetter;
use Gt\TypeSafeGetter\TypeSafeGetter;

/**
 * @extends ArrayIterator<string, mixed>
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class SessionStore
	extends ArrayIterator
	implements SessionContainer, TypeSafeGetter, Countable {
	use NullableTypeSafeGetter;

	protected string $name;
	protected Session $session;
	/** @var array<SessionStore> */
	protected array $stores;
	protected ?SessionStore $parentStore;

	public function __construct(
		string $name,
		Session $session,
		?self $parentStore = null
	) {
		$this->name = $name;
		$this->session = $session;
		$this->parentStore = $parentStore;
		$this->stores = [];
		parent::__construct();
	}

	public function setData(string $key, mixed $value):void {
		$this->offsetSet($key, $value);
	}

	public function getData(string $key):mixed {
		if(!$this->offsetExists($key)) {
			return null;
		}

		return $this->offsetGet($key);
	}

	public function containsData(string $key):bool {
		return $this->offsetExists($key);
	}

	public function containsStore(string $key):bool {
		return isset($this->stores[$key]);
	}

	public function removeData(string $key):void {
		$this->offsetUnset($key);
	}

	public function removeStore(string $key):void {
		unset($this->stores[$key]);
	}

	public function removeDataOrStore(string $key):void {
		if($this->containsData($key)) {
			$this->removeData($key);
		}
		if($this->containsStore($key)) {
			$this->removeStore($key);
		}
	}

	public function write():void {
		if(!isset($this->session)) {
			return;
		}

		$this->session->write();
	}

	public function getStore(
		string $namespace,
		bool $createIfNotExists = false
	):?SessionStore {
		$namespaceParts = explode(".", $namespace);
		$topLevelStoreName = array_shift($namespaceParts);

		$store = $this->stores[$topLevelStoreName] ?? null;
		if(is_null($store)) {
			if($createIfNotExists) {
				return $this->createStore($namespace);
			}
			return null;
		}

		if(empty($namespaceParts)) {
			return $store;
		}

		$namespace = implode(".", $namespaceParts);
		return $store->getStore($namespace, $createIfNotExists);
	}

	public function setStore(
		string $namespace
	):void {
		$namespaceParts = explode(".", $namespace);
		$store = $this;
		$nextStore = $store;

		while (!empty($namespaceParts)) {
			$storeName = array_shift($namespaceParts);
			$store = $nextStore;
			$nextStore = $store->getStore($storeName);

			if (is_null($nextStore)) {
				$nextStore = new SessionStore(
					$storeName,
					$this->session,
					$store
				);
				$store->stores[$storeName] = $nextStore;
			}
		}
	}

	public function createStore(string $namespace):SessionStore {
		$this->setStore($namespace);
		return $this->getStore($namespace);
	}

	public function get(string $key):mixed {
		$store = $this->getStoreFromKey($key);
		$key = $this->normaliseKey($key);
		return $store?->getData($key);
	}

	public function set(string $key, mixed $value):void {
		$store = $this;
		$lastDotPosition = strrpos($key, ".");

		if ($lastDotPosition !== false) {
			$namespace = $this->getNamespaceFromKey($key);
			$store = $this->getStore($namespace);

			if (is_null($store)) {
				$store = $this->createStore($namespace);
			}
		}

		if ($lastDotPosition !== false) {
			$key = substr($key, $lastDotPosition + 1);
		}

		$store->setData($key, $value);
		$store->write();
	}

	public function contains(string $key):bool {
		$store = $this->getStoreFromKey($key);
		$key = $this->normaliseKey($key);
		return $store?->containsData($key) ?? false;
	}

	private function getStoreFromKey(string $key):?SessionStore {
		$store = $this;
		$lastDotPosition = strrpos($key, ".");

		if ($lastDotPosition !== false) {
			$namespace = $this->getNamespaceFromKey($key);
			$store = $this->getStore($namespace);
		}

		if (is_null($store)) {
			return null;
		}

		return $store;
	}

	private function normaliseKey(string $key):string {
		$lastDotPosition = strrpos($key, ".");
		if($lastDotPosition !== false) {
			$key = substr($key, $lastDotPosition + 1);
		}

		return $key;
	}

	public function remove(?string $key = null):void {
		if(is_null($key)) {
			foreach(array_keys($this->stores) as $i) {
				unset($this->stores[$i]);
			}

			$this->parentStore->remove($this->name);
			return;
		}

		$store = $this;
		$lastDotPosition = strrpos($key, ".");

		if($lastDotPosition !== false) {
			$namespace = $this->getNamespaceFromKey($key);
			$store = $this->getStore($namespace);
		}

		if(is_null($store)) {
			return;
		}

		if($lastDotPosition !== false) {
			$key = substr($key, $lastDotPosition + 1);
		}

		$store->removeDataOrStore($key);
		$store->write();
	}

	protected function getSession():Session {
		return $this->session;
	}

	protected function getNamespaceFromKey(string $key):?string {
		$lastDotPosition = strrpos($key, ".");
		if ($lastDotPosition === false) {
			return null;
		}

		return substr($key, 0, $lastDotPosition);
	}
}
