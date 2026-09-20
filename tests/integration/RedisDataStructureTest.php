<?php

declare(strict_types=1);

namespace MiGears\DataStructure\Tests\Integration;

use MiGears\DataStructure\RedisDataStructure;
use MiGears\DataStructure\Tests\RedisTestCase;

class RedisDataStructureTest extends RedisTestCase
{
    private RedisDataStructure $ds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ds = new RedisDataStructure($this->redis);
    }

    // --- Hash ---

    public function testHashSetAndGet(): void
    {
        $this->assertTrue($this->ds->hashSet('h1', 'name', 'alice'));
        $this->assertSame('alice', $this->ds->hashGet('h1', 'name'));
    }

    public function testHashGetMissingReturnsNull(): void
    {
        $this->assertNull($this->ds->hashGet('h1', 'missing'));
    }

    public function testHashSetOverwritesField(): void
    {
        $this->ds->hashSet('h1', 'age', 20);
        $this->ds->hashSet('h1', 'age', 21);
        $this->assertSame('21', $this->ds->hashGet('h1', 'age'));
    }

    public function testHashMultiGetReturnsExistingFieldsOnly(): void
    {
        $this->ds->hashSet('h1', 'a', 1);
        $this->ds->hashSet('h1', 'b', 2);
        $this->assertSame(['a' => '1', 'b' => '2'], $this->ds->hashMultiGet('h1', ['a', 'b', 'c']));
    }

    public function testHashGetAll(): void
    {
        $this->ds->hashSet('h1', 'name', 'alice');
        $this->ds->hashSet('h1', 'age', 30);
        $this->assertSame(['name' => 'alice', 'age' => '30'], $this->ds->hashGetAll('h1'));
    }

    public function testHashDel(): void
    {
        $this->ds->hashSet('h1', 'a', 1);
        $this->ds->hashSet('h1', 'b', 2);
        $this->assertSame(1, $this->ds->hashDel('h1', 'a'));
        $this->assertNull($this->ds->hashGet('h1', 'a'));
        $this->assertSame(1, $this->ds->hashDel('h1', ['b']));
        $this->assertNull($this->ds->hashGet('h1', 'b'));
    }

    public function testHashExists(): void
    {
        $this->assertFalse($this->ds->hashExists('h1', 'a'));
        $this->ds->hashSet('h1', 'a', 1);
        $this->assertTrue($this->ds->hashExists('h1', 'a'));
    }

    public function testHashLen(): void
    {
        $this->assertSame(0, $this->ds->hashLen('h1'));
        $this->ds->hashSet('h1', 'a', 1);
        $this->ds->hashSet('h1', 'b', 2);
        $this->assertSame(2, $this->ds->hashLen('h1'));
    }

    public function testHashIncrBy(): void
    {
        $this->ds->hashSet('h1', 'n', 10);
        $this->assertSame(15, $this->ds->hashIncrBy('h1', 'n', 5));
    }

    // --- List ---

    public function testListPushAndPop(): void
    {
        $this->assertSame(1, $this->ds->listPush('q', 'first'));
        $this->assertSame(2, $this->ds->listPush('q', 'second'));
        $this->assertSame('first', $this->ds->listPop('q'));
        $this->assertSame('second', $this->ds->listPop('q'));
    }

    public function testListPopEmptyReturnsNull(): void
    {
        $this->assertNull($this->ds->listPop('q'));
    }

    public function testListPushMultiple(): void
    {
        $this->assertSame(3, $this->ds->listPush('q', 'a', 'b', 'c'));
        $this->assertSame('a', $this->ds->listPop('q'));
        $this->assertSame('b', $this->ds->listPop('q'));
        $this->assertSame('c', $this->ds->listPop('q'));
    }

    public function testListRange(): void
    {
        $this->ds->listPush('q', 'a', 'b', 'c');
        $this->assertSame(['a', 'b', 'c'], $this->ds->listRange('q'));
        $this->assertSame(['a', 'b'], $this->ds->listRange('q', 0, 1));
    }

    public function testListLen(): void
    {
        $this->assertSame(0, $this->ds->listLen('q'));
        $this->ds->listPush('q', 'a', 'b');
        $this->assertSame(2, $this->ds->listLen('q'));
    }

    public function testListIndex(): void
    {
        $this->ds->listPush('q', 'a', 'b', 'c');
        $this->assertSame('a', $this->ds->listIndex('q', 0));
        $this->assertSame('c', $this->ds->listIndex('q', -1));
        $this->assertNull($this->ds->listIndex('q', 99));
    }

    // --- Set ---

    public function testSetAddAndMembers(): void
    {
        $this->assertSame(2, $this->ds->setAdd('s', ['a', 'b']));
        $this->assertSame(0, $this->ds->setAdd('s', 'a'));
        $members = $this->ds->setMembers('s');
        sort($members);
        $this->assertSame(['a', 'b'], $members);
    }

    public function testSetRemove(): void
    {
        $this->ds->setAdd('s', ['a', 'b', 'c']);
        $this->assertSame(2, $this->ds->setRemove('s', ['a', 'b']));
        $this->assertSame(1, $this->ds->setSize('s'));
    }

    public function testSetSize(): void
    {
        $this->assertSame(0, $this->ds->setSize('s'));
        $this->ds->setAdd('s', 'a');
        $this->assertSame(1, $this->ds->setSize('s'));
    }

    public function testSetIsMember(): void
    {
        $this->assertFalse($this->ds->setIsMember('s', 'a'));
        $this->ds->setAdd('s', 'a');
        $this->assertTrue($this->ds->setIsMember('s', 'a'));
    }

    // --- ZSet ---

    public function testZAddAndZRange(): void
    {
        $this->assertSame(1, $this->ds->zAdd('z', 1.0, 'a'));
        $this->assertSame(1, $this->ds->zAdd('z', 2.0, 'b'));
        $this->assertSame(['a' => 1.0, 'b' => 2.0], $this->ds->zRange('z'));
    }

    public function testZScore(): void
    {
        $this->assertNull($this->ds->zScore('z', 'a'));
        $this->ds->zAdd('z', 1.5, 'a');
        $this->assertSame(1.5, $this->ds->zScore('z', 'a'));
    }

    public function testZRemove(): void
    {
        $this->ds->zAdd('z', 1.0, 'a');
        $this->ds->zAdd('z', 2.0, 'b');
        $this->assertSame(1, $this->ds->zRemove('z', 'a'));
        $this->assertSame(['b' => 2.0], $this->ds->zRange('z'));
    }

    public function testZSize(): void
    {
        $this->assertSame(0, $this->ds->zSize('z'));
        $this->ds->zAdd('z', 1.0, 'a');
        $this->assertSame(1, $this->ds->zSize('z'));
    }

    public function testZSelectDescLimit(): void
    {
        $this->ds->zAdd('z', 1.0, 'a');
        $this->ds->zAdd('z', 2.0, 'b');
        $this->ds->zAdd('z', 3.0, 'c');
        $this->assertSame(['c' => 3.0, 'b' => 2.0], $this->ds->zSelect('z', 0, 9999999999, 2, 'DESC'));
    }

    public function testZSelectAsc(): void
    {
        $this->ds->zAdd('z', 2.0, 'b');
        $this->ds->zAdd('z', 1.0, 'a');
        $this->assertSame(['a' => 1.0, 'b' => 2.0], $this->ds->zSelect('z', 0, 9999999999, 0, 'ASC'));
    }

    public function testZIncrBy(): void
    {
        $this->ds->zAdd('z', 1.0, 'a');
        $this->assertSame(6.0, $this->ds->zIncrBy('z', 'a', 5.0));
        $this->assertSame(6.0, $this->ds->zScore('z', 'a'));
    }

    public function testZBatchAdd(): void
    {
        $this->assertTrue($this->ds->zBatchAdd('z', [1.0, 'a', 2.0, 'b']));
        $this->assertSame(2, $this->ds->zSize('z'));
        $this->assertSame(1.0, $this->ds->zScore('z', 'a'));
        $this->assertSame(2.0, $this->ds->zScore('z', 'b'));
    }

    public function testZBatchAddEmptyReturnsFalse(): void
    {
        $this->assertFalse($this->ds->zBatchAdd('z', []));
    }

    public function testZInterStore(): void
    {
        $this->ds->zAdd('z1', 1.0, 'a');
        $this->ds->zAdd('z1', 2.0, 'b');
        $this->ds->zAdd('z2', 3.0, 'b');
        $this->ds->zAdd('z2', 4.0, 'c');
        $this->assertSame(1, $this->ds->zInterStore('zdest', ['z1', 'z2']));
        $this->assertSame(2.0, $this->ds->zScore('zdest', 'b'));
    }

    // --- Key level ---

    public function testExpireAndTtl(): void
    {
        $this->ds->hashSet('h1', 'a', 1);
        $this->assertTrue($this->ds->expire('h1', 100));
        $ttl = $this->ds->ttl('h1');
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(100, $ttl);
    }

    public function testPersist(): void
    {
        $this->ds->hashSet('h1', 'a', 1);
        $this->ds->expire('h1', 100);
        $this->assertTrue($this->ds->persist('h1'));
        $this->assertSame(-1, $this->ds->ttl('h1'));
    }

    // --- Prefix ---

    public function testWithPrefixIsolatesKeys(): void
    {
        $this->ds->hashSet('h1', 'a', 1);
        $prefixed = $this->ds->withPrefix('tenant:');
        $this->assertNull($prefixed->hashGet('h1', 'a'));
        $prefixed->hashSet('h1', 'a', 2);
        $this->assertSame('1', $this->ds->hashGet('h1', 'a'));
        $this->assertSame('2', $prefixed->hashGet('h1', 'a'));
    }
}