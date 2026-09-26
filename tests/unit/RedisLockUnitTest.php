<?php

declare(strict_types=1);

namespace MiGears\DataStructure\Tests\Unit;

use MiGears\DataStructure\Exception\DataStructureException;
use MiGears\DataStructure\RedisLock;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Pure unit tests: the phpredis \Redis client is fully mocked. No server needed.
 */
class RedisLockUnitTest extends TestCase
{
    public function testLockStoresOwnerTokenOnSuccess(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())
            ->method('set')
            ->with('job', $this->isType('string'), ['nx', 'ex' => 10])
            ->willReturn(true);

        $lock = new RedisLock($redis);
        $this->assertTrue($lock->lock('job', 10));

        // The token captured by lock() is what unlock() compares against.
        $redis->expects($this->once())->method('eval')->willReturn(1);
        $this->assertTrue($lock->unlock('job'));
    }

    public function testWithPrefixCopyCanReleaseASharedNamespaceLock(): void
    {
        $redis = $this->mockRedis();
        $redis->method('set')->willReturn(true);

        $lock = new RedisLock($redis);
        $lock->lock('job', 10);

        // Same namespace, so the copy must still own the token.
        $redis->expects($this->once())->method('eval')->willReturn(1);
        $this->assertTrue($lock->withPrefix('')->unlock('job'));
    }

    public function testWithPrefixCopyCannotReleaseAnotherNamespaceLock(): void
    {
        $redis = $this->mockRedis();
        $redis->method('set')->willReturn(true);

        $lock = new RedisLock($redis);
        $lock->lock('job', 10);

        // Different namespace: no token for "t:job", so nothing is evaluated.
        $redis->expects($this->never())->method('eval');
        $this->assertFalse($lock->withPrefix('t:')->unlock('job'));
    }

    public function testUnlockWithoutTokenDoesNotTouchRedis(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('eval');

        $this->assertFalse((new RedisLock($redis))->unlock('job'));
    }

    public function testLockRejectsNonPositiveTtl(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('set');

        $this->expectException(DataStructureException::class);
        $this->expectExceptionMessage('lock TTL must be greater than 0, 0 given');

        (new RedisLock($redis))->lock('job', 0);
    }

    public function testLockRejectsNegativeTtl(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('set');

        $this->expectException(DataStructureException::class);

        (new RedisLock($redis))->lock('job', -5);
    }

    private function mockRedis(): Redis
    {
        return $this->createMock(Redis::class);
    }
}
