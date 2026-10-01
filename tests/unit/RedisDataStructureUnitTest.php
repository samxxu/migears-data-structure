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

        $this->assertSame(1, (new RedisDataStructure($redis, new NullLogger()))->hashDel('h1', 'a'));
    }

    public function testHashDelSpreadsArrayField(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('hDel')->with('h1', 'b', 'c')->willReturn(2);

        $this->assertSame(2, (new RedisDataStructure($redis, new NullLogger()))->hashDel('h1', ['b', 'c']));
    }

    public function testSetAddNormalizesScalarToArray(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('sAdd')->with('s', 'a')->willReturn(1);

        $this->assertSame(1, (new RedisDataStructure($redis, new NullLogger()))->setAdd('s', 'a'));
    }

    public function testSetRemoveSpreadsArrayMember(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('sRem')->with('s', 'a', 'b')->willReturn(2);

        $this->assertSame(2, (new RedisDataStructure($redis, new NullLogger()))->setRemove('s', ['a', 'b']));
    }

    public function testPrefixIsPrependedToKey(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('hGet')->with('tenant:h1', 'field')->willReturn('v');

        $ds = (new RedisDataStructure($redis, new NullLogger()))->withPrefix('tenant:');
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
        $pipe->expects($this->once())->method('exec')->willReturn([1, 1]);

        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('multi')->with(Redis::PIPELINE)->willReturn($pipe);

        $this->assertTrue((new RedisDataStructure($redis, new NullLogger()))->zBatchAdd('z', [1.0, 'a', 2.0, 'b']));
        $this->assertSame([['z', 1.0, 'a'], ['z', 2.0, 'b']], $calls);
    }

    public function testZBatchAddEmptyReturnsFalseWithoutCallingRedis(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('multi');

        $this->assertFalse((new RedisDataStructure($redis, new NullLogger()))->zBatchAdd('z', []));
    }

    public function testZBatchAddRejectsOddLengthInsteadOfDroppingTheTail(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('multi');

        $this->expectException(DataStructureException::class);
        $this->expectExceptionMessage('got 3 entries');

        (new RedisDataStructure($redis, new NullLogger()))->zBatchAdd('z', [1.0, 'a', 2.0]);
    }

    public function testZBatchAddReportsAPartialFailureAsAnException(): void
    {
        $pipe = $this->createMock(Redis::class);
        $pipe->method('exec')->willReturn([1, false]);

        $redis = $this->mockRedis();
        $redis->method('multi')->willReturn($pipe);

        $this->expectException(DataStructureException::class);
        $this->expectExceptionMessage('a command in the pipeline did not apply');

        (new RedisDataStructure($redis, new NullLogger()))->zBatchAdd('z', [1.0, 'a', 2.0, 'b']);
    }

    public function testCountReturningMethodsReportFailureInsteadOfLeakingEngineWording(): void
    {
        $redis = $this->mockRedis();
        foreach (['hDel', 'hLen', 'hIncrBy', 'rPush', 'lLen', 'sAdd', 'sRem', 'sCard', 'zAdd', 'zRem', 'zCard', 'zInterStore', 'ttl'] as $method) {
            $redis->method($method)->willReturn(false);
        }
        $ds = new RedisDataStructure($redis, new NullLogger());

        $calls = [
            'hashDel' => static fn () => $ds->hashDel('h', 'f'),
            'hashLen' => static fn () => $ds->hashLen('h'),
            'hashIncrBy' => static fn () => $ds->hashIncrBy('h', 'f'),
            'listPush' => static fn () => $ds->listPush('l', 'v'),
            'listLen' => static fn () => $ds->listLen('l'),
            'setAdd' => static fn () => $ds->setAdd('s', 'm'),
            'setRemove' => static fn () => $ds->setRemove('s', 'm'),
            'setSize' => static fn () => $ds->setSize('s'),
            'zAdd' => static fn () => $ds->zAdd('z', 1.0, 'm'),
            'zRemove' => static fn () => $ds->zRemove('z', 'm'),
            'zSize' => static fn () => $ds->zSize('z'),
            'zInterStore' => static fn () => $ds->zInterStore('dest', ['z']),
            'ttl' => static fn () => $ds->ttl('k'),
        ];

        foreach ($calls as $name => $call) {
            try {
                $call();
                $this->fail("{$name}() returned instead of reporting the failure");
            } catch (DataStructureException $e) {
                // Our own message, never the engine's TypeError wording.
                $this->assertStringContainsString("{$name} failed", $e->getMessage());
                $this->assertStringNotContainsString('Return value must be of type', $e->getMessage());
            }
        }
    }

    public function testZIncrByDoesNotTurnAFailureIntoZero(): void
    {
        $redis = $this->mockRedis();
        $redis->method('zIncrBy')->willReturn(false);

        $this->expectException(DataStructureException::class);
        $this->expectExceptionMessage('zIncrBy failed');

        (new RedisDataStructure($redis, new NullLogger()))->zIncrBy('z', 'm', 5);
    }

    public function testBooleanMethodsPassTheClientFalseThrough(): void
    {
        $redis = $this->mockRedis();
        $redis->method('hExists')->willReturn(false);
        $redis->method('sIsMember')->willReturn(false);
        $redis->method('expire')->willReturn(false);
        $redis->method('persist')->willReturn(false);

        $ds = new RedisDataStructure($redis, new NullLogger());

        // Here false answers "no", not "the command failed", so it is not an error.
        $this->assertFalse($ds->hashExists('h', 'f'));
        $this->assertFalse($ds->setIsMember('s', 'm'));
        $this->assertFalse($ds->expire('k', 10));
        $this->assertFalse($ds->persist('k'));
    }

    public function testHashGetReturnsNullOnMissing(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('hGet')->with('h1', 'f')->willReturn(false);

        $this->assertNull((new RedisDataStructure($redis, new NullLogger()))->hashGet('h1', 'f'));
    }

    public function testRedisFailureWrapsInDataStructureException(): void
    {
        $redis = $this->mockRedis();
        $redis->method('hSet')->willThrowException(new \RuntimeException('boom'));

        $this->expectException(DataStructureException::class);
        $this->expectExceptionMessage('boom');

        (new RedisDataStructure($redis, new NullLogger()))->hashSet('h1', 'f', 1);
    }

    public function testConstructorRequiresALogger(): void
    {
        $redis = $this->mockRedis();

        // No logger means no silence: the call site must fail at assembly time
        // instead of quietly substituting a NullLogger.
        $this->expectException(\ArgumentCountError::class);

        new RedisDataStructure($redis);
    }

    public function testZSelectLowercaseAscIsHonoured(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('zRangeByScore')
            ->with('z', '0', '100', ['withscores' => true])
            ->willReturn(['a' => 1.0]);

        $this->assertSame(['a' => 1.0], (new RedisDataStructure($redis, new NullLogger()))->zSelect('z', 0, 100, 0, 'asc'));
    }

    public function testZSelectDefaultOrderIsDescendingAndBoundsAreSwapped(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('zRevRangeByScore')
            ->with('z', '100', '0', ['withscores' => true])
            ->willReturn(['b' => 90.0, 'a' => 80.0]);

        $this->assertSame(['b' => 90.0, 'a' => 80.0], (new RedisDataStructure($redis, new NullLogger()))->zSelect('z', 0, 100));
    }

    public function testZSelectCastsBoundsToStringAndPassesLimit(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->once())->method('zRangeByScore')
            ->with('z', '-5.5', '7.5', ['withscores' => true, 'limit' => [0, 3]])
            ->willReturn([]);

        $this->assertSame([], (new RedisDataStructure($redis, new NullLogger()))->zSelect('z', -5.5, 7.5, 3, 'ASC'));
    }

    public function testZSelectNormalisesScoresToFloat(): void
    {
        $redis = $this->mockRedis();
        $redis->method('zRevRangeByScore')->willReturn(['a' => '1', 'b' => 2]);

        $this->assertSame(['a' => 1.0, 'b' => 2.0], (new RedisDataStructure($redis, new NullLogger()))->zSelect('z'));
    }

    public function testZSelectReturnsEmptyWhenRedisReturnsFalse(): void
    {
        $redis = $this->mockRedis();
        $redis->method('zRevRangeByScore')->willReturn(false);

        $this->assertSame([], (new RedisDataStructure($redis, new NullLogger()))->zSelect('z'));
    }

    public function testZSelectRejectsAMisspelledOrderInsteadOfAnsweringInDescending(): void
    {
        $redis = $this->mockRedis();
        $redis->expects($this->never())->method('zRangeByScore');
        $redis->expects($this->never())->method('zRevRangeByScore');

        $this->expectException(DataStructureException::class);
        $this->expectExceptionMessage("zSelect expects an order of 'ASC' or 'DESC', got 'ASEC'");

        (new RedisDataStructure($redis, new NullLogger()))->zSelect('z', 0, 100, 0, 'ASEC');
    }

    public function testWithPrefixWorksOnASubclassWithAnIncompatibleConstructor(): void
    {
        // withPrefix() must not re-run the constructor: a subclass whose
        // constructor is incompatible with `new static($redis, $logger)` still
        // has to be prefixable. Without clone this raises "Typed property
        // MiGears\DataStructure\RedisDataStructure::$redis must not be accessed
        // before initialization".
        $ds = new class extends RedisDataStructure {
            public function __construct()
            {
            }
        };

        $this->assertInstanceOf(RedisDataStructure::class, $ds->withPrefix('tenant:'));
    }

    private function mockRedis(): Redis
    {
        return $this->createMock(Redis::class);
    }
}
