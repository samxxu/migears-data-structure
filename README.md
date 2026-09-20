# migears/data-structure

![Version](https://img.shields.io/badge/version-1.0.0-blue)

A standalone Redis data structure service: explicit Hash / List / Set / ZSet semantics plus a distributed lock, for Redis-compatible servers (Redis, Valkey, KeyDB).

Sister package of `migears/cache`: cache stays a pure PSR-16 key-value store, this package exposes the data structure layer. Both share a Redis connection, but keep separate interfaces and abstractions.

## Features

- PHP 8.1+, PSR-4 autoloading, namespace `MiGears\DataStructure`
- `DataStructureInterface` — contract for Hash (8), List (5), Set (5), ZSet (9) and key-level TTL operations
- Scalar-only value semantics (`int|float|string`), no transparent serialization
- `RedisDataStructure` — phpredis implementation with `withPrefix()` namespacing
- `RedisLock` — distributed lock via `SET NX EX`, safe owner-token release using a Lua compare-and-delete
- PSR-3 logger injection; exceptions wrapped in `DataStructureException`
- Separate unit and integration (real Redis) test suites

## Installation

```bash
composer require migears/data-structure
```

> Requires the `redis` extension for the Redis implementations: `pecl install redis`

## Quick Start

### RedisDataStructure

```php
use MiGears\DataStructure\RedisDataStructure;

$ds = new RedisDataStructure(['host' => '127.0.0.1', 'port' => 6379]);
// or: new RedisDataStructure($redis); // pass an already connected instance

$ds->hashSet('user:1', 'name', 'Alice');
$ds->hashGet('user:1', 'name');            // 'Alice'

$ds->zAdd('leaderboard', 100, 'alice');
$ds->zAdd('leaderboard', 90, 'bob');
$ds->zSelect('leaderboard');               // ['bob' => 90.0, 'alice' => 100.0]
```

### RedisLock

```php
use MiGears\DataStructure\RedisLock;

$lock = new RedisLock($redis);
if ($lock->lock('job:report', 30)) {
    try {
        // critical section
    } finally {
        $lock->unlock('job:report');
    }
}
```

## Testing

```bash
composer test:unit         # logic tests, no Redis needed
composer test:integration  # requires a running Redis (REDIS_HOST / REDIS_PORT / REDIS_DB env)
```

## License

MIT
