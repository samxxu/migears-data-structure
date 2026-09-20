<?php

declare(strict_types=1);

namespace MiGears\DataStructure;

use MiGears\DataStructure\Exception\DataStructureException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Redis;

/**
 * Redis data structure implementation.
 *
 * Value semantics: scalars only (int|float|string), no transparent serialization —
 * each structure defines its own value types. ZSet scores are normalized to float.
 *
 * Usage:
 *   new RedisDataStructure($redis);            // pass an already connected instance
 *   new RedisDataStructure(['host' => '...']); // connection parameters
 */
class RedisDataStructure implements DataStructureInterface
{
    private readonly Redis $redis;
    private readonly LoggerInterface $logger;
    private string $prefix = '';

    public function __construct(
        Redis|array $redis,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->redis = $redis instanceof Redis ? $redis : $this->connect($redis);
    }

    /** @param array<string, mixed> $config */
    private function connect(array $config): Redis
    {
        try {
            $redis = new Redis();
            $host = (string) ($config['host'] ?? '127.0.0.1');
            $port = (int) ($config['port'] ?? 6379);
            $timeout = (float) ($config['timeout'] ?? 0.0);
            $persistent = (bool) ($config['persistent'] ?? false);
            $persistent ? $redis->pconnect($host, $port, $timeout) : $redis->connect($host, $port, $timeout);
            isset($config['auth']) && $redis->auth($config['auth']);
            isset($config['dbindex']) && $redis->select((int) $config['dbindex']);
            return $redis;
        } catch (\Throwable $e) {
            $this->logger->error('Redis connection failed', ['exception' => $e]);
            throw new DataStructureException('Redis connection failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function withPrefix(string $prefix): static
    {
        $copy = new self($this->redis, $this->logger);
        $copy->prefix = $prefix;
        return $copy;
    }

    /* ===== Hash ===== */

    public function hashSet(string $key, string $field, int|float|string $value): bool
    {
        try {
            return $this->redis->hSet($this->prefix . $key, $field, $value) !== false;
        } catch (\Throwable $e) {
            $this->logger->error('hashSet error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function hashGet(string $key, string $field): int|float|string|null
    {
        try {
            $value = $this->redis->hGet($this->prefix . $key, $field);
            return $value === false ? null : $value;
        } catch (\Throwable $e) {
            $this->logger->error('hashGet error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function hashMultiGet(string $key, array $fields): array
    {
        try {
            $values = $this->redis->hMGet($this->prefix . $key, $fields);
            if ($values === false) {
                return [];
            }
            // phpredis fills missing fields with false; drop them to keep the
            // "only existing fields" contract.
            return array_filter($values, static fn($v) => $v !== false);
        } catch (\Throwable $e) {
            $this->logger->error('hashMultiGet error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function hashGetAll(string $key): array
    {
        try {
            $values = $this->redis->hGetAll($this->prefix . $key);
            return $values === false ? [] : $values;
        } catch (\Throwable $e) {
            $this->logger->error('hashGetAll error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function hashDel(string $key, string|array $field): int
    {
        try {
            $fields = is_array($field) ? $field : [$field];
            return $this->redis->hDel($this->prefix . $key, ...$fields);
        } catch (\Throwable $e) {
            $this->logger->error('hashDel error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function hashExists(string $key, string $field): bool
    {
        try {
            return $this->redis->hExists($this->prefix . $key, $field);
        } catch (\Throwable $e) {
            $this->logger->error('hashExists error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function hashLen(string $key): int
    {
        try {
            return $this->redis->hLen($this->prefix . $key);
        } catch (\Throwable $e) {
            $this->logger->error('hashLen error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function hashIncrBy(string $key, string $field, int $increment = 1): int
    {
        try {
            return $this->redis->hIncrBy($this->prefix . $key, $field, $increment);
        } catch (\Throwable $e) {
            $this->logger->error('hashIncrBy error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /* ===== List ===== */

    public function listPush(string $key, string ...$values): int
    {
        try {
            return $this->redis->rPush($this->prefix . $key, ...$values);
        } catch (\Throwable $e) {
            $this->logger->error('listPush error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listPop(string $key): string|null
    {
        try {
            $value = $this->redis->lPop($this->prefix . $key);
            return $value === false ? null : $value;
        } catch (\Throwable $e) {
            $this->logger->error('listPop error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listRange(string $key, int $start = 0, int $end = -1): array
    {
        try {
            $values = $this->redis->lRange($this->prefix . $key, $start, $end);
            return $values === false ? [] : $values;
        } catch (\Throwable $e) {
            $this->logger->error('listRange error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listLen(string $key): int
    {
        try {
            return $this->redis->lLen($this->prefix . $key);
        } catch (\Throwable $e) {
            $this->logger->error('listLen error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function listIndex(string $key, int $index): string|null
    {
        try {
            $value = $this->redis->lIndex($this->prefix . $key, $index);
            return $value === false ? null : $value;
        } catch (\Throwable $e) {
            $this->logger->error('listIndex error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /* ===== Set ===== */

    public function setAdd(string $key, string|array $member): int
    {
        try {
            $members = is_array($member) ? $member : [$member];
            return $this->redis->sAdd($this->prefix . $key, ...$members);
        } catch (\Throwable $e) {
            $this->logger->error('setAdd error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setRemove(string $key, string|array $member): int
    {
        try {
            $members = is_array($member) ? $member : [$member];
            return $this->redis->sRem($this->prefix . $key, ...$members);
        } catch (\Throwable $e) {
            $this->logger->error('setRemove error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setMembers(string $key): array
    {
        try {
            $members = $this->redis->sMembers($this->prefix . $key);
            return $members === false ? [] : $members;
        } catch (\Throwable $e) {
            $this->logger->error('setMembers error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setSize(string $key): int
    {
        try {
            return $this->redis->sCard($this->prefix . $key);
        } catch (\Throwable $e) {
            $this->logger->error('setSize error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setIsMember(string $key, string $member): bool
    {
        try {
            return $this->redis->sIsMember($this->prefix . $key, $member);
        } catch (\Throwable $e) {
            $this->logger->error('setIsMember error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /* ===== Sorted Set (ZSet) ===== */

    public function zAdd(string $key, int|float $score, string $member): int
    {
        try {
            return $this->redis->zAdd($this->prefix . $key, $score, $member);
        } catch (\Throwable $e) {
            $this->logger->error('zAdd error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zRemove(string $key, string|array $member): int
    {
        try {
            $members = is_array($member) ? $member : [$member];
            return $this->redis->zRem($this->prefix . $key, ...$members);
        } catch (\Throwable $e) {
            $this->logger->error('zRemove error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zSize(string $key): int
    {
        try {
            return $this->redis->zCard($this->prefix . $key);
        } catch (\Throwable $e) {
            $this->logger->error('zSize error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zScore(string $key, string $member): int|float|null
    {
        try {
            $score = $this->redis->zScore($this->prefix . $key, $member);
            return $score === false ? null : (float) $score;
        } catch (\Throwable $e) {
            $this->logger->error('zScore error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zRange(string $key, int $start = 0, int $end = -1): array
    {
        try {
            $members = $this->redis->zRange($this->prefix . $key, $start, $end, true);
            return $members === false ? [] : array_map(static fn($score) => (float) $score, $members);
        } catch (\Throwable $e) {
            $this->logger->error('zRange error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zSelect(string $key, int|float $min = 0, int|float $max = 9999999999, int $limit = 0, string $order = 'DESC'): array
    {
        try {
            $pkey = $this->prefix . $key;
            $opts = $limit > 0 ? ['withscores' => true, 'limit' => [0, $limit]] : ['withscores' => true];
            $members = $order === 'ASC'
                ? $this->redis->zRangeByScore($pkey, $min, $max, $opts)
                : $this->redis->zRevRangeByScore($pkey, $max, $min, $opts);
            return $members === false ? [] : array_map(static fn($score) => (float) $score, $members);
        } catch (\Throwable $e) {
            $this->logger->error('zSelect error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zBatchAdd(string $key, array $set): bool
    {
        if ($set === []) {
            return false;
        }
        try {
            // Pipeline: one round trip, atomic execution (avoids the ambiguous
            // multi-pair zAdd signature across phpredis versions).
            $pkey = $this->prefix . $key;
            $pipe = $this->redis->multi(Redis::PIPELINE);
            $count = count($set);
            for ($i = 0; $i + 1 < $count; $i += 2) {
                $pipe->zAdd($pkey, $set[$i], $set[$i + 1]);
            }
            $pipe->exec();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('zBatchAdd error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zInterStore(string $destKey, array $keys, string $aggregate = 'MIN'): int
    {
        try {
            $pkeys = array_map(fn($k) => $this->prefix . $k, $keys);
            return $this->redis->zInterStore($this->prefix . $destKey, $pkeys, null, $aggregate);
        } catch (\Throwable $e) {
            $this->logger->error('zInterStore error', ['destKey' => $destKey, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zIncrBy(string $key, string $member, int|float $increment = 1): int|float
    {
        try {
            return (float) $this->redis->zIncrBy($this->prefix . $key, $increment, $member);
        } catch (\Throwable $e) {
            $this->logger->error('zIncrBy error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /* ===== Key level ===== */

    public function ttl(string $key): int
    {
        try {
            return $this->redis->ttl($this->prefix . $key);
        } catch (\Throwable $e) {
            $this->logger->error('ttl error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function expire(string $key, int $ttl): bool
    {
        try {
            return $this->redis->expire($this->prefix . $key, $ttl);
        } catch (\Throwable $e) {
            $this->logger->error('expire error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function persist(string $key): bool
    {
        try {
            return $this->redis->persist($this->prefix . $key);
        } catch (\Throwable $e) {
            $this->logger->error('persist error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
