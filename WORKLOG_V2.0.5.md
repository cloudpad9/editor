# WORKLOG — CloudPad9 v2.0.5

**Ngày tạo:** 2026-03-26
**Dựa trên:** `WORKLOG_V2.0.4.md`
**Trạng thái tổng quát:** Phase 0 ✅ · Phase 1 ✅ · Phase 2 ✅ · Phase 3 ✅ · Phase 4 ✅ · Phase 6 ✅ · Phase 7 (partial) ✅ · **Phase 9.1 ✅**

---

## TÓM TẮT SỐ LIỆU

| Metric | v2.0.4 | v2.0.5 | Ghi chú |
|--------|--------|--------|---------|
| `index.php` dòng | 827 | **1,448** | +621 dòng (31 restored methods + 1 big method) |
| Builder methods (total) | ~99 | **130** | +31 methods khôi phục |
| Builder thin wrappers | ~55 | **~86** | +31 wrappers delegate sang services |
| Missing method errors | **~13** | **0** | ✅ 68/68 called methods có definitions |
| Bugfixes | — | **1** | fs_chmod.php: ssh_xxx → execute_linux |
| `src/` files | 18 | 18 | Không thay đổi |
| Plugin commands | 51 | 51 | 1 file sửa (fs_chmod.php) |

---

## ✅ Phase 9.1 — Khôi Phục Missing Methods (CRITICAL FIX)

### Bối cảnh

Khi refactor v2.0.1→v2.0.4, nhiều methods được di chuyển từ Builder (index.php) sang service classes trong `src/`. Tuy nhiên, các **thin wrapper methods** trong Builder — cần thiết để services gọi ngược qua `$this->builder->methodName()` — không được tạo đầy đủ.

Kết quả: codebase v2.0.4 **không chạy được** vì hầu hết mọi flow (load repository, mở file, search, git operations, SSH exec) đều trigger fatal error do gọi method không tồn tại trên Builder.

### Phương pháp phát hiện

1. Extract tất cả `$this->builder->X()` calls từ `src/` (54+ unique methods)
2. Extract tất cả `$builder->X()` calls từ `plugins/` và `tpl/` (15+ unique methods)
3. Extract tất cả `Builder::X()` static calls từ `plugins/` và `tpl/`
4. Cross-check với method definitions trong Builder class
5. So sánh với original `index.php` (3,856 dòng) để lấy implementation gốc

### 9.1.1: Methods khôi phục — Thin Wrappers (delegate sang existing services)

Các methods này chỉ là 1-line wrappers, delegate sang service class đã có sẵn:

#### Repository Manager wrappers (8 methods)

| Method | Delegate sang | Gọi bởi |
|--------|---------------|---------|
| `getRepositories()` | `_repositoryManager->getRepositories()` | tabs/editor, tabs/snr templates |
| `getRepositorySettings($repo)` | `_repositoryManager->getRepositorySettings()` | EditorService, FileOperations, SyncService |
| `hasRepositoryPermission($repo)` | `_repositoryManager->hasRepositoryPermission()` | EditorService, copy_files, move_files |
| `getRepositoryFilePaths($repo, $force)` | `_repositoryManager->getRepositoryFilePaths()` | FileSearchService, EditorService |
| `getRepositoryWisePath($fp, $repo, $fn)` | `_repositoryManager->getRepositoryWisePath()` | EditorService, rename plugins |
| `getFileRepository($fp)` | `_repositoryManager->getFileRepository()` | SyncService |
| `getRepositoryCacheFile($repo)` | `_repositoryManager->getRepositoryCacheFile()` | EditorService |
| `getProjectFilePaths($repo, $force)` | `_repositoryManager->getProjectFilePaths()` | rebuild_filepaths_indexes plugin |

#### File Search wrappers (6 methods)

