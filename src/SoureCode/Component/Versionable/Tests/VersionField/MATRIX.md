# Version-bump coverage matrix

## Legend

**Rule under test.** In a single `flush()`, every change to a `#[Versioned]` entity increments that entity's `version` column by exactly one ("one bump") and writes exactly one snapshot row. A change to a *relationship* is one logical edit that touches **both** ends, so each versioned endpoint bumps once.

**Notation**

| Symbol | Meaning |
|--------|---------|
| `+1` | that entity's `version` increments by exactly one for the flush (one bump: `N` → `N+1`) |
| `×1 each` | every entity listed bumps once for the flush — never twice, never zero |
| `—` | nothing happens here (no bump / not applicable) |

**Status**

| Icon | Meaning |
|------|---------|
| ✅ | implemented and proven by a passing test |
| ❌ | a real gap — does **not** happen today; the test fails |
| ⬜ | case not written / not run yet |
| ❓ | behaviour is an open design decision |

**Terms**

| Term | Meaning |
|------|---------|
| embedded (`Embedded`) | a value object (e.g. `Geo`) flattened into the owner's table as columns; not a relationship |
| sub-field | one column of an embedded value object (e.g. `geo.latitude`). "Replace sub-field" = change one of them, which Doctrine records as a change to the owner |
| n:1 (`ManyToOne`) | many rows point at one; the "many" side **owns** the FK column on its own row |
| 1:n (`OneToMany`) | inverse of n:1 — the "one" side; a collection, owns no column |
| 1:1 (`OneToOne`) | one of the two sides **owns** the FK (`owning`); the other is `inverse` (`mappedBy`) and owns no column |
| n:m (`ManyToMany`) | join table — **neither** row owns a column for the relationship |
| owning side | the entity whose own row/FK carries the relationship; Doctrine updates its row |
| inverse side | the other end (`mappedBy`); its own row does **not** change on the edit |
| endpoint / element | the two ends of a relationship; for n:m the added/removed item is the "element" |

**Test hub** — one entity wired to every kind:
`Subject` — `title` (scalar), `status` (enum), `geo` (embedded), `owner` (n:1 owning → `Owner`), `badge` (1:1 owning → `Badge`), `tags` (n:m → `Tag`).

**Combination atoms** (used in the combination tables):
**a** scalar · **b** enum · **c** embedded · **d** set `owner` (n:1) · **e** set `badge` (1:1) · **f** add/remove `tags` (n:m). `Subject` is always one of the bumped entities; the others appear only when their relation atom (d/e/f) is in the combo.

## Per-case assertions — every row carries this tail

"No-op" is not one row; it is an invariant checked on **every** case below. Each test asserts more than "did it bump":

1. **Bump** — the intended entity's `version` went `+1` and exactly one snapshot was written.
2. **Isolation** — no *uninvolved* entity bumped (a scalar change on `Subject` must leave `Owner` / `Badge` / `Tag` untouched). Catches over-bumping.
3. **Stability** — a second `flush()` with nothing changed bumps **nobody** and writes no snapshot. The version write must leave every touched entity clean in the UnitOfWork; if it does not, *every* case silently leaks a spurious bump on the next flush.

So a row's full test is: *make the change → assert the bump → assert isolation → empty `flush()` → assert stability.* Rows #34–#35 below are just the named, standalone statement of invariants 2–3.

## Simple cases — single change, both sides

| # | Change | Operation | Subject (self / owning) | Other endpoint | Status |
|---|--------|-----------|--------------------------|----------------|--------|
| 1 | Scalar (`title`) | set | ✅ +1 | — | ✅ |
| 2 | Enum (`status`) | set | ✅ +1 | — | ✅ |
| 3 | Embedded (`geo`) | replace sub-field | ✅ +1 | — | ✅ |
| 4 | n:1 (`owner`) | null → entity | ✅ +1 | `Owner` +1 (1:n inverse) | ✅ |
| 5 | n:1 (`owner`) | entity → other | ✅ +1 | both `Owner`s +1 | ✅ |
| 6 | n:1 (`owner`) | entity → null | ✅ +1 | old `Owner` +1 | ✅ |
| 7 | 1:1 owning (`badge`) | null → entity | ✅ +1 | `Badge` +1 (1:1 inverse) | ✅ |
| 8 | 1:1 owning (`badge`) | entity → other | ✅ +1 | both `Badge`s +1 | ⬜ |
| 9 | 1:1 owning (`badge`) | entity → null | ✅ +1 | old `Badge` +1 | ⬜ |
| 10 | n:m (`tags`) | add | ✅ +1 | added `Tag` +1 (element) | ✅ |
| 11 | n:m (`tags`) | remove | ✅ +1 | removed `Tag` +1 | ✅ |
| 12 | n:m (`tags`) | clear | ✅ +1 | each removed `Tag` +1 | ⬜ |

