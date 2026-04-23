<?php
namespace GT\Session;

class SessionArrayWrapper implements SessionContainer {
	/** @var array<string, mixed> */
	private array $sourceArray;

	/** @param array<string, mixed> &$sourceArray */
	public function __construct(array &$sourceArray) {
		$this->sourceArray = &$sourceArray;
	}

	public function get(string $key):mixed {
		return $this->sourceArray[$key] ?? null;
	}

	public function set(string $key, mixed $value):void {
		$this->sourceArray[$key] = $value;
	}

	public function contains(string $key):bool {
		return isset($this->sourceArray[$key]);
	}

	public function remove(string $key):void {
		if(!$this->contains($key)) {
			return;
		}

		unset($this->sourceArray[$key]);
	}
}
