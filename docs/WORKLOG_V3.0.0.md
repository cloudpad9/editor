# CloudPad9 — Refactoring Worklog
**Date:** 2026-03-28  
**Phases executed:** R1 → R2 → R3 → R4 → R5 → R6  
**Base:** `downloaded_files_2026-03-27.zip` | **Plan:** `REFACTORING_PLAN_V3.md`

---

## ✅ DONE

### Phase R1 — Remove duplicates + migrate plugins
| Task | Result |
|------|--------|
| Removed global `plugin_fs` class from `Builder.php` | ✅ ~60 lines deleted |
| Removed global `plugin_tab` class from `Builder.php` | ✅ ~8 lines deleted |
| Removed global `ProfilingHelper` class from `Builder.php` | ✅ ~60 lines deleted |
| `plugins/fs/local/local.php` → extends `BaseFilesystemPlugin` | ✅ Type hints added |
| `plugins/fs/git/git.php` → extends `BaseFilesystemPlugin` | ✅ Dead code `if (true \|\| empty($repodir))` removed |
| `plugins/fs/sftp/sftp.php` → extends `BaseFilesystemPlugin` | ✅ `global $sftps` → `static $connections`; `die()` → `throw FileSystemException` |
| `plugins/fs/svn/svn.php` → extends `BaseFilesystemPlugin` | ✅ Type hints added |
| `plugins/tabs/editor/index.php` → extends `BaseTabPlugin` | ✅ |
| `plugins/tabs/snr/index.php` → extends `BaseTabPlugin` | ✅ |
| `plugins/tabs/diff/index.php` → extends `BaseTabPlugin` | ✅ |
| Removed `is_plugin_command()` + `execute_plugin_command()` from Builder | ✅ Router owns logic |
| Removed `get_sub_dirs()` from Builder | ✅ No callers |
| Removed `getUsers()` from Builder; migrated sole caller to `getAuth()->getUsers()` | ✅ |

**R1 result:** Builder 881 → 681 lines (−23%)

---

### Phase R2 — Decompose Builder God Object
| Task | Result |
|------|--------|
| Added `$_container` property + stored in constructor | ✅ |
| Added `get(string $class): object` generic getter | ✅ |
| Added 5 typed getters: `getAuth()`, `getRepoManager()`, `getFileOps()`, `getOutput()`, `getEditorService()` | ✅ |
| Removed ~60 thin wrapper methods from Builder | ✅ |
| Migrated all 52 command plugin files to direct service calls | ✅ |
| Removed unused private properties: `$_colorManager`, `$_revisionManager`, `$_syncService`, `$_fileSearch`, `$_snr` | ✅ |
| Inlined `Builder::diff()` business logic into `plugins/commands/diff.php` | ✅ |
| Fixed deprecated `mb_convert_encoding` → `htmlspecialchars` in diff command | ✅ |
| Removed all verbose Repository Manager wrapper block | ✅ |
| Migrated `snr_search` command to call `SNRService->search()` directly | ✅ |
| Migrated `$_SESSION` writes in snr_search → `NativeSession::getInstance()->set()` | ✅ |

**R2 result:** Builder 681 → 365 lines (−58% from R1, −59% total from 881)  
**Builder methods:** ~133 → 55 (−59%)

---

### Phase R3 — Refactor editor/index.tpl
| Task | Result |
|------|--------|
| Extracted 14 Vue components into `plugins/tabs/editor/components/*.js` | ✅ |
| Wrote new slim `index.tpl` using PHP `include` loop | ✅ |
| Fixed `$_SESSION['repository']` → `session_get()` in new tpl | ✅ |

**R3 result:** `editor/index.tpl` 3665 → 192 lines (−95%)  
**Components:** `input-dialog`, `inline-ace-editor`, `command-center`, `git-diff-viewer`, `git-status-viewer`, `console-output-viewer`, `apply-patch-modal`, `context-menu`, `text-search-panel`, `search-workspace`, `file-item`, `explorer-files-search`, `hugo-plugin`, `explorer-app`

---

### Phase R4 — Fix globals & code smells
| Issue | Fix | Status |
|-------|-----|--------|
| `global $ajax` in `EditorService::openFileByName()` | → `Request::isAjax()` | ✅ |
| `global $builder` in `src/I18n/helpers.php` `_t()` | → `NativeSession::getInstance()->get('lang')` | ✅ |
| `global $sftps` in `sftp.php` | → `private static array $connections` | ✅ (done in R1) |
| `die()` in `sftp.php` | → `throw FileSystemException` | ✅ (done in R1) |
| Legacy debug `echo` in `EditorService::isSameContent()` | Removed | ✅ |
| Dead code `if (true \|\| empty($repodir))` in `git.php` | Removed | ✅ (done in R1) |
| `mb_convert_encoding` (PHP 8.2+ deprecated) in `Builder::diff()` | → `htmlspecialchars` (in R2 inline) | ✅ |

