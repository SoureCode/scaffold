# Code Review — Sustainable / Faulty / Smelly

Scope: production code under `src/SoureCode/{Component,Bundle}/**`. Tests skipped.
Format: every finding is **file:line — one-line claim**. No praise, no narrative.

The known failing tests (`Versionable` STI/Embeddable, `TracingLockFactory` redeclare)
already document several of these; they are flagged below with **(test exists)**.

---

## DoctrineExtensions (shared base — affects every other bundle)

### Faulty

- `Component/DoctrineExtensions/EventListener/AbstractFlushListener.php:181-204` — `touchRelatedWatchers()` walks the **entire identity map on every flush**, then calls `recomputeSingleEntityChangeSet()` per watcher candidate. Cost is O(entitiesInIdentityMap × scheduledChanges) per behavior listener; degrades sharply when a long-running worker accumulates entities.
- `Component/DoctrineExtensions/EventListener/AbstractFlushListener.php:327` — `clone $visited` inside collection recursion in `walkPath()` defeats the cycle guard for siblings in the same `Collection` and allocates a fresh `SplObjectStorage` per element. With deep object graphs this is both quadratic and incorrect.
- `Component/DoctrineExtensions/EventListener/AbstractFlushListener.php:393-401, 409-418` — `isScheduledForUpdate` / `isScheduledForDeletion` are linear scans of `getScheduledEntityUpdates()` / `getScheduledEntityDeletions()` called once per identity-map entry, turning a flush into O(n²) where `n` is the number of changed entities.

### Smelly

- `Component/DoctrineExtensions/EventListener/AbstractFlushListener.php:18` — Class is not declared `abstract` despite an `abstract protected` method list; relies on `final` subclasses to make instantiation impossible. Mark `abstract class`.
- `Component/DoctrineExtensions/EventListener/AbstractFlushListener.php:60, 108, 115` — `handle*InterfaceFallback()` runs only when metadata is empty (i.e. no `#[CreatedBy]` etc.), but the contract is documented nowhere. Two parallel paths (attribute-first, interface-fallback) with no shared contract.
- Sibling bundles repeat `'prePersist' / 'onFlush' / 'loadClassMetadata'` event strings instead of `Doctrine\ORM\Events::*` constants in `AuthorableBundle.php:65,69,76`, `TimestampableBundle.php:39-48`, `VersionableBundle.php:35-55` — a typo here is silent.

### Sustainable

- `Component/DoctrineExtensions/ChangeSet/ChangeSetMatcher::findProperty()` — Walks the class hierarchy with reflection on every binding match; no memoization. Hot path inside `onFlush`.
- Per-bundle `listener_priorities` config duplicates the same three nodes (`pre_persist`, `on_flush`, `load_class_metadata`) in each bundle's `configure()`. A host app that wants a uniform priority shift must override every bundle. A shared trait / config helper would centralize this.
- `metadataFactory` is `protected readonly` on the base, accessed directly by subclasses — replacing the factory requires a constructor override. No setter, no `with*` clone — fine for the current shape, brittle once a third metadata layer is added.

---

## Authorable

### Faulty

- `Component/Authorable/EventListener/AuthorableMappingListener.php:31, 35, 39, 43` — Reads `$metadata->createdBindings`, `updatedBindings`, `changedBindings`, `deletedBindings` as public properties even though `AuthorableMetadata` exposes `getPersistBindings()` / `getUpdateBindings()` / `getChangeBindings()`. Two-API surface; rename the property and the listener silently breaks.
- `Component/Authorable/EventListener/AuthorableMappingListener.php:32, 36, 40, 44` — Reads `$binding->property` / `$binding->nullable` as public properties; the binding classes also expose getters that are used elsewhere. Same divergence: two ways to reach the same data.

### Smelly

- `Component/Authorable/EventListener/AuthorableListener.php:29-55` — `prePersist()` mixes two unrelated responsibilities: the parent-class attribute-stamping (line 31) and impersonator stamping (33-54). The impersonator loop should be its own listener — it has different preconditions (`shouldRun()` checks the author provider, not the impersonator provider).
- `Component/Authorable/EventListener/AuthorableMappingListener.php:32, 36, 40, 44` — Nullable flag is `false` for `createdBy`, `true` for `changedBy` / `deletedBy`, and propagated from the binding for `updatedBy`. Three different conventions for the same attribute, no constant or doc explaining the asymmetry.
- `Bundle/AuthorableBundle/AuthorableBundle.php:49-79` — `loadExtension()` does three things: import, alias, and rewrite Doctrine event tags. The tag rewrite (62-78) duplicates priority logic that lives in sibling bundles — extract a `applyListenerPriorities(Definition, array $config)` helper if this stays per-bundle.

