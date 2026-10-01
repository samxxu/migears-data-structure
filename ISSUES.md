# migears-data-structure — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (6th round, 2026-10-01).

| | |
|---|---|
| Status | **Best state** |
| Size | src 452 lines (net) · 65 tests (36 skipped) · 4 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 0 · P3 2 · other 0 |
| Settled | 8 of 10 |
| Waiting on the owner | _nothing_ |
| Waiting on the coordinator | `P3-4` |
| Waiting on the reviewer | `P3-5` |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | Roughly a dozen methods return phpredis values raw (`hashLen`, `ttl`, … |
| [`P2-2`](issues/P2-2.md) | P2 | **verified** | `withPrefix()` used `new static(...)`, which breaks on a subclass whose … |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | This module still ships the old `ci.yml` (PHP 8.1–8.4, no PHPStan … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | Both halves of the README end without a trailing newline (git reports … |
| [`P3-3`](issues/P3-3.md) | P3 | **verified** | `zBatchAdd()` uses the same `false` for three different meanings — … |
| [`P3-4`](issues/P3-4.md) | P3 | **question** | zSelect() default bounds min=0, max=9999999999 silently exclude members … |
| [`P3-5`](issues/P3-5.md) | P3 | **fixed** | zSelect() treats every $order other than ‘ASC’ as descending, while the … |
| [`G2`](issues/G2.md) | - | **verified** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |
| [`G3`](issues/G3.md) | - | **verified** | Skip guard: `tests/RedisTestCase.php` marks the integration half … |
| [`G4`](issues/G4.md) | - | **verified** | A missing logger is silent by construction: … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **2** of 10 |
| By status | `question` 1 · `fixed` 1 |
| Waiting on | coordinator 1 · reviewer 1 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P3** | [`P3-4`](issues/P3-4.md) | `question` | coordinator | zSelect() default bounds min=0, max=9999999999 silently exclude members … |
| **P3** | [`P3-5`](issues/P3-5.md) | `fixed` | reviewer | zSelect() treats every $order other than ‘ASC’ as descending, while the … |

## Verdict

The logger standard is landed in both classes, and the failure guards introduced earlier still hold; one silent fallback remains in the sort direction.

## Fixed since the last round

G4 verified by mutation: both constructors now require a LoggerInterface and the NullLogger fallback is gone. Reverting either to an optional parameter turns the module’s own test red.

## Test gaps

36 tests skip without a Redis service, which is the whole connection-side half; the unit tests mock \Redis, so the compare-and-delete Lua script only really runs in the integration suite.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate` · `composer test:unit` · `composer test:integration`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-data-structure — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（6th round，2026-10-01）。

| | |
|---|---|
| 状态 | **状态最好** |
| 体量 | src 452 行（净）· 65 个用例（36 跳过）· 4 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 0 · P3 2 · 其他 0 |
| 已了结 | 8 / 10 |
| 等模块主 | _无_ |
| 等协调人 | `P3-4` |
| 等评审方 | `P3-5` |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | 约十余个方法原样返回 phpredis … |
| [`P2-2`](issues/P2-2.md) | P2 | **verified** | `withPrefix()` 用 `new static(...)`，在构造器不兼容的子类上会失败。工作树中已改为 `clone … |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | 本模块仍是旧的 ci.yml（PHP 8.1–8.4，无 PHPStan 步骤），而兄弟模块已统一到 tests.yml（8.1–8.5 且跑 … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | README 的英文段与中文段结尾都没有换行（git 报 "\ No newline at end of file"）。 |
| [`P3-3`](issues/P3-3.md) | P3 | **verified** | zBatchAdd() 用同一个 false 表示三种语义——空输入、非法的奇数长度、pipeline … |
| [`P3-4`](issues/P3-4.md) | P3 | **question** | zSelect() 默认边界 min=0, max=9999999999 会静默排除负分成员，用户默认期望全范围时会感到意外。 |
| [`P3-5`](issues/P3-5.md) | P3 | **fixed** | zSelect() 把除 ‘ASC’ 以外的任何 $order 一律当作降序，而接口只声明 ‘ASC’|DESC’。‘ASEC’ … |
| [`G2`](issues/G2.md) | - | **verified** | 严格开关：`phpunit.xml.dist` … |
| [`G3`](issues/G3.md) | - | **verified** | 跳过守卫：`tests/RedisTestCase.php` 在连不上 Redis 时会把整个 integration … |
| [`G4`](issues/G4.md) | - | **verified** | 缺 logger 在构造上就是静默的：`RedisDataStructure::__construct` 取 … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **2** / 10 |
| 按状态 | `question` 1 · `fixed` 1 |
| 等在谁 | 协调人 1 · 评审方 1 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P3** | [`P3-4`](issues/P3-4.md) | `question` | 协调人 | zSelect() 默认边界 min=0, max=9999999999 会静默排除负分成员，用户默认期望全范围时会感到意外。 |
| **P3** | [`P3-5`](issues/P3-5.md) | `fixed` | 评审方 | zSelect() 把除 ‘ASC’ 以外的任何 $order 一律当作降序，而接口只声明 ‘ASC’\|DESC’。‘ASEC’ … |

## 结论

logger 标准已在两个类落地，此前引入的失败守卫依然承重；仅排序方向还剩一处静默回落。

## 本轮已修复确认

G4 verified by mutation: both constructors now require a LoggerInterface and the NullLogger fallback is gone. Reverting either to an optional parameter turns the module’s own test red.

## 测试盲区

无 Redis 服务时 36 个用例跳过，即整个连接侧半边；单测 mock 了 \Redis，比较并删除的 Lua 脚本只在集成套件里才真正执行。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate` · `composer test:unit` · `composer test:integration`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
