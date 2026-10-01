<?php

declare(strict_types=1);

namespace MiGears\DataStructure;

use MiGears\DataStructure\Exception\DataStructureException;
use Psr\Log\LoggerInterface;
use Redis;

/**
 * Redis data structure implementation.
 *
 * Value semantics: scalars only (int|float|string), no transparent serialization —
 * each structure defines its own value types. ZSet scores are normalized to float.
 *
 * This class never connects to Redis on its own. It only wraps an already
 * connected Redis instance; establishing the connection belongs to the caller
 * (inject it via MiRest and obtain with resolve() in a web env).
 *
 * Usage:
 *   new RedisDataStructure($redis, $logger);   // pass an already connected instance and a logger
 */
class RedisDataStructure implements DataStructureInterface
{
    private readonly Redis $redis;
    private readonly LoggerInterface $logger;
    private string $prefix = '';

    /**
     * @param LoggerInterface $logger Required: a data structure that reports nothing while
     *        looking healthy is the failure this parameter exists to prevent. Pass an explicit
     *        NullLogger only when discarding these messages is a deliberate choice.
     */
    public function __construct(
        Redis $redis,
        LoggerInterface $logger,
    ) {
        $this->logger = $logger;
        $this->redis = $redis;
    }

    public function withPrefix(string $prefix): static
    {
        // clone, not `new static(...)`: a subclass with an incompatible
        // constructor must not make prefixing fail, and cloning carries the
        // connection and logger over without re-reading them.
        $copy = clone $this;
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
            return $this->intOrFail($this->redis->hDel($this->prefix . $key, ...$fields), 'hashDel');
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
            return $this->intOrFail($this->redis->hLen($this->prefix . $key), 'hashLen');
        } catch (\Throwable $e) {
            $this->logger->error('hashLen error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function hashIncrBy(string $key, string $field, int $increment = 1): int
    {
        try {
            return $this->intOrFail($this->redis->hIncrBy($this->prefix . $key, $field, $increment), 'hashIncrBy');
        } catch (\Throwable $e) {
            $this->logger->error('hashIncrBy error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /* ===== List ===== */

    public function listPush(string $key, string ...$values): int
    {
        try {
            return $this->intOrFail($this->redis->rPush($this->prefix . $key, ...$values), 'listPush');
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
            return $this->intOrFail($this->redis->lLen($this->prefix . $key), 'listLen');
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
            return $this->intOrFail($this->redis->sAdd($this->prefix . $key, ...$members), 'setAdd');
        } catch (\Throwable $e) {
            $this->logger->error('setAdd error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setRemove(string $key, string|array $member): int
    {
        try {
            $members = is_array($member) ? $member : [$member];
            return $this->intOrFail($this->redis->sRem($this->prefix . $key, ...$members), 'setRemove');
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
            return $this->intOrFail($this->redis->sCard($this->prefix . $key), 'setSize');
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
            return $this->intOrFail($this->redis->zAdd($this->prefix . $key, $score, $member), 'zAdd');
        } catch (\Throwable $e) {
            $this->logger->error('zAdd error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zRemove(string $key, string|array $member): int
    {
        try {
            $members = is_array($member) ? $member : [$member];
            return $this->intOrFail($this->redis->zRem($this->prefix . $key, ...$members), 'zRemove');
        } catch (\Throwable $e) {
            $this->logger->error('zRemove error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zSize(string $key): int
    {
        try {
            return $this->intOrFail($this->redis->zCard($this->prefix . $key), 'zSize');
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
            // Case-insensitive: 'asc' must not silently fall through to DESC.
            $members = strtoupper($order) === 'ASC'
                ? $this->redis->zRangeByScore($pkey, (string) $min, (string) $max, $opts)
                : $this->redis->zRevRangeByScore($pkey, (string) $max, (string) $min, $opts);
            return $members === false ? [] : array_map(static fn($score) => (float) $score, $members);
        } catch (\Throwable $e) {
            $this->logger->error('zSelect error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zBatchAdd(string $key, array $set): bool
    {
        // Nothing to add is a no-op, not a mistake: false, and no round trip.
        if ($set === []) {
            return false;
        }
        // An odd number of entries would silently drop the trailing score, so it
        // is a caller mistake rather than something to guess at.
        if (count($set) % 2 !== 0) {
            throw new DataStructureException(
                'zBatchAdd expects a flat [score, member, ...] list; got ' . count($set) . ' entries'
            );
        }
        try {
            // Pipeline: one round trip for every pair. This batches the commands
            // but is NOT a transaction (there is no MULTI/EXEC), so a failing
            // pair leaves the pairs before it applied.
            $pkey = $this->prefix . $key;
            $pipe = $this->redis->multi(Redis::PIPELINE);
            $count = count($set);
            for ($i = 0; $i + 1 < $count; $i += 2) {
                $pipe->zAdd($pkey, $set[$i], $set[$i + 1]);
            }
            $results = $pipe->exec();
            // phpredis puts a false entry in the result array for a command that
            // failed. Answering true here would hide a partial write.
            if (!is_array($results) || in_array(false, $results, true)) {
                throw new DataStructureException('zBatchAdd failed: a command in the pipeline did not apply');
            }
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
            return $this->intOrFail($this->redis->zInterStore($this->prefix . $destKey, $pkeys, null, $aggregate), 'zInterStore');
        } catch (\Throwable $e) {
            $this->logger->error('zInterStore error', ['destKey' => $destKey, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function zIncrBy(string $key, string $member, int|float $increment = 1): int|float
    {
        try {
            return $this->floatOrFail($this->redis->zIncrBy($this->prefix . $key, $increment, $member), 'zIncrBy');
        } catch (\Throwable $e) {
            $this->logger->error('zIncrBy error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /* ===== Key level ===== */

    public function ttl(string $key): int
    {
        try {
            return $this->intOrFail($this->redis->ttl($this->prefix . $key), 'ttl');
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

    /* ===== Internal ===== */

    /**
     * phpredis answers false for a command that failed instead of throwing, and
     * an int return type cannot carry that: the engine raises a TypeError whose
     * own wording leaks out to the caller. Report the failure as this module's
     * exception instead, so every method fails the same way.
     *
     * @param mixed $value raw value returned by the client
     */
    private function intOrFail(mixed $value, string $operation): int
    {
        if (!is_int($value)) {
            throw new DataStructureException("{$operation} failed: expected an integer, got " . gettype($value));
        }
        return $value;
    }

    /**
     * Same contract as intOrFail() for the score-returning methods, where a bare
     * cast would turn the failure into a plausible 0.0 instead of raising.
     *
     * @param mixed $value raw value returned by the client
     */
    private function floatOrFail(mixed $value, string $operation): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new DataStructureException("{$operation} failed: expected a number, got " . gettype($value));
        }
        return (float) $value;
    }
}
