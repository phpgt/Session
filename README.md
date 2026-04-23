# Encapsulated user sessions.

This library is a simple object oriented alternative to the $_SESSION superglobal allowing application code to be passed encapsulated `SessionStore` objects, so areas of code can have access to their own Session area without having full read-write access to all session variables.

Sessions are addressed using dot notation, allowing for handling categories of session data. This is particularly useful when dealing with user authentication, for example.

***

<a href="https://github.com/PhpGt/Session/actions" target="_blank">
	<img src="https://badge.status.php.gt/session-build.svg" alt="Build status" />
</a>
<a href="https://app.codacy.com/gh/PhpGt/Session" target="_blank">
	<img src="https://badge.status.php.gt/session-quality.svg" alt="Code quality" />
</a>
<a href="https://app.codecov.io/gh/PhpGt/Session" target="_blank">
	<img src="https://badge.status.php.gt/session-coverage.svg" alt="Code coverage" />
</a>
<a href="https://packagist.org/packages/PhpGt/Session" target="_blank">
	<img src="https://badge.status.php.gt/session-version.svg" alt="Current version" />
</a>
<a href="http://www.php.gt/session" target="_blank">
	<img src="https://badge.status.php.gt/session-docs.svg" alt="PHP.GT/Session documentation" />
</a>

## Example usage: Welcome a user by their first name or log out the user

```php
if($session->contains("auth")) {
// Remove the *whole* auth section of the session on logout.
	if($action === "logout") {
		$session->delete("auth");
	}
	else {
// Output a variable within the auth namespace:
		$message = "Welcome back, " . $session->getString("auth.user.name");
	}
}
else {
// Pass the "auth" store to a class, so it 
// can't read/write to other session variables:
	AuthenticationSystem::beginLogin($session->getStore("auth"));
}
```

## Redis session storage

This package now includes `GT\Session\RedisHandler` for shared session storage.
It works with Redis-compatible backends such as Redis and Valkey, and is intended
for deployments where application nodes are disposable and session state needs to
survive traffic moving between servers.

`RedisHandler` expects `save_path` to be a DSN rather than a filesystem path.
It uses the `phpredis` extension at runtime.

Example production config:

```ini
[session]
handler=GT\Session\RedisHandler
save_path=rediss://default:secret@example-redis.internal:25061/0?prefix=GT:&ttl=1440
name=GT
use_cookies=true
```

Supported DSN forms:

- `redis://host:6379`
- `redis://:password@host:6379/0`
- `redis://username:password@host:6379/0`
- `rediss://username:password@host:6379/0`

Useful query parameters:

- `prefix`: key prefix for stored sessions, defaults to `<session-name>:`
- `ttl`: session lifetime in seconds, defaults to `session.gc_maxlifetime`
- `timeout`: connection timeout in seconds
- `read_timeout`: socket read timeout in seconds
- `persistent=1`: enable persistent connections
- `persistent_id`: optional persistent connection pool id
- `verify_peer=0` / `verify_peer_name=0`: optional TLS verification flags

# Proudly sponsored by

[JetBrains Open Source sponsorship program](https://www.jetbrains.com/community/opensource/)

[![JetBrains logo.](https://resources.jetbrains.com/storage/products/company/brand/logos/jetbrains.svg)](https://www.jetbrains.com/community/opensource/)
