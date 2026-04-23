<?php
namespace GT\Session;

class SessionStoreFactory {
	public function create(
		string $namespace,
		Session $session,
	):SessionStoreInterface {
		$namespaceParts = explode(".", $namespace);
		$store = new SessionStore(
			array_shift($namespaceParts),
			$session
		);

		foreach($namespaceParts as $part) {
			$innerStore = $store->createStore($part);
			$store = $innerStore;
		}

		return $store;
	}
}