| Method | Delegate sang | Gọi bởi |
|--------|---------------|---------|
| `searchForFile($fn, $repo)` | `_fileSearch->searchForFile()` | EditorService, RepositoryManager |
| `searchForFiles($fn, $repo, $limit, $exact)` | `_fileSearch->searchForFiles()` | EditorService |
| `searchForFilesInArray($fn, $arr, $limit, $exact)` | `_fileSearch->searchForFilesInArray()` | EditorService |
| `rsearch($dir, $excludes, $includes)` | `_fileSearch->rsearch()` | EditorService, RepositoryManager |
| `glob($dir)` | `_fileSearch->glob()` | (internal) |
| `isExcludedPath($file, $excludes, $includes)` | `_fileSearch->isExcludedPath()` | (internal) |

#### Editor Service wrappers (4 methods)

| Method | Delegate sang | Gọi bởi |
|--------|---------------|---------|
| `setFilePath($fn, $fp, $repo)` | `_editorService->setFilePath()` | EditorService (internal callback) |
| `addToRepositoryFilePaths($fp, $repo)` | `_editorService->addToRepositoryFilePaths()` | rename_file, rename_directory_of_file plugins |
| `getOpenFiles($tempOnly)` | `_editorService->getOpenFiles()` | (internal) |
| `getEditorOpenFiles($tempOnly)` | `_editorService->getEditorOpenFiles()` | tabs/editor/index.tpl |

### 9.1.2: Methods khôi phục — Implementations (logic inline trong Builder)

Các methods này chứa business logic riêng, không delegate sang service class:

#### Output / Logging (1 method)

```php
function verbose($content)
```
- **Logic:** Flush message nếu request có `verbose=true`
- **Gọi bởi:** Router (unknown action), RepositoryManager (repo not found), FileSearchService (dir not exist), is_plugin_command (class not found)
- **Nguồn gốc:** Line 2117 trong original index.php

#### Process Management (2 methods)

```php
function save_pid(int $pid): void
function is_stop_pending(): bool
```
- **Logic:** Ghi/đọc PID và stop flag từ user data dir
- **Gọi bởi:** Builder::exec() (lines 330, 362)
- **Nguồn gốc:** Không có trong original (đã là `$this->save_pid()` / `$this->is_stop_pending()` nhưng body bị mất). Implementation mới dựa trên convention file `.pid` / `.stop` trong user data dir.

#### Plugin Filesystem (1 method)

```php
function has_plugin_fs($fs, &$handler)
```
- **Logic:** Load `plugins/fs/{type}/{type}.php`, instantiate handler, validate required methods
- **Gọi bởi:** RepositoryManager::getRepositoryHandler()
- **Nguồn gốc:** Lines 2498-2532 trong original index.php
- **⚠️ Critical:** Không có method này thì KHÔNG repository nào load được

#### Plugin Tabs (2 methods)

```php
function has_plugin_tab($tab, &$handler)
function getUserTabs()
```
- **Logic:** Load `plugins/tabs/{tab}/index.php`, instantiate handler, validate required methods. `getUserTabs()` iterates enabled plugins và returns handlers.
- **Gọi bởi:** tpl/index.tpl (line 93)
- **Nguồn gốc:** Lines 2597-2646 trong original index.php
- **⚠️ Critical:** Không có method này thì UI không render tab nào

#### User / Permission (7 methods)

```php
static function getAvailablePluginsOfCurrentUser()
static function getEnabledPluginsOfCurrentUser()
static function getCurrentUserPermission()
static function hasPermission($key)
function getCurrentUser()
function getCurrentUsername()
function getUsers()
```
- **Logic:** Đọc từ `$_SESSION['builder.user']` và config file
- **Gọi bởi:** getUserTabs(), tab templates (`Builder::hasPermission()`), user login plugin (`getUsers()`)
- **Nguồn gốc:** Lines 2537-2590 trong original index.php

#### SSH (1 method)

```php
function getFrequentUsedShellCommands(): array
```
- **Logic:** Lấy repository settings → handler → `getRepositoryOperations()`
- **Gọi bởi:** SSHService::sshExec() (line 52)
- **Nguồn gốc:** Deduced từ original SSH exec flow (line 3019)

#### Search & Replace (2 methods)

```php
function snr_search($repository, $search, $caseinsensitive, $file_mask, ...)
function file_mask_matched($pattern, $path)
```
- **Logic:** Full S&R engine — iterate file paths, search line-by-line, output HTML results, optional replace/delete
- **Gọi bởi:** plugins/commands/snr_search.php
- **Nguồn gốc:** Lines 2671-2710 (`file_mask_matched`) và 2713-2850 (`snr_search`) trong original index.php
- **Kích thước:** `snr_search` ~130 dòng — method lớn nhất được khôi phục

