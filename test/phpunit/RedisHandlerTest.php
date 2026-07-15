<?php
namespace GT\Session\Test;

use GT\Session\RedisHandler;
use PHPUnit\Framework\TestCase;
use Redis;

if(!class_exists(Redis::class)) {
	class TestRedisClientBase {}
	class_alias(TestRedisClientBase::class, "Redis");
}

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

	public function testEmptyNativePhpSessionWriteDoesNotOverwriteStoredSession():void {
		$client = new TestRedisClient();
		$sut = new class($client) extends RedisHandler {
			public function __construct(private readonly TestRedisClient $client) {}

			protected function createClient():Redis {
				/** @phpstan-ignore-next-line */
				return $this->client;
			}
		};
		$sut->open("redis://cache.internal", "GT");
		$sut->write("abc123", "stored-session");
		$sut->write("abc123", "a:0:{}");

		self::assertSame("stored-session", $sut->read("abc123"));
	}

	public function testCommandRetriesAfterDisconnectedClient():void {
		$client = new TestRedisClient();
		$sut = new class($client) extends RedisHandler {
			public function __construct(private readonly TestRedisClient $client) {}

			protected function createClient():Redis {
				/** @phpstan-ignore-next-line */
				return $this->client;
			}
		};
		$sut->open("redis://cache.internal", "GT");
		$client->failNextSetEx = true;

		self::assertTrue($sut->write("abc123", "payload"));
		self::assertSame("payload", $sut->read("abc123"));
		self::assertSame(2, $client->connectCount);
	}
}

class TestRedisClient extends Redis {
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
	public int $connectCount = 0;
	public bool $failNextSetEx = false;
	public bool $closed = false;

	/**
	 * @param array{auth?:array{0:string|false|null,1?:string},stream?:array<string,mixed>}|null $context
	 */
	public function connect(
		string $host,
		int $port = 6379,
		float $timeout = 0,
		?string $persistent_id = null,
		int $retry_interval = 0,
		float $read_timeout = 0,
		?array $context = null,
	):bool {
		$this->connectCount++;
		$this->connectParameters = [
			"host" => $host,
			"port" => $port,
			"timeout" => $timeout,
			"persistentId" => $persistent_id,
			"retryInterval" => $retry_interval,
			"readTimeout" => $read_timeout,
			"context" => $context,
		];
		return true;
	}

	/**
	 * @param array{string,string}|string $credentials
	 */
	public function auth(mixed $credentials):Redis|bool {
		$this->authCalls []= $credentials;
		return true;
	}

	public function select(int $database):Redis|bool {
		$this->selectCalls []= $database;
		return true;
	}

	public function get(string $key):string|false {
		return $this->data[$key] ?? false;
	}

	public function set(string $key, mixed $value, mixed $options = null):Redis|string|bool {
		$this->setCalls []= $key;
		$this->data[$key] = (string)$value;
		return true;
	}

	public function setEx(string $key, int $ttl, mixed $value) {
		if($this->failNextSetEx) {
			$this->failNextSetEx = false;
			throw new \RedisException("Redis server went away");
		}

		$this->setExCalls []= [
			"key" => $key,
			"ttl" => $ttl,
			"value" => (string)$value,
		];
		$this->data[$key] = (string)$value;
		return true;
	}

	public function del(array|string $key, string ...$otherKeys):Redis|int|false {
		if(is_array($key)) {
			$key = reset($key);
		}

		unset($this->data[$key]);
		return ++$this->deleted;
	}

	public function close():bool {
		$this->closed = true;
		return true;
	}
}
