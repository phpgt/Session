<?php
namespace GT\Session;

class FlashMessage {
	public function __construct(
		public readonly string $name,
		public readonly string $message,
	) {}
}
