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

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
	public function gc(int $maxLifeTime):int|false {
		return 0;
	}
	// phpcs:enable

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

		[$host, $context] = $this->parseHostAndContext($parts, $query);

		return [
			"host" => $host,
			"port" => (int)($parts["port"] ?? self::DEFAULT_PORT),
			"timeout" => (float)($query["timeout"] ?? 0),
			"readTimeout" => (float)($query["read_timeout"] ?? 0),
			"persistentId" => $this->parsePersistentId($query),
			"prefix" => $this->parsePrefix($query, $name),
			"ttl" => (int)($query["ttl"] ?? ini_get("session.gc_maxlifetime")),
			"database" => $this->parseDatabase($parts),
			"auth" => $this->parseAuth($parts),
			"context" => $context,
		];
	}

	/**
	 * @param array<string,mixed> $parts
	 * @param array<string,mixed> $query
	 * @return array{
	 *   0:string,
	 *   1:array{stream:array{verify_peer:bool,verify_peer_name:bool}}|null
	 * }
	 */
	private function parseHostAndContext(array $parts, array $query):array {
		$scheme = strtolower($parts["scheme"] ?? "redis");
		$host = $parts["host"];

		if(!in_array($scheme, ["rediss", "tls"], true)) {
			return [$host, null];
		}

		return [
			"tls://$host",
			[
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
			],
		];
	}

	/**
	 * @param array<string,mixed> $parts
	 * @return array{string,string}|string|null
	 */
	private function parseAuth(array $parts):array|string|null {
		if(isset($parts["user"]) && $parts["user"] !== "" && isset($parts["pass"])) {
			return [rawurldecode($parts["user"]), rawurldecode($parts["pass"])];
		}

		if(isset($parts["pass"])) {
			return rawurldecode($parts["pass"]);
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $query
	 */
	private function parsePersistentId(array $query):?string {
		if(
			!isset($query["persistent"])
			|| !filter_var($query["persistent"], FILTER_VALIDATE_BOOL)
		) {
			return null;
		}

		return is_string($query["persistent_id"] ?? null)
			? $query["persistent_id"]
			: "phpgt-session";
	}

	/**
	 * @param array<string,mixed> $query
	 */
	private function parsePrefix(array $query, string $name):string {
		return is_string($query["prefix"] ?? null)
			? $query["prefix"]
			: $name . self::DEFAULT_PREFIX_SEPARATOR;
	}

	/**
	 * @param array<string,mixed> $parts
	 */
	private function parseDatabase(array $parts):int {
		return isset($parts["path"])
			? (int)trim($parts["path"], "/")
			: 0;
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
