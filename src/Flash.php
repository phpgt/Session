<?php
namespace Gt\Session;

use RuntimeException;
use SplQueue;

class Flash {
	public function __construct(private readonly SessionStore $session) {}

	public function put(string $name, string $message):void {
		$queue = $this->session->get("queue.$name");
		if(!$queue) {
			$queue = new SplQueue();
			$this->session->set("queue.$name", $queue);
		}

		$queue->enqueue(new FlashMessage($name, $message));
	}

	public function consume(string $name):?FlashMessage {
		/** @var null|SplQueue $queue */
		$queue = $this->session->get("queue.$name");
		try {
			return $queue->dequeue();
		}
		catch(RuntimeException $e) {
			$this->session->remove("queue.$name");
			return null;
		}
	}

}
