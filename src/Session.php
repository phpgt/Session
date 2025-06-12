<?php
namespace Gt\Session;

use ArrayAccess;
use ArrayObject;
use Gt\TypeSafeGetter\NullableTypeSafeGetter;
use Gt\TypeSafeGetter\TypeSafeGetter;
use SessionHandlerInterface;
use Traversable;

class Session implements SessionContainer, TypeSafeGetter {
	use NullableTypeSafeGetter;

	const DEFAULT_SESSION_NAME = "PHPSESSID";
	const DEFAULT_SESSION_LIFETIME = 0;
	const DEFAULT_SESSION_PATH = "/tmp";
	const DEFAULT_SESSION_DOMAIN = "";
	const DEFAULT_SESSION_SECURE = true;
	const DEFAULT_SESSION_HTTPONLY = true;
	const DEFAULT_COOKIE_PATH = "/";
	const DEFAULT_COOKIE_SAMESITE = "Lax";
	const DEFAULT_STRICT_MODE = true;
	const DEFAULT_SESSION_ID_LENGTH = 64;
	const DEFAULT_SESSION_ID_BITS_PER_CHARACTER = 5;

	protected string $id;
	protected SessionHandlerInterface $sessionHandler;
	protected ?SessionStore $store;
	/** @var array<string, mixed> */
	protected array $config;

	/** @param iterable<string,mixed> $config */
	public function __construct(
		SessionHandlerInterface $sessionHandler,
		iterable $config = [],
		?string $id = null,
	) {
		$this->sessionHandler = $sessionHandler;

		if(!is_array($config)) {
			$config = iterator_to_array($config);
		}
		/** @var array<string,mixed> $config */
		$this->config = $config;

		if(is_null($id)) {
			$id = $this->getId();
		}

		$this->id = $id;

		$sessionPath = $this->getAbsolutePath(
			$config["save_path"] ?? self::DEFAULT_SESSION_PATH
		);
		$sessionName = $config["name"] ?? self::DEFAULT_SESSION_NAME;
		$this->attemptStart($sessionPath, $sessionName, $config);

		$this->sessionHandler->open($sessionPath, $sessionName);
		$this->store = $this->readSessionData();
		if(is_null($this->store)) {
			$this->store = new SessionStore(__NAMESPACE__, $this);
		}
	}

	public function kill():void {
		$this->sessionHandler->destroy($this->getId());
		$params = session_get_cookie_params();
		setcookie(
			session_name() ?: "",
			"",
			-1,
			$params["path"],
			$params["domain"],
			$params["secure"],
			$params["httponly"]
		);
	}

	public function getStore(
		string $namespace,
		bool $createIfNotExists = false
	):?SessionStore {
		return $this->store->getStore(
			$namespace,
			$createIfNotExists
		);
	}

	public function get(string $key):mixed {
		return $this->store->get($key);
	}

	public function set(string $key, mixed $value):void {
		$this->store->set($key, $value);
	}

	public function contains(string $key):bool {
		return $this->store->contains($key);
	}

	public function remove(string $key):void {
		$this->store->remove($key);
	}

	public function getId():string {
		$id = session_id();
		if(empty($id)) {
			session_id($this->createNewId());
		}

		return session_id() ?: "";
	}

	protected function getAbsolutePath(string $path):string {
		$path = str_replace(
			["/", "\\"],
			DIRECTORY_SEPARATOR,
			$path
		);

		if($path[0] !== DIRECTORY_SEPARATOR) {
			$path = implode(DIRECTORY_SEPARATOR, [
				sys_get_temp_dir(),
				$path,
			]);
		}

		return $path;
	}

	/** @SuppressWarnings(PHPMD.Superglobals) */
	protected function createNewId():string {
		if(($this->config["use_trans_sid"] ?? null)
		&& !$this->config["use_cookies"]) {
			return $_GET[$this->config["name"]] ?? session_create_id();
		}
		return session_create_id() ?: "";
	}

	protected function readSessionData():?SessionStore {
		return unserialize($this->sessionHandler->read($this->id)) ?: null;
	}

	public function write():bool {
		return $this->sessionHandler->write(
			$this->id,
			serialize($this->store)
		);
	}

	/**
	 * @param string $sessionPath
	 * @param string $sessionName
	 * @param array<string,mixed> $config
	 * @return void
	 */
	private function attemptStart(
		string $sessionPath,
		string $sessionName,
		array $config,
	):void {
		$sessionOptions = $this->getSessionOptions(
			$sessionPath,
			$sessionName,
			$config,
		);
		$this->tryStartSession($sessionOptions);
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private function getSessionOptions(
		string $sessionPath,
		string $sessionName,
		array $config
	):array {
		$defaultConfig = [
			"use_only_cookies" => true,
			"use_cookies" => true,
			"use_trans_sid" => false,
			"cookie_lifetime" => self::DEFAULT_SESSION_LIFETIME,
			"cookie_path" => self::DEFAULT_COOKIE_PATH,
			"cookie_domain" => self::DEFAULT_SESSION_DOMAIN,
			"cookie_secure" => self::DEFAULT_SESSION_SECURE,
			"cookie_httponly" => self::DEFAULT_SESSION_HTTPONLY,
			"cookie_samesite" => self::DEFAULT_COOKIE_SAMESITE,
			"use_strict_mode" => self::DEFAULT_STRICT_MODE,
		];

		$config = array_merge($defaultConfig, $config);

		return [
			"save_path" => $sessionPath,
			"name" => $sessionName,
			"serialize_handler" => "php_serialize",
			"use_only_cookies" => $config["use_only_cookies"],
			"use_cookies" => $config["use_cookies"],
			"use_trans_sid" => $config["use_trans_sid"] ?? false,
			"cookie_lifetime" => $config["cookie_lifetime"],
			"cookie_path" => $config["cookie_path"],
			"cookie_domain" => $config["cookie_domain"],
			"cookie_secure" => $config["cookie_secure"],
			"cookie_httponly" => $config["cookie_httponly"],
			"cookie_samesite" => $config["cookie_samesite"],
			"use_strict_mode" => $config["use_strict_mode"],
		];
	}

	/** @param array<string,mixed> $sessionOptions */
	private function tryStartSession(array $sessionOptions):void {
		$startAttempts = 0;
		do {
			$success = session_start($sessionOptions);
			if(!$success) {
				//phpcs:ignore
				@session_destroy();
			}
			$startAttempts++;
		}
		while(!$success && $startAttempts <= 1);
	}
}
