# migears/data-structure

![Version](https://img.shields.io/badge/version-2.0.0-blue)

A standalone Redis data structure service: explicit **Hash / List / Set / Sorted Set (ZSet)** semantics plus a **distributed lock**, targeting Redis-compatible servers (Redis, Valkey, KeyDB).

Sister package of `migears/cache`: `migears/cache` stays a pure PSR-16 key-value store, while this package exposes the data-structure layer. Both operate on the same Redis connection but keep separate interfaces and abstractions.

## Features

- PHP 8.1+, PSR-4 autoloading, namespace `MiGears\DataStructure`
- `DataStructureInterface` — a contract of **30 operations** across Hash (8), List (5), Set (5), ZSet (9) and key-level TTL (3)
- **Scalar-only** value semantics (`int|float|string`) — no transparent serialization, each structure defines its own value types
- `RedisDataStructure` — phpredis implementation with `withPrefix()` key namespacing
- `RedisLock` — distributed lock via `SET NX EX` with **safe owner-token release** (Lua compare-and-delete)
- Optional PSR-3 logger injection; all errors wrapped in `DataStructureException`
- Separate unit and integration (real Redis) test suites

## Installation

```bash
composer require migears/data-structure
```

> The Redis implementations require the `redis` extension: `pecl install redis`

## Handling the Redis Connection

The classes **never connect to Redis themselves**. They only wrap a connection you provide, so the connection — and its life-cycle — stays with the caller:

```php
use MiGears\DataStructure\RedisDataStructure;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$ds = new RedisDataStructure($redis);
```

In a miGears web environment, inject the connection in `MiRest` and obtain it through the service registry:

```php
$rest->set(Redis::class, fn () => (new Redis())->connect('127.0.0.1', 6379));

// in a resource:
$ds     = new RedisDataStructure($this->service(Redis::class));
$lock   = new RedisLock($this->service(Redis::class));
```

## Value Semantics

Values are **scalars only** (`int|float|string`). There is no transparent serialization of arrays or objects:

- **Hash** values — `int|float|string`
- **List** elements — `string`
- **Set** members — `string`
- **ZSet** — scores are `int|float`, normalized to `float` on read

Store anything more complex through the base PSR-16 cache (`migears/cache`) instead.

## Quick Start

```php
use MiGears\DataStructure\RedisDataStructure;

$ds = new RedisDataStructure($redis);

/* Hash */
$ds->hashSet('user:1', 'name', 'Alice');
$ds->hashGet('user:1', 'name');               // 'Alice'
$ds->hashIncrBy('user:1', 'points', 10);      // 10 (atomic)

/* List — FIFO queue (push to tail, pop from head) */
$ds->listPush('jobs', 'reindex', 'notify');   // 2  (length)
$ds->listPop('jobs');                         // 'reindex'
$ds->listLen('jobs');                         // 1

/* Set */
$ds->setAdd('tags:new', ['php', 'redis']);
$ds->setIsMember('tags:new', 'php');          // true
$ds->setMembers('tags:new');                  // ['php', 'redis']

/* Sorted Set (ZSet) — leaderboard */
$ds->zAdd('board', 100, 'alice');
$ds->zAdd('board', 90, 'bob');
$ds->zRange('board');                         // ['bob' => 90.0, 'alice' => 100.0] (asc)
$ds->zSelect('board');                        // ['alice' => 100.0, 'bob' => 90.0] (desc)
$ds->zIncrBy('board', 'alice', 5);            // 105.0

/* TTL */
$ds->expire('board', 3600);
$ds->ttl('board');                            // ~3600
$ds->persist('board');                        // remove expiry
```

### Distributed lock

```php
use MiGears\DataStructure\RedisLock;

$lock = new RedisLock($redis);

if ($lock->lock('job:report', 30)) {          // 30s TTL, acquire returns bool
    try {
        // critical section
    } finally {
        $lock->unlock('job:report');          // released only if we still own it
    }
}
```

`unlock()` is **safe**: it releases only when the key still holds this instance's owner token (Lua compare-and-delete). A lock that expired and was re-acquired is never deleted by a stale holder.

### Key prefix — namespacing

Share one Redis instance across apps/modules without key collisions:

```php
$tenantCache = $ds->withPrefix('tenant:42:');
$tenantCache->hashSet('config', 'theme', 'dark');   // stores "tenant:42:config"
```

## API Reference

### Hash

| Method | Returns | Description |
|--------|---------|-------------|
| `hashSet($key, $field, $value)` | `bool` | Set the value of a field |
| `hashGet($key, $field)` | `int\|float\|string\|null` | Get a field's value (null when missing) |
| `hashMultiGet($key, $fields)` | `array` | Get several fields, **existing ones only** |
| `hashGetAll($key)` | `array` | Get all fields of the hash |
| `hashDel($key, $field\|fields)` | `int` | Delete one or more fields |
| `hashExists($key, $field)` | `bool` | Check whether a field exists |
| `hashLen($key)` | `int` | Number of fields |
| `hashIncrBy($key, $field, $increment = 1)` | `int` | Atomic increment of a numeric field |

### List

| Method | Returns | Description |
|--------|---------|-------------|
| `listPush($key, ...$values)` | `int` | Push value(s) to the **tail** (rPush), returns new length |
| `listPop($key)` | `string\|null` | Pop from the **head** (lPop), null when empty |
| `listRange($key, $start = 0, $end = -1)` | `array` | Range of elements (supports negative indexes) |
| `listLen($key)` | `int` | Length of the list |
| `listIndex($key, $index)` | `string\|null` | Element at a given index (negates count from tail), null if out of range |

### Set

| Method | Returns | Description |
|--------|---------|-------------|
| `setAdd($key, $member\|members)` | `int` | Add one or more members, returns count added |
| `setRemove($key, $member\|members)` | `int` | Remove one or more members, returns count removed |
| `setMembers($key)` | `array` | All members |
| `setSize($key)` | `int` | Number of members |
| `setIsMember($key, $member)` | `bool` | Check membership |

### Sorted Set (ZSet)

| Method | Returns | Description |
|--------|---------|-------------|
| `zAdd($key, $score, $member)` | `int` | Add a member with a score |
| `zRemove($key, $member\|members)` | `int` | Remove one or more members |
| `zSize($key)` | `int` | Number of members |
| `zScore($key, $member)` | `int\|float\|null` | Score of a member, null when missing |
| `zRange($key, $start = 0, $end = -1)` | `array` | Members ordered by score **ascending** (by index range) |
| `zSelect($key, $min = 0, $max = 9999999999, $limit = 0, $order = 'DESC')` | `array` | Members filtered by score bounds and sorted; `$limit` 0 = unlimited |
| `zBatchAdd($key, $set)` | `bool` | Batch add from a flat `[score, member, score, member, ...]` array |
| `zInterStore($destKey, $keys, $aggregate = 'MIN')` | `int` | Intersection of multiple ZSets into `$destKey` |
| `zIncrBy($key, $member, $increment = 1)` | `int\|float` | Atomic score increment |

> `$min` / `$max` are **inclusive** score bounds. `zSelect` returns scores as `float`, keys ordered by score (default `DESC`).

### Key level (TTL)

| Method | Returns | Description |
|--------|---------|-------------|
| `ttl($key)` | `int` | Remaining TTL in seconds; `-1` = no expiry, `-2` = key missing |
| `expire($key, $ttl)` | `bool` | Set a TTL in seconds |
| `persist($key)` | `bool` | Remove the expiry |

## Error Handling & Logging

Every operation is wrapped in `try/catch`: underlying exceptions are logged through the injected PSR-3 logger (defaults to `NullLogger`) and re-thrown as a `MiGears\DataStructure\Exception\DataStructureException`:

```php
use MiGears\DataStructure\RedisDataStructure;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('ds');
$logger->pushHandler(new StreamHandler('ds.log'));

$ds = new RedisDataStructure($redis, $logger);
```

## Testing

```bash
composer test:unit         # logic tests via mocked Redis — no server needed
composer test:integration  # real Redis required
```

The integration suite auto-resolves a connection (first match wins) and starts a throwaway container if none is present:

- `REDIS_DSN` — e.g. `redis://:pass@host:6379/15` or bare `host:6379`
- `REDIS_HOST` / `REDIS_PORT` / `REDIS_DB` / `REDIS_AUTH`
- `docker` or `podman` — auto-spawn a `redis:7-alpine` container

If none is available, integration tests are **skipped**.

## License

MIT

---

# migears/data-structure

![Version](https://img.shields.io/badge/version-2.0.0-blue)

一个独立的 Redis 数据结构服务：显式的 **Hash / List / Set / 有序集合（ZSet）** 语义，外加一个**分布式锁**，面向 Redis 兼容服务器（Redis、Valkey、KeyDB）。

它是 `migears/cache` 的姊妹包：`migears/cache` 保持纯粹的 PSR-16 键值缓存，本包则提供数据结构层。二者共用同一条 Redis 连接，但保持独立的接口与抽象。

## 特性

- PHP 8.1+，PSR-4 自动加载，命名空间 `MiGears\DataStructure`
- `DataStructureInterface` —— 涵盖 Hash（8）、List（5）、Set（5）、ZSet（9）与 Key 级 TTL（3）共 **30 个操作**的契约
- **仅标量**取值语义（`int|float|string`）——不做透明序列化，每种结构自行定义取值类型
- `RedisDataStructure` —— phpredis 实现，支持 `withPrefix()` 键命名空间
- `RedisLock` —— 基于 `SET NX EX` 的分布式锁，支持**安全的属主令牌释放**（Lua 比较后删除）
- 可选 PSR-3 日志注入；所有错误统一包装为 `DataStructureException`
- 独立的单元测试与集成（真实 Redis）测试套件

## 安装

```bash
composer require migears/data-structure
```

> Redis 实现需要 `redis` 扩展：`pecl install redis`

## 连接的建立

这些类**自身不会连接 Redis**。它们只包装你传入的连接，连接及其生命周期由调用方掌控：

```php
use MiGears\DataStructure\RedisDataStructure;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$ds = new RedisDataStructure($redis);
```

在 miGears 的 web 环境中，把连接注入 `MiRest`，再经服务注册中心取得：

```php
$rest->set(Redis::class, fn () => (new Redis())->connect('127.0.0.1', 6379));

// 在资源类中：
$ds     = new RedisDataStructure($this->service(Redis::class));
$lock   = new RedisLock($this->service(Redis::class));
```

## 取值语义

值为**仅标量**（`int|float|string`），不对数组或对象做透明序列化：

- **Hash** 值 —— `int|float|string`
- **List** 元素 —— `string`
- **Set** 成员 —— `string`
- **ZSet** —— 分数为 `int|float`，读取时统一归一化为 `float`

如需存储更复杂的结构，请改用基础 PSR-16 缓存（`migears/cache`）。

## 快速开始

```php
use MiGears\DataStructure\RedisDataStructure;

$ds = new RedisDataStructure($redis);

/* Hash */
$ds->hashSet('user:1', 'name', 'Alice');
$ds->hashGet('user:1', 'name');               // 'Alice'
$ds->hashIncrBy('user:1', 'points', 10);      // 10（原子）

/* List —— FIFO 队列（尾部入队，头部出队） */
$ds->listPush('jobs', 'reindex', 'notify');   // 2（长度）
$ds->listPop('jobs');                         // 'reindex'
$ds->listLen('jobs');                         // 1

/* Set */
$ds->setAdd('tags:new', ['php', 'redis']);
$ds->setIsMember('tags:new', 'php');          // true
$ds->setMembers('tags:new');                  // ['php', 'redis']

/* 有序集合（ZSet）——排行榜 */
$ds->zAdd('board', 100, 'alice');
$ds->zAdd('board', 90, 'bob');
$ds->zRange('board');                         // ['bob' => 90.0, 'alice' => 100.0]（升序）
$ds->zSelect('board');                        // ['alice' => 100.0, 'bob' => 90.0]（降序）
$ds->zIncrBy('board', 'alice', 5);            // 105.0

/* TTL */
$ds->expire('board', 3600);
$ds->ttl('board');                            // 约 3600
$ds->persist('board');                        // 移除过期时间
```

### 分布式锁

```php
use MiGears\DataStructure\RedisLock;

$lock = new RedisLock($redis);

if ($lock->lock('job:report', 30)) {          // 30s TTL，获取返回 bool
    try {
        // 临界区
    } finally {
        $lock->unlock('job:report');          // 仅当仍持有该锁时才会被释放
    }
}
```

`unlock()` 是**安全**的：只有当 key 仍持有本实例的属主令牌时才会释放（Lua 比较后删除）。已过期并被重新获取的锁，绝不会被陈旧的持有者误删。

### 键前缀——命名空间

多应用/模块共用一条 Redis 时避免键冲突：

```php
$tenantCache = $ds->withPrefix('tenant:42:');
$tenantCache->hashSet('config', 'theme', 'dark');   // 实际存储 "tenant:42:config"
```

## API 参考

### Hash

| 方法 | 返回 | 说明 |
|------|------|------|
| `hashSet($key, $field, $value)` | `bool` | 设置某个字段的值 |
| `hashGet($key, $field)` | `int\|float\|string\|null` | 读取某个字段的值（不存在返回 null） |
| `hashMultiGet($key, $fields)` | `array` | 批量读取字段，**仅返回已存在的** |
| `hashGetAll($key)` | `array` | 读取哈希的全部字段 |
| `hashDel($key, $field\|fields)` | `int` | 删除一个或多个字段 |
| `hashExists($key, $field)` | `bool` | 字段是否存在 |
| `hashLen($key)` | `int` | 字段数量 |
| `hashIncrBy($key, $field, $increment = 1)` | `int` | 数值字段原子自增 |

### List

| 方法 | 返回 | 说明 |
|------|------|------|
| `listPush($key, ...$values)` | `int` | 向**尾部**入队（rPush），返回新长度 |
| `listPop($key)` | `string\|null` | 从**头部**出队（lPop），空时返回 null |
| `listRange($key, $start = 0, $end = -1)` | `array` | 元素区间（支持负索引） |
| `listLen($key)` | `int` | 列表长度 |
| `listIndex($key, $index)` | `string\|null` | 指定索引处的元素（负值从尾部计数），越界返回 null |

### Set

| 方法 | 返回 | 说明 |
|------|------|------|
| `setAdd($key, $member\|members)` | `int` | 添加一个或多个成员，返回新增数量 |
| `setRemove($key, $member\|members)` | `int` | 删除一个或多个成员，返回删除数量 |
| `setMembers($key)` | `array` | 全部成员 |
| `setSize($key)` | `int` | 成员数量 |
| `setIsMember($key, $member)` | `bool` | 是否包含某成员 |

### 有序集合（ZSet）

| 方法 | 返回 | 说明 |
|------|------|------|
| `zAdd($key, $score, $member)` | `int` | 添加一个带分数的成员 |
| `zRemove($key, $member\|members)` | `int` | 删除一个或多个成员 |
| `zSize($key)` | `int` | 成员数量 |
| `zScore($key, $member)` | `int\|float\|null` | 某成员的分数，不存在返回 null |
| `zRange($key, $start = 0, $end = -1)` | `array` | 按分数**升序**返回索引区间内的成员 |
| `zSelect($key, $min = 0, $max = 9999999999, $limit = 0, $order = 'DESC')` | `array` | 按分数区间筛选并排序；`$limit` 为 0 表示不限条数 |
| `zBatchAdd($key, $set)` | `bool` | 批量添加，`$set` 为扁平数组 `[score, member, score, member, ...]` |
| `zInterStore($destKey, $keys, $aggregate = 'MIN')` | `int` | 多个 ZSet 求交集写入 `$destKey` |
| `zIncrBy($key, $member, $increment = 1)` | `int\|float` | 分数原子自增 |

> `$min` / `$max` 为**闭区间**分数边界。`zSelect` 返回的分数均为 `float`，默认按分数**降序**。

### Key 级（TTL）

| 方法 | 返回 | 说明 |
|------|------|------|
| `ttl($key)` | `int` | 剩余 TTL（秒）；`-1` = 无过期，`-2` = key 不存在 |
| `expire($key, $ttl)` | `bool` | 设置以秒为单位的过期时间 |
| `persist($key)` | `bool` | 移除过期时间 |

## 错误处理与日志

每个操作都用 `try/catch` 包裹：底层异常会通过注入的 PSR-3 日志器记录（默认 `NullLogger`），然后以 `MiGears\DataStructure\Exception\DataStructureException` 重新抛出：

```php
use MiGears\DataStructure\RedisDataStructure;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('ds');
$logger->pushHandler(new StreamHandler('ds.log'));

$ds = new RedisDataStructure($redis, $logger);
```

## 测试

```bash
composer test:unit         # 通过 mock Redis 跑逻辑测试——无需服务器
composer test:integration  # 需要真实 Redis
```

集成套件会自动解析连接（按顺序取首个可用），若无可用连接则会自动拉起一个临时容器：

- `REDIS_DSN` —— 例如 `redis://:pass@host:6379/15` 或裸 `host:6379`
- `REDIS_HOST` / `REDIS_PORT` / `REDIS_DB` / `REDIS_AUTH`
- `docker` 或 `podman` —— 自动拉起 `redis:7-alpine` 容器

若均不可用，集成测试会被**跳过**。

## License

MIT