### Sustainable

- `Component/Authorable/EventListener/AuthorableListener::resolveValue()` returns `mixed`. A custom `AuthorProviderInterface` that returns the wrong shape (e.g. a Symfony `UserInterface` instead of an entity) fails at Doctrine flush time, not at construction.
- `Component/Authorable/EventListener/AuthorableMappingListener::resolveTargetFromProperty()` throws on built-in types and unknown classes; correct, but the failure mode is `LogicException` at metadata load. A custom resolver seam (an interface accepted via `user_class` or a callable) would make polymorphic authors viable.

---

## Timestampable

### Faulty

- `Component/Timestampable/EventListener/TimestampableListener.php:73` — Hardcoded changeset key `'updatedAt'` is correct for `TimestampableInterface` (which defines `setUpdatedAt`) but ignores any user-renamed `#[UpdatedAt]` property. Only matters in the interface-fallback path; still asymmetric with the attribute path which derives the property name from metadata.

### Smelly

- `Component/Timestampable/EventListener/TimestampableMappingListener.php` — Same `$metadata->*Bindings` public-property access pattern as Authorable; same recommendation.
- `Component/Timestampable/EventListener/TimestampableListener::handlePersistInterfaceFallback()` and `handleUpdateInterfaceFallback()` — Both methods near-duplicate the structure of `AuthorableListener`'s equivalents. The fallback shape (interface check → set if null → recompute changeset) is identical; lift it to the base.

### Sustainable

- `Component/Timestampable/Clock/TimestampFactory::makeFor(ReflectionProperty)` — Couples timestamp generation to the property declaration (presumably for `int` vs `DateTimeImmutable`). The seam is opaque from outside; nothing forbids constructing a `TimestampFactory` that returns the wrong type for a given property.

---

## Removable

### Faulty

- `Component/Removable/Doctrine/SoftDeleteFilter.php:48-67` — `resolveDeletedAtColumn()` walks reflection per class **and** treats absence of a registered Doctrine field mapping as "use the raw property name" (line 58). For embedded or renamed columns the SQL alias is wrong. Should derive the column strictly from `ClassMetadata::getColumnName()` and treat a miss as "not filterable".

### Smelly

- `Component/Removable/Remover.php:105` — `format('Y-m-d H:i:s')` for the `:cutoff` parameter relies on DBAL's coerce-on-bind. Pass the `DateTimeImmutable` directly with a parameter type constant (`Types::DATETIME_IMMUTABLE`) so a non-MySQL platform doesn't silently lose precision.
- `Component/Removable/Remover.php:84-99` — `purge()` rebuilds the column-name lookup that `SoftDeleteFilter::resolveDeletedAtColumn()` already does. Two implementations of the same lookup.
- `Component/Removable/Remover.php:14-24` — Constructor takes both `TimestampableMetadataFactory` **and** `AuthorableMetadataFactory` unconditionally. Removable depends on Authorable just to clear `#[DeletedBy]`; that coupling should be hidden behind an optional "marker clearer" interface, not a hard dependency.

### Sustainable

- `Bundle/RemovableBundle/RemovableBundle.php:45-51` — Some soft-delete-filter config keys become container parameters, others stay in extension args. Two storage sites for one logical group of settings. Consolidate under `sourecode.removable.soft_delete_filter.*` parameters.

---

## FeatureFlags

### Faulty

- `Component/FeatureFlags/Manager/DoctrineFeatureFlagsManager.php:115-133` — When the unique-constraint catch path fires, the existing row is found and `setEnabled($enabled)` is called regardless of what value the racing writer just committed. Last-write-wins is a policy choice; right now it's a side-effect of "retry once on conflict". Either document the policy or compare-and-set.
- `Component/FeatureFlags/Gate/PercentageRolloutGate.php:43-46` — Returns `false` when `user_id` is missing for a flag present in the rollout map. Gate contract elsewhere is "null = no opinion". Anonymous traffic should yield `null` (fall through to stored boolean) rather than a hard deny.

### Smelly