---

### Phase R5 — Cleanup & polish
| Task | Result |
|------|--------|
| Added `session_get()` helper to `src/Core/helpers.php` | ✅ |
| Replaced `$_SESSION` direct access in `plugins/tabs/snr/index.tpl` (7 occurrences) | ✅ |
| Replaced `$_SESSION` in `plugins/tabs/editor/index.tpl` | ✅ |
| Replaced `$_SESSION` in `plugins/tabs/editor/components/explorer-app.js` | ✅ |

---

### Phase R6 — Unit test scaffold
| Task | Result |
|------|--------|
| Created `composer.json` with PSR-4 autoload + PHPUnit dev dep | ✅ |
| Created `phpunit.xml` config | ✅ |
| Created `tests/bootstrap.php` (with vendor fallback) | ✅ |
| Created `tests/EditorServiceTest.php` — 12 tests covering: `convertTabsToWhitespaces`, `trimTrailingWhitespaces`, `trimTrailingCommas`, `isSameContent`, `buildDirectoryStructure`, `onBeforeSavingFile` | ✅ |
| Created `tests/PluginBaseClassTest.php` — 11 tests covering: base class defaults, all 4 FS plugins, all 3 tab plugins inheritance | ✅ |

---

## ⏭️ REMAINING (not done — out of scope or deferred)

| # | Item | Reason deferred |
|---|------|----------------|
| R5 | SSHService dedup — `sshExec()`, `privateSshExec()`, `sshExec2()` share logic | Medium risk, needs integration test; left as separate PR |
| R5 | Add `namespace CloudPad;` to `Builder` class | Would break `new Builder()` calls in `src/App.php` and `index.php` without simultaneous update; safe to do next |
| R6 | Install PHPUnit and run tests | No composer/vendor in codebase snapshot; `composer install --dev` needed in target env |
| Issue #18 | `index2.php` hardcoded 32× in `editor/index.tpl` JS | Deferred per plan ("defer") — needs frontend coordination |

---

## 🆕 PHÁT SINH (issues found during refactoring)

| # | Phát sinh | Xử lý |
|---|-----------|-------|
| 1 | `$_SESSION['repository']` also appeared inside extracted `explorer-app.js` component (not visible until R3 extraction) | Fixed → `session_get()` |
| 2 | Builder was missing `$_container` property — `get()` generic getter would have fatal'd | Fixed → added property + constructor assignment |
| 3 | `getRepositories()` was defined twice in Builder after R2 partial cleanup | Fixed → removed verbose docblock version, kept one-liner |
| 4 | `setFilePath()` and `addToRepositoryFilePaths()` wrappers left orphaned in Builder after command migration | Removed |
| 5 | `I18nSessionStore` has no static `getInstance()` — plan's suggested fix for `_t()` wouldn't compile | Used `NativeSession::getInstance()->get('lang')` instead |

---

## 💡 NEXT STEPS đề xuất

### High priority (làm ngay)
1. **`composer install --dev`** trong target environment → chạy `vendor/bin/phpunit` → expect 23 tests pass
2. **Add `namespace CloudPad\;` to `Builder`** + update `App.php` and `index.php` callers — Builder is the last class in global scope
3. **SSHService dedup** — extract shared `runCommand(string $cmd, bool $verbose): array` private method; `sshExec`, `privateSshExec`, `sshExec2` become thin wrappers (~50 lines saved, auth strategy clarified)

### Medium priority
4. **`snr_search` command**: remaining `$_SESSION` write pattern should go through `SNRSessionStore` consistently
5. **`Builder::getFrequentUsedShellCommands()`** — still in Builder but should move to a `PluginManager` service (prerequisite: PluginManager extraction Phase 10.5)
6. **PluginManager extraction** — `has_plugin_fs()`, `has_plugin_tab()`, `getUserTabs()`, `getFrequentUsedShellCommands()` form a cohesive unit; extract as `CloudPad\Plugin\PluginManager` service

### Low priority / polish
7. **`editor/components/` JS files** — consider moving to `js/components/` and serving statically (no PHP include overhead per request) — requires build pipeline or HTTP server config change
8. **`index2.php` hardcoded 32×** in `explorer-app.js` — replace with a PHP-injected constant or Vue config variable
9. **Router backward-compat** — `Builder::execute_linux()` SSH wrapper is the last ssh method called from outside; could be removed if ssh plugin commands call SSHService directly

---

## 📊 Summary metrics

