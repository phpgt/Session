<?php
namespace Gt\Session;

use Redis;
use RuntimeException;

class RedisHandler extends Handler {
	private const DEFAULT_PORT = 6379;
	private const DEFAULT_PREFIX_SEPARATOR = ":";
	private ?Redis $client = null;
	private string $prefix = "";
	private int $ttl = 0;

	public function open(string $savePath, string $name):bool {
		$config = $this->parseSavePath($savePath, $name);
		$client = $this->createClient();

		$connected = $client->connect(
			$config["host"],
			$config["port"],
			$config["timeout"],
			$config["persistentId"],
			0,
			$config["readTimeout"],
			$config["context"],
		);

		if(!$connected) {
			return false;
		}

		if($config["auth"] !== null && !$client->auth($config["auth"])) {
			return false;
		}

		if($config["database"] > 0 && !$client->select($config["database"])) {
			return false;
		}

		$this->client = $client;
		$this->prefix = $config["prefix"];
		$this->ttl = $config["ttl"];
		return true;
	}

	public function close():bool {
		if(is_null($this->client)) {
			return true;
		}

		return $this->client->close();
	}

	public function read(string $sessionId):string {
		$value = $this->requireClient()->get($this->getKey($sessionId));
		return is_string($value) ? $value : "";
	}

	public function write(string $sessionId, string $sessionData):bool {
		$client = $this->requireClient();
		$key = $this->getKey($sessionId);

		if($this->ttl > 0) {
			return $client->setEx($key, $this->ttl, $sessionData);
		}

		return $client->set($key, $sessionData);
	}

	public function destroy(string $id = ""):bool {
		return $this->requireClient()->del($this->getKey($id)) >= 0;
	}

	public function gc(int $maxLifeTime):int|false {
		return 0;
	}

	/**
	 * @return array{
	 *   host:string,
	 *   port:int,
	 *   timeout:float,
	 *   readTimeout:float,
	 *   persistentId:?string,
	 *   prefix:string,
	 *   ttl:int,
	 *   database:int,
	 *   auth:array{string,string}|string|null,
	 *   context:array{stream:array{verify_peer:bool,verify_peer_name:bool}}|null
	 * }
	 */
	private function parseSavePath(string $savePath, string $name):array {
		$parts = parse_url($savePath);
		if($parts === false || !isset($parts["host"])) {
			throw new RuntimeException("Invalid Redis save_path DSN.");
		}

		parse_str($parts["query"] ?? "", $query);

		$scheme = strtolower($parts["scheme"] ?? "redis");
		$host = $parts["host"];
		$context = null;

		if(in_array($scheme, ["rediss", "tls"], true)) {
			$host = "tls://$host";
			$context = [
				"stream" => [
					"verify_peer" => filter_var(
						$query["verify_peer"] ?? true,
						FILTER_VALIDATE_BOOL
					),
					"verify_peer_name" => filter_var(
						$query["verify_peer_name"] ?? true,
						FILTER_VALIDATE_BOOL
					),
				],
			];
		}
		/** @var array{stream:array{verify_peer:bool,verify_peer_name:bool}}|null $context */

		$auth = null;
		if(isset($parts["user"]) && $parts["user"] !== "" && isset($parts["pass"])) {
			$auth = [rawurldecode($parts["user"]), rawurldecode($parts["pass"])];
		}
		elseif(isset($parts["pass"])) {
			$auth = rawurldecode($parts["pass"]);
		}

		$persistentId = null;
		if(
			isset($query["persistent"])
			&& filter_var($query["persistent"], FILTER_VALIDATE_BOOL)
		) {
			$persistentId = is_string($query["persistent_id"] ?? null)
				? $query["persistent_id"]
				: "phpgt-session";
		}

		$prefix = is_string($query["prefix"] ?? null)
			? $query["prefix"]
			: $name . self::DEFAULT_PREFIX_SEPARATOR;

		return [
			"host" => $host,
			"port" => (int)($parts["port"] ?? self::DEFAULT_PORT),
			"timeout" => (float)($query["timeout"] ?? 0),
			"readTimeout" => (float)($query["read_timeout"] ?? 0),
			"persistentId" => $persistentId,
			"prefix" => $prefix,
			"ttl" => (int)($query["ttl"] ?? ini_get("session.gc_maxlifetime")),
			"database" => isset($parts["path"])
				? (int)trim($parts["path"], "/")
				: 0,
			"auth" => $auth,
			"context" => $context,
		];
	}

	private function getKey(string $sessionId):string {
		return $this->prefix . $sessionId;
	}

	protected function createClient():Redis {
		if(!class_exists(Redis::class)) {
			throw new RuntimeException(
				"The phpredis extension is required to use Gt\\Session\\RedisHandler."
			);
		}

		return new Redis();
	}

	private function requireClient():Redis {
		if(is_null($this->client)) {
			throw new RuntimeException("RedisHandler::open() must be called before use.");
		}

		return $this->client;
	}
}