- `Component/FeatureFlags/Manager/EnvOverrideFeatureFlagsManager.php:71-91` — Env-key derivation (`prefix + uppercase + strtr('.-', '__')`) is hidden inside the decorator. Operators have to read the source to know `billing.beta-rates` maps to `FEATURE_BILLING_BETA_RATES`. Expose the mapping as a static method or document it on the class.
- `Component/FeatureFlags/Manager/DoctrineFeatureFlagsManager.php:32, 80, 103, 117, 132` — `?->dispatch(...)` everywhere. A nullable dispatcher is fine; the repeated null-safe chain is the smell. Inject `EventDispatcherInterface` with a `NullDispatcher` default and drop the `?->`.
- `Component/FeatureFlags/Bundle/FeatureFlagsBundle/FeatureFlagsBundle.php` — `loadExtension()` builds the manager stack (Env → Doctrine) by parameter substitution. A host that wants `Cached → Env → Doctrine` has to override service definitions; there's no equivalent of `decorates:` chaining at the bundle level.

### Sustainable

- `FeatureFlagsManagerInterface` exposes read **and** write on the same surface. `EnvOverrideFeatureFlagsManager` only overrides reads; `GatedFeatureFlagsManager` only overlays reads via gates. A `FeatureFlagsReaderInterface` + `FeatureFlagsWriterInterface` split would let decorators declare their actual scope.
- No registry seam for gates — adding a new `FeatureGateInterface` implementation means wiring it manually in `composite_feature_gate` arguments. A tagged-services aggregation (`#[AutoconfigureTag]`) would let the bundle pick them up.

---

## Settings

### Faulty

- `Component/Settings/Manager/CachedSettingsManager.php:35` — `return $item->get() ?? $default;` treats a **cached `null`** as a miss and replaces it with `$default`. A setting that legitimately stores `null` is never observable through the cache.
- `Component/Settings/Manager/AuditedSettingsManager.php:42-44` — Reads the previous value, then writes the new value, then dispatches. A concurrent writer between lines 42 and 43 produces a `SettingChangedEvent` whose `$previous` is already stale. Either snapshot via the manager's own write (return the prior value from `set()`) or accept the staleness in writing.

### Smelly

- `Component/Settings/Manager/EncryptingSettingsManager.php` — Marker prefix `enc::` (per reviewer; verify before fix) couples encrypt/decrypt round-trip to a magic string. Migration to a new scheme requires reading and re-writing every row.
- `Component/Settings/Manager/CachedSettingsManager::all()` returns the inner collection un-cached. Reads via `get()` cache; reads via `all()` don't. Different latency profile for what looks like one API.
- `Component/Settings/Manager/AuditedSettingsManager::set()` does two reads through the decorator stack just to assemble an event payload. For a Cached → Audited → Doctrine stack, that's two cache hits + one DB read per write.

### Sustainable

- `SettingsManagerInterface` — Decorator order matters (Cached must wrap Encrypting, not the other way around — encrypted blobs in cache would defeat encryption-at-rest). Nothing in the interface signals this ordering; nothing in the bundle wires a "blessed" stack.
- `Component/Settings/Schema/ArraySettingsSchema::validate()` — Validator is a `callable` whose throw contract is unspecified. Schema implementations either trust or wrap; both are wrong sometimes.

---

## Versionable

### Faulty

- `Component/Versionable/EventListener/VersionableSchemaListener.php:42` — `getAllMetadata()` iteration visits each subclass, but `createVersionTable()` uses the **root metadata** for `getColumnName()`, `getFieldMapping()`, and `hasField()`. Subclass-only `#[Versioned]` columns can't be added correctly because root metadata doesn't carry the subclass field mapping under that name. **(test exists: `InheritanceIntegrationTest::testVersionTableIsSharedAcrossSubclassesAndCarriesDiscriminator`)**
- `Component/Versionable/EventListener/VersionableSchemaListener.php:104-111` — `hasField($fieldName)` runs **before** the embeddable check (line 113). For an embedded property `address`, Doctrine reports `hasField('address') === false`, so the embeddable branch is reached — but if the property name happens to collide with a non-embedded field on the subclass, ordering is silently wrong. **(test exists: `EmbeddableIntegrationTest::testVersionTableFlattensEmbeddableColumns`)**
- `Component/Versionable/EventListener/VersionableListener.php` — Snapshot insert uses the property name (e.g. `authorNote`) rather than the column name (`author_note`) when building the SQL. Misaligned with the schema generated by `VersionableSchemaListener`. **(test exists: `InheritanceIntegrationTest::testSnapshotRoundTripPreservesSubclassValuesAndDiscriminator`)**
- `Component/Versionable/EventListener/VersionableListener::postFlush()` — Snapshot writes happen **after** the entity transaction commits. If the snapshot insert fails (retry exhausted, FK violation, platform error), the entity change persists but the audit row is lost silently. Wrap the snapshot in the same transactional boundary, or surface the failure.

