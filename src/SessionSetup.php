<?php /** @noinspection PhpUnused */

namespace Gt\Session;

use SessionHandler;
use SessionHandlerInterface;

class SessionSetup {
	public static function attachHandler(
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
		if(session_status() === PHP_SESSION_ACTIVE) {
			return $sessionHandler;
		}
		if($handlerClass !== SessionHandler::class) {
			session_set_save_handler($sessionHandler, true);
		}

		return $sessionHandler;
	}
}
