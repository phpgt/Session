<?php /** @noinspection PhpUnused */

namespace GT\Session;

use SessionHandler;
use SessionHandlerInterface;

class SessionSetup {
	public function attachHandler(
		string $handlerClass = SessionHandler::class
	):SessionHandlerInterface {
// Ensure class name is fully qualified.
		if($handlerClass[0] !== "\\") {
			$handlerClass = "\\$handlerClass";
		}

		/** @var SessionHandlerInterface $sessionHandler */
		$sessionHandler = new $handlerClass();

// There is no need to set the save handler on the inbuilt SessionHandler as
// it's already set.
		if($handlerClass !== SessionHandler::class) {
			if(session_status() !== PHP_SESSION_ACTIVE) {
				session_set_save_handler($sessionHandler, true);
			}
		}

		return $sessionHandler;
	}
}