#### Misc (1 method)

```php
function get_sub_dirs($dir)
```
- **Logic:** List subdirectories trong một directory
- **Nguồn gốc:** Original index.php

### 9.1.3: Bugfix — `fs_chmod.php`

**File:** `plugins/commands/fs_chmod.php` line 23

**Trước:**
```php
$builder->ssh_xxx($command);
```

**Sau:**
```php
$builder->execute_linux($command);
```

**Nguyên nhân:** `ssh_xxx` không phải tên method hợp lệ — đây là placeholder hoặc typo từ quá trình refactor. Method đúng là `execute_linux()` (đã có wrapper trong Builder, delegate sang SSHService).

---

## VERIFICATION

### Syntax check: ✅ PASS

```
$ php -l index.php
No syntax errors detected in index.php

$ for f in src/*/*.php src/Core/Exceptions/*.php plugins/commands/*.php; do php -l "$f"; done
→ No syntax errors detected (tất cả files)
```

### Method coverage: ✅ 68/68

```
=== Methods CALLED on Builder (68 unique) ===
  addToRepositoryFilePaths, clone_file, close_file, create_temp_revision,
  delete_user_file, diff, download_user_file, error, exec, execGitCommand,
  file_exists, file_get_contents, file_live_search, file_put_contents,
  flush_line, getAbsoluteFilePath, getAbsolutePath, getEditorOpenFiles,
  getFileRepository, getFrequentUsedShellCommands, getProjectFilePaths,
  getRepositories, getRepositoryCacheFile, getRepositoryFilePaths,
  getRepositorySettings, getRepositoryWisePath, getUserRevisionDir,
  getUserSessionId, getUserTabs, getUserTempDir, getUserTempRevisionDir,
  getUserUploadDir, getUsers, get_color, get_directory_children,
  get_directory_structure, get_file_content, get_git_info, hasPermission,
  hasRepositoryPermission, has_plugin_fs, isUserLoggedIn, is_plugin_command,
  json_response, new_temp_file, open_file_by_name, rebuild_sub_indexes,
  recover_file, reloadUserSessionData, reload_file, revert_file, rsearch,
  save_current_file, save_file_revision, searchForFile, searchForFiles,
  searchForFilesInArray, serializeUserSessionData, setFilePath, set_color,
  snr_search, standalone_editor, sync_file, try_chmod, try_exec,
  upload_file, verbose, execute_linux

=== MISSING ===
  ✅ NONE — All 68 called methods have definitions
```

---

## FILES THAY ĐỔI

```
cloudpad9/
├── index.php                    ← 1,448 dòng (+621) — 31 methods restored
├── plugins/commands/
│   └── fs_chmod.php             ← Bugfix: ssh_xxx → execute_linux
└── WORKLOG_V2.0.5.md           ← NEW: worklog này
```

---

## CẤU TRÚC HIỆN TẠI

```
cloudpad9/
├── index.php                              ← 1,448 dòng — Builder class + bootstrap
│                                             130 methods (86 thin wrappers + 44 inline)
├── src/
│   ├── Core/
│   │   ├── Request.php                    ← Input validation
│   │   ├── Response.php                   ← JSON response helpers
│   │   ├── Router.php                     ← Action dispatcher + exception catch
│   │   └── Exceptions/
│   │       ├── NotFoundException.php
│   │       ├── PermissionDeniedException.php
│   │       ├── ValidationException.php
│   │       └── FileSystemException.php
│   ├── I18n/
│   │   └── Translator.php
│   ├── Auth/
│   │   └── AuthService.php
│   ├── Editor/
│   │   ├── ColorManager.php
│   │   ├── EditorService.php              ← 750 dòng — largest service
│   │   ├── RevisionManager.php
│   │   └── SyncService.php
│   ├── SSH/
│   │   └── SSHService.php
│   ├── Repository/
│   │   └── RepositoryManager.php
│   ├── Git/
│   │   └── GitService.php
│   ├── FileSystem/
│   │   └── FileOperations.php
│   └── Search/
│       └── FileSearchService.php
├── plugins/
│   ├── commands/                          ← 51 files
│   ├── fs/                                ← 4 handlers (local, git, sftp, svn)
│   └── tabs/                              ← 3 tabs (editor, diff, snr)
├── tpl/
│   └── index.tpl
├── js/
│   └── builder.js                         ← 2,558 dòng — frontend monolith
├── css/, fonts/, images/, lib/            ← Static assets
├── bin/rust/                              ← Rust binary cho rebuild-indexes
├── composer.json
├── repositories.conf.php
├── users.conf.example.php
└── WORKLOG_V2.0.5.md                     ← ✅ NEW
```

