<?php

declare(strict_types=1);

namespace MiGears\DataStructure\Tests\Integration;

use MiGears\DataStructure\RedisLock;
use MiGears\DataStructure\Tests\RedisTestCase;

class RedisLockTest extends RedisTestCase
{
    private RedisLock $lock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lock = new RedisLock($this->redis);
    }

    public function testLockAcquire(): void
    {
        $this->assertTrue($this->lock->lock('lk', 10));
        $this->assertFalse($this->lock->lock('lk', 10));
    }

    public function testUnlockReleases(): void
    {
        $this->lock->lock('lk', 10);
        $this->assertTrue($this->lock->unlock('lk'));
        $this->assertTrue($this->lock->lock('lk', 10));
    }

    public function testUnlockDoesNotReleaseUnknownToken(): void
    {
        $a = new RedisLock($this->redis);
        $b = new RedisLock($this->redis);

        $this->assertTrue($a->lock('lk', 10));
        $this->assertFalse($b->lock('lk', 10));
        // B holds no token for the key: unlock must not release A's lock
        $this->assertFalse($b->unlock('lk'));
        $this->assertFalse($a->lock('lk', 10));
        // A releases, then B can acquire
        $this->assertTrue($a->unlock('lk'));
        $this->assertTrue($b->lock('lk', 10));
    }

    public function testLockExpiresAfterTtl(): void
    {
        $this->assertTrue($this->lock->lock('lk', 1));
        usleep(1_100_000);
        $this->assertTrue($this->lock->lock('lk', 10));
    }
}