<?php

declare(strict_types=1);

namespace MiGears\DataStructure\Tests\Unit;

use MiGears\DataStructure\Exception\DataStructureException;
use MiGears\DataStructure\RedisLock;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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

        $lock = new RedisLock($redis, new NullLogger());
        $this->assertTrue($lock->lock('job', 10));

        // The token captured by lock() is what unlock() compares against.
        $redis->expects($this->once())->method('eval')->willReturn(1);
        $this->assertTrue($lock->unlock('job'));
    }

    public function testWithPrefixCopyCanReleaseASharedNamespaceLock(): void
    {
        $redis = $this->mockRedis();
        $redis->method('set')->willReturn(true);

        $lock = new RedisLock($redis, new NullLogger());
        $lock->lock('job', 10);

        // Same namespace, so the copy must still own the token.
        $redis->expects($this->once())->method('eval')->willReturn(1);
        $this->assertTrue($lock->withPrefix('')->unlock('job'));
    }

    public function testWithPrefixCopyCannotReleaseAnotherNamespaceLock(): void
    {
        $redis = $this->mockRedis();
        $redis->method('set')->willReturn(true);

        $lock = new RedisLock($redis, new NullLogger());
        $lock->lock('job', 10);

        // Different namespace: no token for "t:job", so nothing is evaluated.
        $redis->expects($this->never())->method('eval');
        $this->assertFalse($lock->withPrefix('t:')->unlock('job'));
    }

    public function testUnlockWithoutTokenDoesNotTouchRedis(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('eval');

        $this->assertFalse((new RedisLock($redis, new NullLogger()))->unlock('job'));
    }

    public function testLockRejectsNonPositiveTtl(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('set');

        $this->expectException(DataStructureException::class);
        $this->expectExceptionMessage('lock TTL must be greater than 0, 0 given');

        (new RedisLock($redis, new NullLogger()))->lock('job', 0);
    }

    public function testLockRejectsNegativeTtl(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('set');

        $this->expectException(DataStructureException::class);

        (new RedisLock($redis, new NullLogger()))->lock('job', -5);
    }

    public function testConstructorRequiresALogger(): void
    {
        $redis = $this->mockRedis();

        // No logger means no silence: the call site must fail at assembly time
        // instead of quietly substituting a NullLogger.
        $this->expectException(\ArgumentCountError::class);

        new RedisLock($redis);
    }

    public function testWithPrefixWorksOnASubclassWithAnIncompatibleConstructor(): void
    {
        // Same contract as RedisDataStructure: prefixing must not re-run the
        // constructor, so a subclass with an incompatible constructor is still
        // prefixable.
        $lock = new class extends RedisLock {
            public function __construct()
            {
            }
        };

        $this->assertInstanceOf(RedisLock::class, $lock->withPrefix('t:'));
    }

    private function mockRedis(): Redis
    {
        return $this->createMock(Redis::class);
    }
}
