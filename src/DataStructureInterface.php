<?php

declare(strict_types=1);

namespace MiGears\DataStructure;

/**
 * Data structure service contract for Redis-compatible servers (Redis, Valkey, KeyDB, ...).
 *
 * Value semantics: scalars only (int|float|string), no transparent serialization —
 * each structure defines its own value types. ZSet scores are normalized to float.
 */
interface DataStructureInterface
{
    /* ===== Hash ===== */

    public function hashSet(string $key, string $field, int|float|string $value): bool;

    public function hashGet(string $key, string $field): int|float|string|null;

    /** @return array<string, int|float|string> only existing fields */
    public function hashMultiGet(string $key, array $fields): array;

    /** @return array<string, int|float|string> */
    public function hashGetAll(string $key): array;

    public function hashDel(string $key, string|array $field): int;

    public function hashExists(string $key, string $field): bool;

    public function hashLen(string $key): int;

    public function hashIncrBy(string $key, string $field, int $increment = 1): int;

    /* ===== List ===== */

    public function listPush(string $key, string ...$values): int;

    public function listPop(string $key): string|null;

    /** @return string[] */
    public function listRange(string $key, int $start = 0, int $end = -1): array;

    public function listLen(string $key): int;

    public function listIndex(string $key, int $index): string|null;

    /* ===== Set ===== */

    public function setAdd(string $key, string|array $member): int;

    public function setRemove(string $key, string|array $member): int;

    /** @return string[] */
    public function setMembers(string $key): array;

    public function setSize(string $key): int;

    public function setIsMember(string $key, string $member): bool;

    /* ===== Sorted Set (ZSet) ===== */

    public function zAdd(string $key, int|float $score, string $member): int;

    public function zRemove(string $key, string|array $member): int;

    public function zSize(string $key): int;

    public function zScore(string $key, string $member): int|float|null;

    /** @return array<string, float> members ordered by score ascending */
    public function zRange(string $key, int $start = 0, int $end = -1): array;

    /** @param int|float $min score lower bound (inclusive) */
    /** @param int|float $max score upper bound (inclusive) */
    /** @param int $limit 0 = unlimited; >0 takes first $limit entries */
    /** @param 'ASC'|'DESC' $order sort direction */
    /** @return array<string, float> */
    public function zSelect(string $key, int|float $min = 0, int|float $max = 9999999999, int $limit = 0, string $order = 'DESC'): array;

    /** @param array<int, int|float|string> $set flat [score, member, score, member, ...] */
    public function zBatchAdd(string $key, array $set): bool;

    public function zInterStore(string $destKey, array $keys, string $aggregate = 'MIN'): int;

    public function zIncrBy(string $key, string $member, int|float $increment = 1): int|float;

    /* ===== Key level ===== */

    /** Remaining TTL in seconds; -1 = no expiry, -2 = key missing. */
    public function ttl(string $key): int;

    public function expire(string $key, int $ttl): bool;

    public function persist(string $key): bool;
}
