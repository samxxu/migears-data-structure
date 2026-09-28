# migears-data-structure — Known Issues / 已知问题

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> From the miGears Full-Module Code Review Report (4th round, 2026-09-27).

| | |
|---|---|
| Status / 状态 | **P0 cleared / P0 已清零** |
| Size / 体量 | src 618 lines (436 net) · 58 tests (36 integration skipped) · 4 src files |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## At a glance / 状态一览

| | |
|---|---|
| Items / 条目 | P0 0 · P1 0 · P2 1 · P3 3 · other 2 |
| Answered / 已回复 | 6 of 6 |
| Waiting / 等待回复 | _nothing / 无_ |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **fixed** | Roughly a dozen methods return phpredis values raw (`hashLen`, `ttl`, … |
| [`P3-1`](issues/P3-1.md) | P3 | **fixed** | This module still ships the old `ci.yml` (PHP 8.1–8.4, no PHPStan … |
| [`P3-2`](issues/P3-2.md) | P3 | **fixed** | Both halves of the README end without a trailing newline (git reports … |
| [`P3-3`](issues/P3-3.md) | P3 | **fixed** | `zBatchAdd()` uses the same `false` for three different meanings — … |
| [`G2`](issues/G2.md) | - | **fixed** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |
| [`G3`](issues/G3.md) | - | **fixed** | Skip guard: `tests/RedisTestCase.php` marks the integration half … |

## Verdict / 结论

The dangerous fixture is fixed and the once-untested Redis paths now have unit coverage. The remaining substantive issue is a failure-semantics inconsistency: many methods return phpredis values raw, so a failure surfaces as a PHP TypeError wrapped in a business exception instead of the module's own normalisation.

危险的测试夹具已修复，过去无覆盖的 Redis 路径有了单元测试。剩下的实质问题是失败语义不一致：多个方法原样返回 phpredis 的值，失败时表现为「被包成业务异常的 PHP TypeError」，而不是模块自己的归一化。

## Fixed since the last round / 本轮已修复确认

上一轮 6 项中 5 项实体修复：夹具不再把 db=0 改成 15（并有独立复核）、zSelect 大小写、zBatchAdd 的奇数长度校验与 atomic 注释、RedisLock 的令牌复制与 ttl 校验、单元套件从 9 例扩到 22 例；第 6 条（默认分数窗口）改为文档化保留。 

## Test gaps / 测试盲区

36 integration tests still skip locally, so real-server behaviour of zRange/expire/persist rests on CI alone; the raw-return false path has no test (this round it took a stub to expose); RedisLock expiry uses a real 1.1s sleep and stays integration-only.

36 个集成用例在本机仍全部跳过，zRange/expire/persist 的真实行为只靠 CI；裸返回 false 的路径无任何用例（本轮靠桩才暴露）；RedisLock 到期释放用真实 1.1 秒睡眠，仅集成套件覆盖。

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate` · `composer test:unit` · `composer test:integration`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
