<?php

declare(strict_types=1);

namespace MiGears\DataStructure\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests needing a real Redis server.
 *
 * Connection resolution (first match wins):
 *   1. REDIS_DSN                 e.g. redis://:pass@host:6379/15 or host:6379
 *   2. REDIS_HOST (+ _PORT/_DB/_AUTH)  point at any externally managed Redis
 *   3. docker|podman            auto-spawn a throwaway redis container
 *   none available              -> test marked skipped
 *   resolved but unreachable    -> test marked skipped (with the reason)
 *
 * DATABASE SELECTION IS DESTRUCTIVE: every test method runs flushDB(), so the
 * resolved database has all its keys deleted. A database that is named
 * explicitly is honoured exactly, including db 0 (`redis://host:6379/0`, or
 * REDIS_DB=0); a source that never names one (bare `host:6379`, no REDIS_DB)
 * falls back to DEFAULT_DB rather than silently defaulting to db 0. Never point
 * REDIS_* at a database that holds real data.
 *
 * The container/connection is created ONCE per test class and shared across
 * its test methods; each test still runs flushDB() for isolation. The
 * throwaway container is removed after the class has finished.
 */
abstract class RedisTestCase extends TestCase
{
    /** Database used when the connection source does not name one. */
    private const DEFAULT_DB = 15;

    private static ?\Redis $sharedRedis = null;
    private static bool $resolved = false;
    private static ?string $connectError = null;
    private static ?string $containerEngine = null;
    private static ?string $containerId = null;

    protected \Redis $redis;

    protected function setUp(): void
    {
        $redis = self::sharedRedis();
        if ($redis === null) {
            $reason = self::$connectError ?? 'no connection source available (REDIS_DSN / REDIS_HOST / docker / podman)';
            $this->markTestSkipped(
                "Redis unavailable: {$reason}. Set REDIS_DSN / REDIS_HOST, start a local Redis, or install docker/podman."
            );
        }
        $this->redis = $redis;
        $this->redis->flushDB();
    }

    public static function tearDownAfterClass(): void
    {
        self::destroyContainer();
        self::$sharedRedis = null;
        self::$resolved = false;
        self::$connectError = null;
    }

    private static function sharedRedis(): ?\Redis
    {
        if (self::$resolved) {
            return self::$sharedRedis;
        }
        self::$resolved = true;

        $config = self::locateRedis();
        if ($config === null) {
            return null;
        }

        try {
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port'], 2.0);
            if ($config['auth'] !== '') {
                $redis->auth($config['auth']);
            }
            // "Unspecified" (null) falls back to DEFAULT_DB; an explicit 0 stays 0.
            $db = $config['db'] ?? self::DEFAULT_DB;
            if (!$redis->select($db) || $redis->getDbNum() !== $db) {
                throw new \RuntimeException("cannot select Redis DB {$db}");
            }
            self::$sharedRedis = $redis;
            return $redis;
        } catch (\Throwable $e) {
            self::destroyContainer();
            // A configured-but-unreachable server (or a missing ext-redis) must
            // degrade to a skip, matching the "no connection source" path.
            self::$connectError = $e->getMessage();
            return null;
        }
    }

    /* ===== Connection resolution ===== */

    private static function locateRedis(): ?array
    {
        return self::fromDsn()
            ?? self::fromEnv()
            ?? self::fromContainer();
    }

    private static function fromDsn(): ?array
    {
        $dsn = getenv('REDIS_DSN');
        if (!is_string($dsn) || $dsn === '') {
            return null;
        }

        // redis://  / tcp://  forms and bare host:port
        if (str_starts_with($dsn, 'redis://') || str_starts_with($dsn, 'tcp://')) {
            $u = parse_url($dsn);
            if ($u === false) {
                return ['host' => '127.0.0.1', 'port' => 6379, 'auth' => '', 'db' => null];
            }
            $db = null;
            if (isset($u['path']) && ($seg = ltrim($u['path'], '/')) !== '') {
                $db = (int) $seg;
            }
            $auth = $u['pass'] ?? $u['user'] ?? '';
            return [
                'host' => $u['host'] ?? '127.0.0.1',
                'port' => (int) ($u['port'] ?? 6379),
                'auth' => is_string($auth) ? $auth : '',
                'db' => $db,
            ];
        }

        $parts = explode(':', $dsn);
        $host = $parts[0] === '' ? '127.0.0.1' : $parts[0];
        return ['host' => $host, 'port' => (int) ($parts[1] ?? 6379), 'auth' => '', 'db' => null];
    }

    private static function fromEnv(): ?array
    {
        $host = getenv('REDIS_HOST');
        if (!is_string($host) || $host === '') {
            return null;
        }
        $db = getenv('REDIS_DB');

        return [
            'host' => $host,
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'auth' => (string) (getenv('REDIS_AUTH') ?: ''),
            // An unset/empty REDIS_DB means "unspecified" (null), not db 0;
            // "0" is preserved so an explicit request for db 0 is honoured.
            'db' => is_string($db) && $db !== '' ? (int) $db : null,
        ];
    }

    /* ===== Throwaway container ===== */

    private const ENGINES = ['docker', 'podman'];

    private static function fromContainer(): ?array
    {
        foreach (self::ENGINES as $engine) {
            if (trim(self::shell("command -v {$engine}")) === '') {
                continue;
            }
            $config = self::tryStartContainer($engine);
            if ($config !== null) {
                return $config;
            }
        }
        // An engine binary may exist while its daemon/VM is down; fall back to none.
        return null;
    }

    private static function tryStartContainer(string $engine): ?array
    {
        $image = getenv('REDIS_IMAGE') ?: 'redis:7-alpine';
        $id = trim(self::shell("{$engine} run -d -P {$image}"));
        if ($id === '') {
            return null;
        }

        $out = trim(self::shell("{$engine} port {$id} 6379"));
        $colon = strrpos($out, ':');
        if ($colon === false) {
            self::shell("{$engine} rm -f {$id}");
            return null;
        }

        self::$containerEngine = $engine;
        self::$containerId = $id;

        $port = (int) substr($out, $colon + 1);
        if (!self::waitForPort('127.0.0.1', $port, 8.0)) {
            self::destroyContainer();
            return null;
        }

        // A freshly spawned container owns nothing, so any database is safe to flush.
        return ['host' => '127.0.0.1', 'port' => $port, 'auth' => '', 'db' => self::DEFAULT_DB];
    }

    /** Poll a TCP port until the container's Redis has started (or timeout). */
    private static function waitForPort(string $host, int $port, float $timeout): bool
    {
        $deadline = microtime(true) + $timeout;
        do {
            $fp = @fsockopen($host, $port, $errno, $errstr, 0.5);
            if ($fp !== false) {
                fclose($fp);
                return true;
            }
            usleep(100_000);
        } while (microtime(true) < $deadline);
        return false;
    }

    private static function destroyContainer(): void
    {
        if (self::$containerEngine !== null && self::$containerId !== null) {
            self::shell(self::$containerEngine . ' rm -f ' . self::$containerId);
        }
        self::$containerEngine = null;
        self::$containerId = null;
    }

    private static function shell(string $cmd): string
    {
        $pipes = [];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            return '';
        }
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        return $out;
    }
}