---

## CÒN LẠI — CHƯA LÀM (từ Refactoring Plan v3.0)

### 🔄 Phase 5 — Gộp Duplicate Plugin Commands
- Gộp `git_log.php` + `git_log_all.php` → thêm param `scope`
- Gộp `git_diff.php` + `git_diff_all.php` → thêm param `scope`
- Gộp `git_commit.php` + `git_commit_all.php` → thêm param `scope`
- Gộp `copy_files.php` + `move_files.php` → thêm param `operation`

### 🔄 Phase 6.3 — Migrate plugin commands sang throw pattern
- 42+ plugin commands vẫn dùng json_fail()/Response::fail() thay vì throw exceptions
- Router đã sẵn sàng catch, chỉ cần migrate từng command

### 🔄 Phase 8 — Tách Bootstrap khỏi index.php
- Tạo `src/App.php` bootstrap class
- Di chuyển plugin_fs, plugin_tab, ProfilingHelper ra files riêng
- Tách global functions (_t, json_ok, json_fail) sang autoloaded helpers
- index.php → ~30 dòng entry point

### 🔄 Phase 9.2-9.5 — Tiếp tục phá Builder God Object
- Tạo OutputManager (tách flush/verbose)
- Tạo ProcessManager (tách exec/save_pid/is_stop_pending)
- Tạo SNRService (tách snr_search/file_mask_matched)
- Builder target: < 200 dòng

### 🔄 Phase 10 — Loại bỏ Circular Dependency (QUAN TRỌNG)
- Tạo DI Container
- Tạo Interfaces cho 6 services chính
- Refactor services: inject dependencies trực tiếp thay vì `\Builder`
- Builder trở thành service locator / backward compat layer

### 🔄 Phase 11-18 — Session, Security, Frontend, Cleanup
- Xem chi tiết trong `CLOUDPAD9_REFACTORING_PLAN.md`

---

## NHỮNG VẤN ĐỀ CẦN CHÚ Ý

### ⚠️ `save_pid()` / `is_stop_pending()` — implementation mới
Hai methods này không có trong original index.php dưới dạng named methods (logic nằm inline trong `exec()`). Implementation hiện tại dùng file `.pid` / `.stop` trong user data dir. Cần verify trên server thực rằng:
1. `getUserDataDir()` trả path writable
2. Frontend có logic tạo file `.stop` khi user nhấn Stop

### ⚠️ `getFrequentUsedShellCommands()` — deduced implementation
Method này không có body rõ ràng trong original. Implementation hiện tại lấy commands từ `repository handler → getRepositoryOperations()`. Cần verify logic SSH exec trên server thực.

### ⚠️ `snr_search()` — large method (130 dòng) vẫn nằm trong Builder
Method này nên được tách sang `SNRService` trong Phase 9.4. Hiện tại giữ trong Builder vì plugin `snr_search.php` gọi `$builder->snr_search()`.

### ⚠️ Các vấn đề từ v2.0.4 vẫn còn
- `EditorService::addToRepositoryFilePaths()` — cache path hardcode
- `FileOperations::isEmptyDir()` — behavior khác so với Builder cũ
- `getFrequentUsedShellCommands()` — chưa test với SSH flow thực tế
- Plugin commands chưa dùng Exception classes

---

*Worklog tạo sau khi hoàn thành Phase 9.1 — Critical Fix: Khôi phục 31 missing methods + 1 bugfix — CloudPad9 v2.0.5*
