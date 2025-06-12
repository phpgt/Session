<?php
namespace Gt\Session;

class FlashMessage {
	public function __construct(
		public readonly string $name,
		public readonly string $message,
	) {}
}