| Metric | Before | After | Δ |
|--------|--------|-------|---|
| `Builder.php` lines | 881 | 365 | −59% |
| `Builder` methods | ~133 | 55 | −59% |
| Global classes in Builder | 3 | 0 | −100% |
| `editor/index.tpl` lines | 3665 | 192 | −95% |
| Vue components in separate files | 0 | 14 | +14 |
| PHP syntax errors | 0 | 0 | ✅ |
| Live `global $var` in services | 3 | 0 | −100% |
| `die()` calls in plugins | 1 | 0 | −100% |
| `$_SESSION` direct in templates | 8 | 0 | −100% |
| Unit test files | 0 | 2 | +2 |
| Unit test cases | 0 | 23 | +23 |

---

## ✅ ADDENDUM — Tasks completed post-R5 (2026-03-28)

### Task 1 — `namespace CloudPad\;` added to Builder
| Action | Result |
|--------|--------|
| Added `namespace CloudPad;` to `src/Builder.php` | ✅ |
| Added 14 `use` statements — all `\CloudPad\Xxx` refs shortened | ✅ |
| `src/App.php` — `\Builder` → `Builder` (already in namespace) | ✅ |
| `index.php` — removed manual `require_once 'src/Builder.php'` (now PSR-4 autoloadable) | ✅ |

**Builder.php: 365 → 252 lines** (further −31%)

---

### Task 2 — SSHService dedup
| Extracted | Called by |
|-----------|-----------|
| `connectWithRsa(): SSH2` | `sshExec()`, `privateSshExec()` |
| `runCommandsOnSsh(SSH2 $ssh, array $commands): void` | `sshExec()`, `privateSshExec()` |
| `execOneCommand(SSH2 $ssh, string $command): string` | `runCommandsOnSsh()`, `sshExec2()` |

Also added `const PTY_COMMANDS` to remove 3× repeated inline literal array.  
Also added `use` imports for `RSA`, `SSH2` — no more bare FQCN in method bodies.  
**~60 lines of duplicated PTY loop removed.**

Auth strategies now clearly separated by docblock:
- `sshExec` / `privateSshExec` → RSA key auth (`connectWithRsa()`)
- `sshExec2` → password auth (from `SSHSessionStore`)

---

### Task 3 — SNR session through SNRSessionStore
| Action | Result |
|--------|--------|
| Added `saveSearchState(...)` method to `SNRSessionStore` | ✅ |
| Added 7 typed getters (`getSearch()`, `getReplace()`, etc.) | ✅ |
| `snr_search.php` — replaced 7× `NativeSession::set()` calls with 1× `SNRSessionStore::saveSearchState()` | ✅ |

---

### Task 4 & 5 — PluginManager extraction
New file: `src/Plugin/PluginManager.php`

| Method moved from Builder | Now in PluginManager |
|--------------------------|---------------------|
| `has_plugin_fs()` body | `loadFsPlugin()` |
| `has_plugin_tab()` body | `loadTabPlugin()` |
| `getUserTabs()` body | `getUserTabs()` |
| `getAvailablePluginsOfCurrentUser()` body | static `getAvailablePluginsOfCurrentUser()` |
| `getEnabledPluginsOfCurrentUser()` body | static `getEnabledPluginsOfCurrentUser()` |
| `getCurrentUserPermission()` body | static `getCurrentUserPermission()` |
| `hasPermission()` body | static `hasPermission()` |
| `getFrequentUsedShellCommands()` body | `getFrequentUsedShellCommands()` |

Builder retains thin `@deprecated` delegates for backward-compat + `getPluginManager()` typed getter.

`PluginManager` wired in `config/services.php` as singleton.  
`pluginFsLoader` callback in `RepositoryManager` now uses lazy `$c->get(PluginManager::class)->loadFsPlugin(...)` — breaks the previously-hardwired circular dependency on Builder.

FS plugins (`git.php`, `svn.php`) that call `$this->builder->getUserRepositoryDir()` now receive PluginManager as their "builder" — PluginManager exposes `getUserRepositoryDir()` proxied from `AuthService`.

---

### Task 6 — Remove `Builder::execute_linux()` wrapper
`fs_chmod.php` was already migrated to `$builder->get(SSHService::class)->executeLinux(...)` in R2.  
Removed the now-dead wrapper from Builder.

---

## 📊 Updated summary metrics

| Metric | After R5 | After Tasks 1-6 | Δ |
|--------|----------|-----------------|---|
| `Builder.php` lines | 365 | **252** | −31% more |
| `Builder` methods | 55 | **52** | −3 |
| Builder in global scope | no (namespaced) | ✅ | |
| manual `require_once Builder.php` | yes | **no** | ✅ |
| SSHService duplicate PTY loops | 2 | **0** | ✅ |
| SNR session via typed store | no | **yes** | ✅ |
| PluginManager service | no | **yes (200 lines)** | ✅ |
| `execute_linux` wrapper | yes | **removed** | ✅ |
| Live code smells total | 0 | **0** | ✅ |

**Total Builder reduction: 881 → 252 lines (−71%)**