### Smelly

- `Component/Versionable/EventListener/VersionableSchemaListener.php:80, 89, 92, 192, 198, 221-229` — Column names `entity_id`, `version`, `created_at`, `target_id`, `target_version`, `version_id` are bare string literals. Centralize on constants or a `VersionTableColumns` value object — at minimum a constant pool — so the listener and the writer can't drift.
- `Component/Versionable/EventListener/VersionableListener.php` — Single class handles schema-aware embeddable flattening (write path), collection hydration (read path), and DBAL convert-to-database / convert-to-PHP. Three responsibilities; testing them in isolation is impossible.
- `Component/Versionable/Metadata/VersionableMetadataFactory::versionTableName()` — Static method, no seam. Multi-tenant schemas or custom suffixes require subclassing the factory and patching every call site.

### Sustainable

- `Bundle/VersionableBundle/VersionableBundle.php:35-55` — Listener priorities come from **both** `config/services.php` and the bundle's `loadExtension()`. Readers have to track two definitions to know which wins. Pick one site.
- Schema listener does not expose any extension point for a third party that wants per-entity column overrides (renames, joins). Every customization requires forking the listener.

---

## Traceable

### Faulty

- `Bundle/TraceableBundle/Lock/TracingLockFactory.php:20-26` — `extends LockFactory` and re-declares `private readonly LoggerInterface $logger`. PHP 8.5 rejects redeclaring a parent property as readonly: the class can't autoload. **(test exists: `TracingLockFactoryTest`)** Either drop `extends LockFactory` (compose instead of inherit — the constructor already proxies everything) or rename / remove the property.
- `Bundle/TraceableBundle/HttpClient/TracingHttpClient.php` — Reading `$options['headers']` without `??=` first means a request without a `headers` key starts with an undefined-index notice (PHPUnit's `failOnNotice` will catch this on the next test that exercises a header-less request).

### Smelly

- `Component/Traceable/W3C/Traceparent.php:61-62` — `generate()` produces a 16-byte trace id and an 8-byte parent (span) id. That matches W3C, but the constant names / call sites don't make the byte count obvious. Two named constants (`TRACE_ID_BYTES = 16`, `SPAN_ID_BYTES = 8`) would prevent a future "make them the same" refactor from breaking interop.
- `Component/Traceable/TraceContextHolder.php` — Single mutable property, no fiber / async safety story. Fine for a sync HTTP worker; document the assumption ("not safe across concurrent fibers"). Symfony 8 fiber-based scheduling is on the roadmap.
- `Bundle/TraceableBundle/Lock/TracingLock.php` — Only `acquire()` / `release()` log. `acquireRead()`, `refresh()`, `isAcquired()`, `getRemainingLifetime()` proxy silently. Either log all observable actions or document the asymmetry.
- `Component/Traceable/Exporter/OpenTelemetryExporter.php:41, 45, 50` — Attribute key prefixes (`operation`, `sourecode.`) are inline string literals; not a constant, not configurable.

### Sustainable

- `Component/Traceable/Exporter/OpenTelemetryExporter::__construct()` — `class_exists()` guard catches missing OTEL SDK at construction, but doesn't catch SDK-version method drift (`spanBuilder()` etc.). Either pin the SDK version in `composer.json` of the bundle, or wrap the export call in a try/catch with a `LoggerInterface` warning.

---

## RecentAuthentication

### Faulty

- `Bundle/RecentAuthenticationBundle/Security/RecentAuthentication.php:27` — `mark()` calls `$this->requestStack->getSession()` unconditionally. Outside an HTTP request (CLI, worker) `RequestStack::getSession()` throws `SessionNotFoundException`. `isActive()` already guards with `getMainRequest()` + `hasSession()`; `mark()` and `clear()` don't. Inconsistent defensiveness.
- `Bundle/RecentAuthenticationBundle/Security/RecentAuthentication.php:59-62` — TTL-expiry auto-clear only fires for the bundle default (`$ttlSeconds === null`). A per-attribute tighter TTL that misses leaves the underlying session timestamp in place — which is intentional (the tighter TTL is per-resource, not a global wipe), but the asymmetry should be made explicit in the method docblock. Currently a reader sees `isActive(60)` and assumes it has the same wiping semantics as `isActive()`.

### Smelly

- `Bundle/RecentAuthenticationBundle/EventListener/AccessDeniedListener.php:43-45` — Dispatches `RecentAuthRequiredEvent` **before** `$event->setResponse()`. Subscribers can't observe (or veto) the redirect. Move dispatch after `setResponse()` or pass the response through the event.
- `Bundle/RecentAuthenticationBundle/Security/RouteRedirectStrategy.php` — `$returnPath` parameter on `redirectForReauth()` is part of the interface but discarded by the route-based implementation (it always redirects to `$loginRoute`). Either remove the parameter from this implementation's signature (via interface refactor) or honor it as a query parameter.
- `Bundle/RecentAuthenticationBundle/Security/RecentAuthentication.php:15-16` — Session keys `_recent_auth_at` and `_recent_auth_return` are `private const string`. Fine, but a host app that wants to share the recent-auth marker across two recent-authentication scopes (e.g. impersonation + sensitive ops) has no seam.

### Sustainable

- `RedirectStrategyInterface` — Single method `redirectForReauth(Request, ?string $returnPath): Response`. No seam for non-HTML responses (JSON API), no seam for stateful flow tokens (PKCE-style). Add a strategy that returns 401 + JSON if needed.
- After the recent removal of `ReauthController` and the template, the bundle has no built-in path between "access denied → redirect" and "host completes reauth → call `mark()`". Document this explicitly in the bundle README — currently the host app silently has to know.

---

## Root tooling

### Faulty

- `bin/merge:46-62` — When the same package appears in `require` of one sub-package and `require-dev` of another, the cross-bucket conflict is **not** detected (each bucket is checked independently). Add a third pass that asserts no overlap between `$require` and `$requireDev`.

### Smelly

- `bin/merge:8` — Glob `src/*/*/*/composer.json` hardcodes 3-deep nesting. A future component at a different depth is silently dropped, no error. Add a sanity check on `count($packageFiles)` vs `count(known package names)` or move to a `find`-based scan with explicit depth.
- `bin/merge:21` — `$require = ['php' => $rootJson['require']['php'] ?? '>=8.5']` — `>=8.5` baseline is duplicated in `composer.json`; if the root file's `php` constraint diverges, the script silently overrides on next merge.
- `composer.json` / `.gitattributes` — `composer.lock` was deleted in the working tree (per `git status -D composer.lock`). Either commit the new lock or `.gitignore` it; the in-between state will trip CI.

### Sustainable

- `bin/merge` mutates the root `composer.json` in place. There is no record of "this file is generated — do not edit by hand" inside the file itself. A header comment (`"_generated": "bin/merge"`) on top would catch the inevitable hand-edit.
- `phpunit.xml.dist` — `failOnDeprecation="true" failOnNotice="true"` is strict; combined with sub-package autoload through `bin/merge`, a deprecation in any dev dependency fails every PHPUnit run. Worth scoping `failOnDeprecation` to first-party namespaces only.

---

## Cross-cutting

- **Public-property access to `$metadata->*Bindings`** occurs in every mapping listener (Authorable, Timestampable, Versionable). The `*Metadata` value objects expose both `getX()` getters and public fields. Pick one and remove the other.
- **`loadExtension()` overload across bundles** — Every bundle's `loadExtension()` re-tags listeners with priorities from config. Five copies of the same pattern. Extract a `PrioritizedListenerRegistrar` helper in DoctrineExtensionsBundle.
- **Dispatcher injection inconsistency** — Some managers take `?EventDispatcherInterface` and use `?->dispatch()`. Others take a non-nullable dispatcher with a `NullDispatcher` default. Pick one across the toolkit.
- **Magic strings for Doctrine events** — `'prePersist'`, `'onFlush'`, `'loadClassMetadata'`, `'postGenerateSchema'` should reference `Doctrine\ORM\Events::*` / `Doctrine\ORM\Tools\ToolEvents::*` constants in every bundle.
- **Failing tests document real bugs** — `Versionable` STI / Embeddable schema generation (3 failures, 2 errors) and `TracingLockFactory` readonly redeclare (test passes by design — the assertion is "this class fails to load"). Per the active goal, code was not aligned; this review is the place to surface them for prioritization.
