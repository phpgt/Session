<?php
namespace GT\Session\Test;

use GT\Session\RedisHandler;
use PHPUnit\Framework\TestCase;
use Redis;

class RedisHandlerTest extends TestCase {
	public function testOpenParsesStandardDsn():void {
		$client = new TestRedisClient();
		$sut = new class($client) extends RedisHandler {
			public function __construct(private readonly TestRedisClient $client) {}

			protected function createClient():Redis {
				/** @phpstan-ignore-next-line */
				return $this->client;
			}
		};

		$sut->open(
			"redis://default:secret@example.internal:25061/2?prefix=prod:session:&ttl=1800&timeout=1.5&read_timeout=2.5&persistent=1&persistent_id=pool-a",
			"GT",
		);

		self::assertSame("example.internal", $client->connectParameters["host"]);
		self::assertSame(25061, $client->connectParameters["port"]);
		self::assertSame(1.5, $client->connectParameters["timeout"]);
		self::assertSame(2.5, $client->connectParameters["readTimeout"]);
		self::assertSame("pool-a", $client->connectParameters["persistentId"]);
		self::assertSame([["default", "secret"]], $client->authCalls);
		self::assertSame([2], $client->selectCalls);

		$sut->write("abc123", "payload");
		self::assertSame("payload", $sut->read("abc123"));
		self::assertSame(
			[
				"key" => "prod:session:abc123",
				"ttl" => 1800,
				"value" => "payload",
			],
			$client->setExCalls[0],
		);
	}

	public function testOpenParsesTlsDsn():void {
		$client = new TestRedisClient();
		$sut = new class($client) extends RedisHandler {
			public function __construct(private readonly TestRedisClient $client) {}

			protected function createClient():Redis {
				/** @phpstan-ignore-next-line */
				return $this->client;
			}
		};

		$sut->open(
			"rediss://:secret@example.internal?verify_peer=0&verify_peer_name=0",
			"GT",
		);
		$sut->write("abc123", "payload");

		self::assertSame("tls://example.internal", $client->connectParameters["host"]);
		self::assertSame(
			[
				"stream" => [
					"verify_peer" => false,
					"verify_peer_name" => false,
				],
			],
			$client->connectParameters["context"],
		);
		self::assertSame(["secret"], $client->authCalls);
		self::assertSame("payload", $client->data["GT:abc123"]);
	}

	public function testDestroyAndClose():void {
		$client = new TestRedisClient();
		$sut = new class($client) extends RedisHandler {
			public function __construct(private readonly TestRedisClient $client) {}

			protected function createClient():Redis {
				/** @phpstan-ignore-next-line */
				return $this->client;
			}
		};
		$sut->open("redis://cache.internal", "GT");
		$sut->write("abc123", "payload");

		self::assertTrue($sut->destroy("abc123"));
		self::assertSame("", $sut->read("abc123"));
		self::assertTrue($sut->close());
		self::assertTrue($client->closed);
	}
}

class TestRedisClient {
	/** @var array<string,mixed> */
	public array $connectParameters = [];
	/** @var array<int,array{key:string,ttl:int,value:string}> */
	public array $setExCalls = [];
	/** @var array<int,string> */
	public array $setCalls = [];
	/** @var array<int,array{string,string}|string> */
	public array $authCalls = [];
	/** @var array<int,int> */
	public array $selectCalls = [];
	/** @var array<string,string> */
	public array $data = [];
	public int $deleted = 0;
	public bool $closed = false;

	/**
	 * @param array{auth?:array{0:string|false|null,1?:string},stream?:array<string,mixed>}|null $context
	 */
	public function connect(
		string $host,
		int $port,
		float $timeout = 0,
		?string $persistentId = null,
		int $retryInterval = 0,
		float $readTimeout = 0,
		?array $context = null,
	):bool {
		$this->connectParameters = [
			"host" => $host,
			"port" => $port,
			"timeout" => $timeout,
			"persistentId" => $persistentId,
			"retryInterval" => $retryInterval,
			"readTimeout" => $readTimeout,
			"context" => $context,
		];
		return true;
	}

	/**
	 * @param array{string,string}|string $credentials
	 */
	public function auth(array|string $credentials):bool {
		$this->authCalls []= $credentials;
		return true;
	}

	public function select(int $database):bool {
		$this->selectCalls []= $database;
		return true;
	}

	public function get(string $key):string|false {
		return $this->data[$key] ?? false;
	}

	public function set(string $key, string $value):bool {
		$this->setCalls []= $key;
		$this->data[$key] = $value;
		return true;
	}

	public function setEx(string $key, int $ttl, string $value):bool {
		$this->setExCalls []= [
			"key" => $key,
			"ttl" => $ttl,
			"value" => $value,
		];
		$this->data[$key] = $value;
		return true;
	}

	public function del(string $key):int {
		unset($this->data[$key]);
		return ++$this->deleted;
	}

	public function close():bool {
		$this->closed = true;
		return true;
	}
}

if(!class_exists(Redis::class)) {
	class_alias(TestRedisClient::class, Redis::class);
}
