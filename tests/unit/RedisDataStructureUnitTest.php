<?php

declare(strict_types=1);

namespace MiGears\DataStructure\Tests\Unit;

use MiGears\DataStructure\Exception\DataStructureException;
use MiGears\DataStructure\RedisDataStructure;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Redis;

/**
 * Pure unit tests: the phpredis \Redis client is fully mocked. No server needed.
 */
class RedisDataStructureUnitTest extends TestCase
{
    public function testHashDelAcceptsScalarField(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('hDel')->with('h1', 'a')->willReturn(1);

        $this->assertSame(1, (new RedisDataStructure($redis))->hashDel('h1', 'a'));
    }

    public function testHashDelSpreadsArrayField(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('hDel')->with('h1', 'b', 'c')->willReturn(2);

        $this->assertSame(2, (new RedisDataStructure($redis))->hashDel('h1', ['b', 'c']));
    }

    public function testSetAddNormalizesScalarToArray(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('sAdd')->with('s', 'a')->willReturn(1);

        $this->assertSame(1, (new RedisDataStructure($redis))->setAdd('s', 'a'));
    }

    public function testSetRemoveSpreadsArrayMember(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('sRem')->with('s', 'a', 'b')->willReturn(2);

        $this->assertSame(2, (new RedisDataStructure($redis))->setRemove('s', ['a', 'b']));
    }

    public function testPrefixIsPrependedToKey(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('hGet')->with('tenant:h1', 'field')->willReturn('v');

        $ds = (new RedisDataStructure($redis))->withPrefix('tenant:');
        $this->assertSame('v', $ds->hashGet('h1', 'field'));
    }

    public function testZBatchAddPipelinesPairs(): void
    {
        $calls = [];
        $pipe = $this->createMock(Redis::class);
        $pipe->method('zAdd')->willReturnCallback(function (...$args) use (&$calls) {
            $calls[] = $args;
            return 1;
        });
        $pipe->expects($this->once())->method('exec');

        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('multi')->with(Redis::PIPELINE)->willReturn($pipe);

        $this->assertTrue((new RedisDataStructure($redis))->zBatchAdd('z', [1.0, 'a', 2.0, 'b']));
        $this->assertSame([['z', 1.0, 'a'], ['z', 2.0, 'b']], $calls);
    }

    public function testZBatchAddEmptyReturnsFalseWithoutCallingRedis(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('multi');

        $this->assertFalse((new RedisDataStructure($redis))->zBatchAdd('z', []));
    }

    public function testHashGetReturnsNullOnMissing(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('hGet')->with('h1', 'f')->willReturn(false);

        $this->assertNull((new RedisDataStructure($redis))->hashGet('h1', 'f'));
    }

    public function testRedisFailureWrapsInDataStructureException(): void
    {
        $redis = $this->mockRedis();
        $redis->method('hSet')->willThrowException(new \RuntimeException('boom'));

        $this->expectException(DataStructureException::class);
        $this->expectExceptionMessage('boom');

        (new RedisDataStructure($redis))->hashSet('h1', 'f', 1);
    }

    private function mockRedis(): Redis
    {
        return $this->createMock(Redis::class);
    }
}