## Combination cases — one flush, multiple changes → each affected entity bumps **exactly once**

### Pairwise (all 15)

| # | Combo | Entities bumped (×1 each) | Status |
|---|-------|----------------------------|--------|
| 13 | a+b | Subject | ⬜ |
| 14 | a+c | Subject | ⬜ |
| 15 | a+d | Subject, Owner | ⬜ |
| 16 | a+e | Subject, Badge | ⬜ |
| 17 | a+f | Subject, Tag | ✅ |
| 18 | b+c | Subject | ⬜ |
| 19 | b+d | Subject, Owner | ⬜ |
| 20 | b+e | Subject, Badge | ⬜ |
| 21 | b+f | Subject, Tag | ⬜ |
| 22 | c+d | Subject, Owner | ⬜ |
| 23 | c+e | Subject, Badge | ⬜ |
| 24 | c+f | Subject, Tag | ⬜ |
| 25 | d+e | Subject, Owner, Badge | ⬜ |
| 26 | d+f | Subject, Owner, Tag | ⬜ |
| 27 | e+f | Subject, Badge, Tag | ⬜ |

### Higher-order

| # | Combo | Entities bumped (×1 each) | Status |
|---|-------|----------------------------|--------|
| 28 | a+b+c (all own-row) | Subject | ⬜ |
| 29 | d+e+f (all relations) | Subject, Owner, Badge, Tag | ✅ |
| 30 | a+d+e+f (scalar + all relations) | Subject, Owner, Badge, Tag | ✅ |
| 31 | two tags added in one flush | Subject (once), both Tags | ⬜ |
| 32 | tag added + tag removed in one flush | Subject (once), both Tags | ⬜ |

## Boundary

| # | Case | Expected | Status |
|---|------|----------|--------|
| 33 | Insert | seeds `version` (0), no bump, no snapshot | ✅ |
| 34 | No-op flush — **stability** | nothing changed → no bump, no snapshot, anywhere | ✅ |
| 35 | Re-flush after a bump — **stability** | version write leaves the entity clean → no extra bump | ✅ |
| 35b | **Isolation** | an unrelated change on one entity never bumps the other | ✅ |
| 36 | Concurrent change, `lock` enabled (`#[ORM\Version]`) | `OptimisticLockException` | ✅ |
| 36b | `lock` field present | excluded from snapshot content; our counter still bumps | ✅ |
| 37 | Concurrent change, no `lock` | both append, distinct versions | ⬜ |

## Structural variants

| # | Case | Expected | Status |
|---|------|----------|--------|
| 38 | Self-referential n:1 (`Node.parent`) | self +1; parent +1 if bidirectional | ⬜ |
| 39 | STI subclass field change | +1 on shared version field | ⬜ |
| 40 | `OneToMany` with `orphanRemoval` | owner +1; removed child handled | ⬜ |
| 41 | Enum read-back from snapshot | enum restored (write fixed; read pending) | ⬜ |

## Status

Both sides now bump for every cardinality. `SnapshotTargetResolver` resolves inverse owners from the change set (relationship *changes*, not just insert/delete), handles single-valued inverse (1:1), and bumps n:m elements — all gated on bidirectionality, so unidirectional relations stay one-sided. The version increment is uniform (no native/hijack split): one bump per affected entity per flush, written by `VersionIncrementer` via Doctrine's persister (no hand-written SQL).

A host's optional Doctrine `#[ORM\Version]` lock is supported (`Tests/Lock/LockTest`): the factory excludes it from snapshot content, our counter bumps independently, and Doctrine still raises `OptimisticLockException` on a concurrent change (`#36`, `#36b`).

Still `⬜` (documented, not yet asserted): the remaining single-op variants (entity→other / →null, remove / clear), most pairwise combos, the no-`lock` race (`#37`), and the structural variants (`#38`–`#41`).
