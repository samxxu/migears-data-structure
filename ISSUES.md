# migears-data-structure — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (5th round, 2026-09-28).

| | |
|---|---|
| Status | **P2 open** |
| Size | src 455 lines (net) · 61 tests (36 skipped) · 3 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 1 · P3 3 · other 3 |
| Settled | 0 of 7 |
| Waiting on the owner | `G4` |
| Waiting on the reviewer | `P2-1`, `P3-1`, `P3-2`, `P3-3`, `G2`, `G3` |
| Waiting on the coordinator | _nothing_ |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **fixed** | Roughly a dozen methods return phpredis values raw (`hashLen`, `ttl`, … |
| [`P3-1`](issues/P3-1.md) | P3 | **fixed** | This module still ships the old `ci.yml` (PHP 8.1–8.4, no PHPStan … |
| [`P3-2`](issues/P3-2.md) | P3 | **fixed** | Both halves of the README end without a trailing newline (git reports … |
| [`P3-3`](issues/P3-3.md) | P3 | **fixed** | `zBatchAdd()` uses the same `false` for three different meanings — … |
| [`G2`](issues/G2.md) | - | **fixed** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |
| [`G3`](issues/G3.md) | - | **fixed** | Skip guard: `tests/RedisTestCase.php` marks the integration half … |
| [`G4`](issues/G4.md) | - | **open** | A missing logger is silent by construction: … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **7** of 7 |
| By status | `open` 1 · `fixed` 6 |
| Waiting on | owner 1 · reviewer 6 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P2** | [`P2-1`](issues/P2-1.md) | `fixed` | reviewer | Roughly a dozen methods return phpredis values raw (`hashLen`, `ttl`, … |
| **P3** | [`P3-1`](issues/P3-1.md) | `fixed` | reviewer | This module still ships the old `ci.yml` (PHP 8.1–8.4, no PHPStan … |
| **P3** | [`P3-2`](issues/P3-2.md) | `fixed` | reviewer | Both halves of the README end without a trailing newline (git reports … |
| **P3** | [`P3-3`](issues/P3-3.md) | `fixed` | reviewer | `zBatchAdd()` uses the same `false` for three different meanings — … |
| **-** | [`G2`](issues/G2.md) | `fixed` | reviewer | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |
| **-** | [`G3`](issues/G3.md) | `fixed` | reviewer | Skip guard: `tests/RedisTestCase.php` marks the integration half … |
| **-** | [`G4`](issues/G4.md) | `open` | owner | A missing logger is silent by construction: … |

## Verdict

A Redis-backed data-structure facade with consistent failure guards on numeric returns; withPrefix() still uses new static() instead of clone — the same pattern already fixed in migears-cache.

## Fixed since the last round

All prior P2/P3 items fixed: intOrFail/floatOrFail guards across numeric methods, CI migrated to tests.yml with PHPStan and PHP 8.5, README trailing newline added, zBatchAdd validates odd length and pipeline failure.

## Test gaps

No integration test for Redis connection failure scenarios (all methods' exception paths); no test for withPrefix() subclass compatibility; RedisLock has no test for lock contention or TTL expiry edge cases.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate` · `composer test:unit` · `composer test:integration`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-data-structure — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（5th round，2026-09-28）。

| | |
|---|---|
| 状态 | **P2 待修** |
| 体量 | src 455 行（净）· 61 个用例（36 跳过）· 3 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 1 · P3 3 · 其他 3 |
| 已了结 | 0 / 7 |
| 等负责人 | `G4` |
| 等评审方 | `P2-1`, `P3-1`, `P3-2`, `P3-3`, `G2`, `G3` |
| 等协调人 | _无_ |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **fixed** | 约十余个方法原样返回 phpredis … |
| [`P3-1`](issues/P3-1.md) | P3 | **fixed** | 本模块仍是旧的 ci.yml（PHP 8.1–8.4，无 PHPStan 步骤），而兄弟模块已统一到 tests.yml（8.1–8.5 且跑 … |
| [`P3-2`](issues/P3-2.md) | P3 | **fixed** | README 的英文段与中文段结尾都没有换行（git 报 "\ No newline at end of file"）。 |
| [`P3-3`](issues/P3-3.md) | P3 | **fixed** | zBatchAdd() 用同一个 false 表示三种语义——空输入、非法的奇数长度、pipeline … |
| [`G2`](issues/G2.md) | - | **fixed** | 严格开关：`phpunit.xml.dist` … |
| [`G3`](issues/G3.md) | - | **fixed** | 跳过守卫：`tests/RedisTestCase.php` 在连不上 Redis 时会把整个 integration … |
| [`G4`](issues/G4.md) | - | **open** | 缺 logger 在构造上就是静默的：`RedisDataStructure::__construct` 取 … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **7** / 7 |
| 按状态 | `open` 1 · `fixed` 6 |
| 等在谁 | 负责人 1 · 评审方 6 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P2** | [`P2-1`](issues/P2-1.md) | `fixed` | 评审方 | 约十余个方法原样返回 phpredis … |
| **P3** | [`P3-1`](issues/P3-1.md) | `fixed` | 评审方 | 本模块仍是旧的 ci.yml（PHP 8.1–8.4，无 PHPStan 步骤），而兄弟模块已统一到 tests.yml（8.1–8.5 且跑 … |
| **P3** | [`P3-2`](issues/P3-2.md) | `fixed` | 评审方 | README 的英文段与中文段结尾都没有换行（git 报 "\ No newline at end of file"）。 |
| **P3** | [`P3-3`](issues/P3-3.md) | `fixed` | 评审方 | zBatchAdd() 用同一个 false 表示三种语义——空输入、非法的奇数长度、pipeline … |
| **-** | [`G2`](issues/G2.md) | `fixed` | 评审方 | 严格开关：`phpunit.xml.dist` … |
| **-** | [`G3`](issues/G3.md) | `fixed` | 评审方 | 跳过守卫：`tests/RedisTestCase.php` 在连不上 Redis 时会把整个 integration … |
| **-** | [`G4`](issues/G4.md) | `open` | 负责人 | 缺 logger 在构造上就是静默的：`RedisDataStructure::__construct` 取 … |

## 结论

一个基于 Redis 的数据结构门面，数值返回上有一致的失败守卫；withPrefix() 仍使用 new static() 而非 clone——migears-cache 已修复了同样的问题。

## 本轮已修复确认

All prior P2/P3 items fixed: intOrFail/floatOrFail guards across numeric methods, CI migrated to tests.yml with PHPStan and PHP 8.5, README trailing newline added, zBatchAdd validates odd length and pipeline failure.

## 测试盲区

无 Redis 连接失败场景集成测试（所有方法的异常路径）；无 withPrefix() 子类兼容性测试；RedisLock 无锁竞争或 TTL 过期边界测试。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate` · `composer test:unit` · `composer test:integration`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
