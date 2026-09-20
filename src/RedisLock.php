<?php

declare(strict_types=1);

namespace MiGears\DataStructure;

use MiGears\DataStructure\Exception\DataStructureException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Redis;

/**
 * Distributed lock built on SET NX EX with owner-token release.
 *
 * unlock() releases the lock only if the key still holds this instance's
 * token (Lua compare-and-delete), so an expired-and-reacquired lock is
 * never deleted by a stale holder.
 *
 * This class never connects to Redis on its own. It only wraps an already
 * connected Redis instance; establishing the connection belongs to the caller.
 */
class RedisLock
{
    private const UNLOCK_SCRIPT = 'if redis.call("get", KEYS[1]) == ARGV[1] then return redis.call("del", KEYS[1]) else return 0 end';

    private readonly Redis $redis;
    private readonly LoggerInterface $logger;
    private string $prefix = '';
    /** @var array<string, string> key => owner token */
    private array $tokens = [];

    public function __construct(
        Redis $redis,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->redis = $redis;
    }

    public function withPrefix(string $prefix): static
    {
        $copy = new self($this->redis, $this->logger);
        $copy->prefix = $prefix;
        return $copy;
    }

    public function lock(string $key, int $ttl): bool
    {
        try {
            $pkey = $this->prefix . $key;
            $token = bin2hex(random_bytes(16));
            $acquired = $this->redis->set($pkey, $token, ['nx', 'ex' => $ttl]);
            if ($acquired) {
                $this->tokens[$pkey] = $token;
            }
            return (bool) $acquired;
        } catch (\Throwable $e) {
            $this->logger->error('RedisLock lock error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function unlock(string $key): bool
    {
        try {
            $pkey = $this->prefix . $key;
            $token = $this->tokens[$pkey] ?? null;
            if ($token === null) {
                return false;
            }
            $released = $this->redis->eval(self::UNLOCK_SCRIPT, [$pkey, $token], 1);
            unset($this->tokens[$pkey]);
            return (bool) $released;
        } catch (\Throwable $e) {
            $this->logger->error('RedisLock unlock error', ['key' => $key, 'exception' => $e]);
            throw new DataStructureException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
