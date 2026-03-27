# CloudPad9 — Kế Hoạch Refactor Toàn Diện

**Phiên bản:** v3.0 (từ codebase v2.0.4)
**Ngày tạo:** 2026-03-26
**Mục tiêu:** Codebase clean, dễ mở rộng, dễ bảo trì, testable

---

## MỤC LỤC

1. [Tổng Quan Hiện Trạng](#1-tổng-quan-hiện-trạng)
2. [Sơ Đồ Kiến Trúc Mục Tiêu](#2-sơ-đồ-kiến-trúc-mục-tiêu)
3. [Phase 8 — Tách Bootstrap khỏi index.php](#phase-8)
4. [Phase 9 — Phá Vỡ God Object Builder](#phase-9)
5. [Phase 10 — Loại Bỏ Circular Dependency](#phase-10)
6. [Phase 11 — Session Abstraction](#phase-11)
7. [Phase 12 — Chuẩn Hóa Plugin Commands](#phase-12)
8. [Phase 13 — Gộp Duplicate Plugin Commands](#phase-13)
9. [Phase 14 — Tách Remaining Business Logic khỏi Builder](#phase-14)
10. [Phase 15 — Frontend Modularization](#phase-15)
11. [Phase 16 — Security Hardening](#phase-16)
12. [Phase 17 — Tổ Chức Thư Mục Public](#phase-17)
13. [Phase 18 — Cleanup & Documentation](#phase-18)
14. [Phụ Lục: Missing Methods](#phụ-lục-missing-methods)
15. [Thứ Tự Thực Hiện & Dependencies](#thứ-tự-thực-hiện)

---

## 1. Tổng Quan Hiện Trạng

### Số liệu codebase v2.0.4

| File / Module | Dòng | Vai trò |
|---|---|---|
| `index.php` | 827 | Bootstrap + Builder class (God Object) |
| `src/` (20 files) | ~2,544 | Service layer (11 classes + 4 exceptions + Request/Response/Router) |
| `plugins/commands/` (42 files) | ~1,627 | Action handlers |
| `plugins/fs/` (4 files) | ~249 | Filesystem plugins |
| `plugins/tabs/` (3 files) | ~42 | Tab renderers |
| `js/builder.js` | 2,558 | Toàn bộ frontend logic (monolith) |
| **Tổng source** | **~7,847** | |

### Các vấn đề nghiêm trọng (Critical)

#### 🔴 C1: God Object — Builder class (827 dòng, 70+ methods)

Builder vẫn là trung tâm của toàn bộ ứng dụng. Mặc dù đã delegate phần lớn logic sang service classes, nó vẫn giữ:
- **~55 thin wrapper methods** chỉ để forward calls sang services
- **~15 inline methods** chưa được tách (exec, flush, snr_*, diff, plugin_command logic)
- Mọi service đều nhận `$this` (Builder instance) trong constructor
- Mọi plugin command đều nhận `$builder` làm parameter duy nhất

**Hệ quả:** Không thể test bất kỳ service nào một cách độc lập. Thêm feature mới = thêm wrapper vào Builder.

#### 🔴 C2: Circular Dependency

```
Builder → creates → EditorService(Builder)
EditorService → calls → $this->builder->getAbsoluteFilePath()
                       → $this->builder->file_get_contents()
                       → $this->builder->searchForFile()
                       → $this->builder->getRepositorySettings()
                       ... (54 lần gọi ngược về Builder)
```

Tất cả 11 services đều depend on `\Builder`. Builder depends on tất cả 11 services. Đây là circular dependency pattern khiến:
- Unit testing không thể (phải mock toàn bộ Builder)
- Refactoring bất kỳ thứ gì đều có ripple effect
- Không thể tách services ra packages độc lập

#### 🔴 C3: Missing Methods — Runtime Errors

Các methods sau được GỌI nhưng KHÔNG tồn tại trong codebase:

| Method | Gọi từ | Hệ quả |
|---|---|---|
| `Builder::verbose()` | Router, RepositoryManager, FileSearchService, is_plugin_command | Fatal error khi encounter unknown action/bad repo |
| `Builder::has_plugin_fs()` | RepositoryManager::getRepositoryHandler() | Fatal error khi load bất kỳ repository nào |
| `Builder::save_pid()` | Builder::exec() | Fatal error khi chạy shell commands |
| `Builder::is_stop_pending()` | Builder::exec() | Fatal error khi long-running command |
| `Builder::getFrequentUsedShellCommands()` | SSHService::sshExec() | Fatal error khi SSH exec |
| `Builder::searchForFile()` | EditorService, RepositoryManager | Fatal error khi open/search file |
| `Builder::getRepositorySettings()` | Nhiều nơi qua Builder wrapper | Wrapper chưa tồn tại |
| `Builder::hasRepositoryPermission()` | copy_files.php, move_files.php | Wrapper chưa tồn tại |
| `Builder::getRepositoryFilePaths()` | FileSearchService | Wrapper chưa tồn tại |
| `Builder::getRepositoryWisePath()` | EditorService | Wrapper chưa tồn tại |
| `Builder::getFileRepository()` | SyncService | Wrapper chưa tồn tại |
| `Builder::rsearch()` | RepositoryManager | Wrapper chưa tồn tại |
| `Builder::snr_search()` | snr_search.php plugin | Method tồn tại nhưng thiếu dependency |

**Nguyên nhân:** Khi refactor từ 3,858 dòng → 827 dòng, một số method definitions và thin wrappers bị mất. Codebase hiện tại KHÔNG chạy được hoàn chỉnh.

### Các vấn đề trung bình (Medium)

#### 🟠 M1: 4 Response Patterns Khác Nhau Trong Plugin Commands

```php
// Pattern 1: Global function wrappers
json_ok(['output' => $out]);
json_fail('Source file not found.');

// Pattern 2: Builder method (deprecated)
$builder->json_response(['success' => false, 'message' => '...']);

// Pattern 3: Response class trực tiếp (recommended)
\CloudPad\Core\Response::ok();
\CloudPad\Core\Response::fail('...');

// Pattern 4: Response::json() raw (inconsistent)
\CloudPad\Core\Response::json(['success' => false, 'message' => '...']);
```

#### 🟠 M2: Plugin Commands Chưa Dùng Exception Pattern

Router đã có try/catch cho 4 exception types, nhưng 0/42 plugin commands throw exceptions. Tất cả vẫn dùng `echo/exit` hoặc `Response::fail()` (which calls `exit` internally).

#### 🟠 M3: Direct `$_SESSION` Access (50+ locations trong src/)

Services truy cập `$_SESSION` trực tiếp thay vì qua abstraction layer:
- `EditorService`: 15 lần
- `RepositoryManager`: 2 lần
- `AuthService`: 8 lần
- `ColorManager`: 1 lần
- Tất cả plugin commands: qua `$_SESSION` gián tiếp

#### 🟠 M4: Global State

```php
// index.php
global $ajax, $verbose;
global $builder;

// _t() function
global $_L;
global $builder;

// sftp plugin
global $sftps;
```

#### 🟠 M5: Duplicate Plugin Commands

| Nhóm | Files | Logic trùng |
|---|---|---|
| Git commit | `git_commit.php` (89L) + `git_commit_all.php` (47L) | ~70% giống nhau |
| Git diff | `git_diff.php` (29L) + `git_diff_all.php` (28L) | ~90% giống nhau |
| Git log | `git_log.php` (29L) + `git_log_all.php` (23L) | ~85% giống nhau |
| File ops | `copy_files.php` (44L) + `move_files.php` (42L) | ~80% giống nhau |

### Các vấn đề nhẹ (Low)

#### 🟡 L1: Frontend Monolith — `builder.js` (2,558 dòng)
- Không có module system
- jQuery thuần + jQuery UI
- Vue 2 loaded nhưng hầu như không dùng
- Tất cả functions ở global scope

#### 🟡 L2: Không Có Interfaces / Contracts
- Tất cả services là concrete classes
- Không thể swap implementations
- Không thể mock cho testing

#### 🟡 L3: Security Concerns
- `chmod 777` hardcoded ở nhiều nơi
- `execute.sh` wrapper cho shell commands — potential command injection surface
- `X-XSS-Protection: 0` header

#### 🟡 L4: No Autoloading cho Plugin Commands
- Mỗi plugin command là một file PHP với function hoặc class riêng
- Require thủ công qua `require_once` trong `is_plugin_command()`

---

## 2. Sơ Đồ Kiến Trúc Mục Tiêu

```
cloudpad9/
├── public/                          ← Web root (document root cho nginx/apache)
│   ├── index.php                    ← Entry point ONLY (~30 dòng)
│   ├── css/
│   ├── js/
│   │   ├── modules/                 ← JS modules tách từ builder.js
│   │   │   ├── editor.js
│   │   │   ├── file-tree.js
│   │   │   ├── ssh-terminal.js
│   │   │   ├── search-replace.js
│   │   │   ├── git-panel.js
│   │   │   └── utils.js
│   │   └── app.js                   ← Main entry, import modules
│   ├── fonts/
│   ├── images/
│   └── lib/                         ← Third-party frontend libs
│
├── src/                             ← PSR-4 autoloaded (CloudPad namespace)
│   ├── App.php                      ← Application bootstrap & DI container
│   ├── Core/
│   │   ├── Container.php            ← Simple DI container
│   │   ├── Request.php              ← (giữ nguyên)
│   │   ├── Response.php             ← (giữ nguyên)
│   │   ├── Router.php               ← Enhanced router
│   │   ├── Session/
│   │   │   ├── SessionInterface.php
│   │   │   └── NativeSession.php
│   │   ├── Output/
│   │   │   └── OutputManager.php    ← Tách flush/verbose logic
│   │   ├── Process/
│   │   │   └── ProcessManager.php   ← Tách exec/save_pid/is_stop_pending
│   │   └── Exceptions/              ← (giữ nguyên 4 files)
│   │
│   ├── Auth/
│   │   ├── AuthServiceInterface.php
│   │   └── AuthService.php
│   ├── Editor/
│   │   ├── EditorServiceInterface.php
│   │   ├── EditorService.php
│   │   ├── ColorManager.php
│   │   ├── RevisionManager.php
│   │   └── SyncService.php
│   ├── FileSystem/
│   │   ├── FileOperationsInterface.php
│   │   └── FileOperations.php
│   ├── Git/
│   │   ├── GitServiceInterface.php
│   │   └── GitService.php
│   ├── I18n/
│   │   └── Translator.php
│   ├── Repository/
│   │   ├── RepositoryManagerInterface.php
│   │   └── RepositoryManager.php
│   ├── Search/
│   │   ├── FileSearchServiceInterface.php
│   │   ├── FileSearchService.php
│   │   └── SearchAndReplace/
│   │       └── SNRService.php       ← Tách từ Builder
│   └── SSH/
│       └── SSHService.php
│
├── plugins/
│   ├── commands/                    ← (giữ, nhưng chuẩn hóa)
│   ├── fs/                          ← (giữ)
│   └── tabs/                        ← (giữ)
│
├── tpl/                             ← Templates
├── config/                          ← (mới)
│   ├── app.php                      ← Application config
│   └── services.php                 ← Service definitions cho DI
├── bin/                             ← Binary tools
├── cache/                           ← File cache
├── tmp/                             ← User temp data
├── locales/                         ← Language files
├── vendor/                          ← Composer
├── composer.json
└── .env
```

---

## Phase 8 — Tách Bootstrap Khỏi index.php {#phase-8}

**Mục tiêu:** index.php chỉ còn là entry point (~30 dòng). Tất cả class definitions và bootstrap logic được tách ra.

**Priority:** 🔴 Critical (prerequisite cho Phase 9-10)

### 8.1: Tạo `src/App.php` — Bootstrap class

**File mới:** `src/App.php`

```php
<?php
namespace CloudPad;

use Dotenv\Dotenv;

class App
{
    private static ?self $instance = null;
    private array $services = [];

    public static function boot(string $appDir): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Load .env
        $dotenv = Dotenv::createImmutable($appDir);
        $dotenv->load();

        // Define constants
        self::defineConstants($appDir);

        // PHP settings
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        error_reporting(E_ALL);
        set_time_limit(0);
        ob_implicit_flush(true);

        $instance = new self();
        $instance->appDir = $appDir;
        self::$instance = $instance;

        return $instance;
    }

    private static function defineConstants(string $appDir): void
    {
        define('SSH_PORT', $_ENV['SSH_PORT'] ?? 22);
        define('SSH_RSA_PRIVATE_FILE', $_ENV['SSH_RSA_PRIVATE_FILE'] ?? '');
        define('SSH_RSA_USERNAME', $_ENV['SSH_RSA_USERNAME'] ?? '');
        define('SSH_RSA_PASSPHRASE', $_ENV['SSH_RSA_PASSPHRASE'] ?? '');
        define('PHP_PATH', $_ENV['PHP_PATH'] ?? '/usr/bin/php');
        define('BUILDER_DIR', $appDir);

        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https:' : 'http:';
        $port = $_SERVER['SERVER_PORT'];
        $server = $_SERVER['SERVER_NAME'] . ($port != 80 && $port != 443 ? ':' . $port : '');
        define('BUILDER_ABSOLUTE_URL', $scheme . '//' . $server);
    }

    // ... service registration methods
}
```

### 8.2: Di chuyển `plugin_fs`, `plugin_tab`, `ProfilingHelper` ra khỏi index.php

| Class | Destination |
|---|---|
| `plugin_fs` | `src/Plugin/BaseFilesystemPlugin.php` |
| `plugin_tab` | `src/Plugin/BaseTabPlugin.php` |
| `ProfilingHelper` | `src/Core/Debug/ProfilingHelper.php` |

### 8.3: Di chuyển global functions ra file riêng

| Function | Destination |
|---|---|
| `_t()` | `src/I18n/helpers.php` (autoloaded via composer `files`) |
| `json_ok()`, `json_fail()`, `json_response()`, `json_success()` | `src/Core/helpers.php` (autoloaded via composer `files`) |

**Cập nhật `composer.json`:**
```json
{
    "autoload": {
        "psr-4": { "CloudPad\\": "src/" },
        "files": [
            "src/Core/helpers.php",
            "src/I18n/helpers.php"
        ]
    }
}
```

### 8.4: index.php mới (~30 dòng)

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

$app = \CloudPad\App::boot(__DIR__);

$action     = \CloudPad\Core\Request::getAction();
$standalone = \CloudPad\Core\Request::getString('standalone');
$ajax       = \CloudPad\Core\Request::isAjax();

// Auth check
$publicActions = ['user/login', 'user/logout', 'user/register', ...];
if (!in_array($action, $publicActions)) {
    $app->auth()->auth();
}

// Dispatch
$router = $app->router();
$router->dispatch($action, $standalone);

// Render UI (non-AJAX, non-download)
$renderable = !$ajax && !in_array($action, ['download-user-file', ...$publicActions]);
if ($renderable) {
    $builder = $app->legacyBuilder(); // backward compat
    include __DIR__ . '/tpl/index.tpl';
}
```

### Checklist Phase 8

- [ ] Tạo `src/App.php` với bootstrap logic
- [ ] Di chuyển `plugin_fs` → `src/Plugin/BaseFilesystemPlugin.php`
- [ ] Di chuyển `plugin_tab` → `src/Plugin/BaseTabPlugin.php`
- [ ] Di chuyển `ProfilingHelper` → `src/Core/Debug/ProfilingHelper.php`
- [ ] Tạo `src/Core/helpers.php` với json_ok/json_fail/json_response/json_success
- [ ] Tạo `src/I18n/helpers.php` với `_t()`
- [ ] Cập nhật `composer.json` autoload files
- [ ] Viết lại `index.php` chỉ còn entry point
- [ ] Test: tất cả actions hiện tại vẫn hoạt động

---

## Phase 9 — Phá Vỡ God Object Builder {#phase-9}

**Mục tiêu:** Builder class chuyển thành thin facade / backward-compat layer, không còn business logic.

**Priority:** 🔴 Critical

### 9.1: Khôi phục Missing Methods (FIX RUNTIME ERRORS)

⚠️ **QUAN TRỌNG: Thực hiện TRƯỚC mọi refactoring khác. Codebase hiện tại không chạy được do thiếu methods.**

Thêm các thin wrapper methods còn thiếu vào Builder:

```php
// === MISSING WRAPPERS — thêm vào Builder class ===

// Output — delegate sang OutputManager (Phase 9.2) hoặc inline tạm
function verbose(string $message): void {
    // Tạm thời: flush error message
    $this->flush_line($message . "\n");
}

// Plugin filesystem
function has_plugin_fs(string $type, &$handler = null): bool {
    $filepath = __DIR__ . "/plugins/fs/{$type}/{$type}.php";
    if (!file_exists($filepath)) return false;
    require_once $filepath;
    $classname = 'plugin_fs_' . $type;
    if (!class_exists($classname)) return false;
    $handler = new $classname($this);
    return true;
}

// Process management
function save_pid(int $pid): void {
    $file = $this->getUserDataDir() . '/.pid';
    file_put_contents($file, (string)$pid);
}

function is_stop_pending(): bool {
    $file = $this->getUserDataDir() . '/.stop';
    if (file_exists($file)) {
        unlink($file);
        return true;
    }
    return false;
}

// SSH shell commands — stub (hoặc implement từ user config)
function getFrequentUsedShellCommands(): array {
    $settings = $this->getRepositorySettings(
        \CloudPad\Core\Request::getString('repository')
    );
    $handler = $settings['handler'] ?? null;
    return $handler ? $handler->getRepositoryOperations($settings) : [];
}

// Repository wrappers (delegate sang RepositoryManager)
function getRepositories(): array { return $this->_repositoryManager->getRepositories(); }
function getRepositorySettings(string $repo): ?array { return $this->_repositoryManager->getRepositorySettings($repo); }
function hasRepositoryPermission(string $repo): bool { return $this->_repositoryManager->hasRepositoryPermission($repo); }
function getRepositoryFilePaths(string $repo, bool $force = false): array { return $this->_repositoryManager->getRepositoryFilePaths($repo, $force); }
function getRepositoryWisePath(string $fp, string $repo, string $fn): string { return $this->_repositoryManager->getRepositoryWisePath($fp, $repo, $fn); }
function getFileRepository(string $fp): string { return $this->_repositoryManager->getFileRepository($fp); }

// Search wrappers (delegate sang FileSearchService)
function searchForFile(string $fn, string $repo): string { return $this->_fileSearch->searchForFile($fn, $repo); }
function searchForFiles(string $fn, string $repo, int $limit = 0, bool $exact = false): array { return $this->_fileSearch->searchForFiles($fn, $repo, $limit, $exact); }
function rsearch(string $dir, array $excludes = [], array $includes = []): array { return $this->_fileSearch->rsearch($dir, $excludes, $includes); }
function glob(string $dir): array { return $this->_fileSearch->glob($dir); }
```

### 9.2: Tạo `src/Core/Output/OutputManager.php`

Tách flush/verbose/output logic từ Builder:

```php
<?php
namespace CloudPad\Core\Output;

class OutputManager
{
    public function flush(string $s): void { print $s; flush(); ob_flush(); }

    public function flushLine(string $s, bool $flushJs = false, ?string $jsMessage = null, bool $modal = true): void
    {
        // Di chuyển logic từ Builder::flush_line()
    }

    public function flushBlock(string $block): void { /* ... */ }

    public function flushJsMessage(string $message, string $type = 'info'): void { /* ... */ }

    public function flushJsNotification(string $message): void { /* ... */ }

    public function verbose(string $message): void { $this->flushLine($message . "\n"); }
}
```

### 9.3: Tạo `src/Core/Process/ProcessManager.php`

Tách process execution logic từ Builder:

```php
<?php
namespace CloudPad\Core\Process;

class ProcessManager
{
    private OutputManager $output;
    private string $userDataDir;

    public function exec(string $cmd, ?string $cwd = null, bool $returnOutput = false, string &$output = ''): int
    {
        // Di chuyển logic từ Builder::exec() (lines 308-378)
    }

    public function savePid(int $pid): void { /* ... */ }

    public function isStopPending(): bool { /* ... */ }
}
```

### 9.4: Tạo `src/Search/SearchAndReplace/SNRService.php`

Tách Search & Replace logic từ Builder:

```php
<?php
namespace CloudPad\Search\SearchAndReplace;

class SNRService
{
    // Di chuyển từ Builder:
    // snr_get_regex(), snr_get_pos(), snr_get_pos_with_regex()
    // snr_replace(), snr_revert(), snr_search() (nếu còn)
    // diff()
}
```

### 9.5: Builder trở thành Facade thuần

Sau Phase 9, Builder class chỉ còn:
1. Constructor: khởi tạo services
2. ~55 thin wrapper methods (backward compat cho plugin commands)
3. Getter methods cho services (`getEditorService()`, `getGitService()`, etc.)
4. KHÔNG còn business logic inline nào

**Target:** Builder < 200 dòng.

### Checklist Phase 9

- [ ] Khôi phục TẤT CẢ missing methods (9.1) — test runtime ngay
- [ ] Tạo `OutputManager` và migrate flush/verbose methods
- [ ] Tạo `ProcessManager` và migrate exec/pid/stop methods
- [ ] Tạo `SNRService` và migrate snr_*/diff methods
- [ ] Builder chỉ còn constructor + thin wrappers
- [ ] Test: mọi plugin command vẫn hoạt động qua Builder wrappers
- [ ] Đếm: Builder < 200 dòng

---

## Phase 10 — Loại Bỏ Circular Dependency {#phase-10}

**Mục tiêu:** Services không còn depend on `\Builder`. Thay vào đó, inject trực tiếp dependencies cần thiết.

**Priority:** 🔴 Critical (prerequisite cho testing)

### 10.1: Tạo Simple DI Container

**File mới:** `src/Core/Container.php`

```php
<?php
namespace CloudPad\Core;

class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function get(string $abstract): object
    {
        if (!isset($this->instances[$abstract])) {
            $factory = $this->bindings[$abstract]
                ?? throw new \RuntimeException("No binding for {$abstract}");
            $this->instances[$abstract] = $factory($this);
        }
        return $this->instances[$abstract];
    }
}
```

### 10.2: Refactor Services — Inject Dependencies Trực Tiếp

**Trước (circular):**
```php
class EditorService {
    private \Builder $builder;
    public function __construct(\Builder $builder) { $this->builder = $builder; }

    public function openFileByName(...) {
        $filepath = $this->builder->searchForFile($filename, $repository);
        $content  = $this->builder->file_get_contents($filepath, $repository);
        $color    = $this->builder->get_color($filepath);
        $rpath    = $this->builder->getRepositoryWisePath($filepath, $repository, $filename);
    }
}
```

**Sau (explicit dependencies):**
```php
class EditorService {
    private FileSearchServiceInterface $search;
    private FileOperationsInterface $fileOps;
    private ColorManager $colorManager;
    private RepositoryManagerInterface $repoManager;

    public function __construct(
        FileSearchServiceInterface $search,
        FileOperationsInterface $fileOps,
        ColorManager $colorManager,
        RepositoryManagerInterface $repoManager
    ) {
        $this->search       = $search;
        $this->fileOps      = $fileOps;
        $this->colorManager = $colorManager;
        $this->repoManager  = $repoManager;
    }

    public function openFileByName(...) {
        $filepath = $this->search->searchForFile($filename, $repository);
        $content  = $this->fileOps->fileGetContents($filepath, $repository);
        $color    = $this->colorManager->getColor($filepath);
        $rpath    = $this->repoManager->getRepositoryWisePath($filepath, $repository, $filename);
    }
}
```

### 10.3: Dependency Map — Mỗi Service Cần Gì

Phân tích chi tiết `$this->builder->` calls trong mỗi service để xác định actual dependencies:

| Service | Hiện tại gọi Builder methods | Actual dependencies |
|---|---|---|
| **EditorService** (54 calls) | searchForFile, file_get_contents, file_put_contents, get_color, getRepositorySettings, getRepositoryWisePath, getAbsoluteFilePath, getRepositoryFilePaths, save_file_revision, create_temp_revision, flush_line, getUserUploadDir, getUserTempDir, getUserDataDir | FileSearchService, FileOperations, ColorManager, RepositoryManager, RevisionManager, OutputManager, AuthService (for user dirs) |
| **RepositoryManager** (10 calls) | error, verbose, has_plugin_fs, try_exec, searchForFile (transitive) | OutputManager, Plugin\PluginManager (new), FileOperations |
| **RevisionManager** (16 calls) | getUserRevisionDir, getUserTempRevisionDir, file_get_contents, file_put_contents, getAbsoluteFilePath | AuthService (dirs), FileOperations, RepositoryManager |
| **SSHService** (7 calls) | flush_line, getFrequentUsedShellCommands | OutputManager, RepositoryManager |
| **FileOperations** (6 calls) | getRepositorySettings, flush_line | RepositoryManager, OutputManager |
| **SyncService** (5 calls) | getAbsoluteFilePath, file_get_contents, file_put_contents, getFileRepository, getRepositorySettings | RepositoryManager, FileOperations |
| **ColorManager** (4 calls) | getAbsoluteFilePath, file_get_contents, file_put_contents | RepositoryManager, FileOperations |
| **FileSearchService** (2 calls) | verbose, getRepositoryFilePaths | OutputManager, RepositoryManager |
| **AuthService** (2 calls) | file_put_contents, file_get_contents | FileOperations |
| **GitService** (0 calls) | Không gọi Builder | ✅ Đã clean |

### 10.4: Tạo Interfaces Cho Services Chính

```
src/Auth/AuthServiceInterface.php
src/Editor/EditorServiceInterface.php
src/FileSystem/FileOperationsInterface.php
src/Git/GitServiceInterface.php
src/Repository/RepositoryManagerInterface.php
src/Search/FileSearchServiceInterface.php
```

Mỗi interface chỉ chứa public methods cần thiết cho consumers khác.

### 10.5: Service Registration trong Container

**File:** `config/services.php`

```php
<?php
return function (\CloudPad\Core\Container $c) {
    $appDir = BUILDER_DIR;

    $c->singleton(OutputManager::class, fn($c) =>
        new OutputManager());

    $c->singleton(Translator::class, fn($c) =>
        new Translator($appDir));

    $c->singleton(AuthServiceInterface::class, fn($c) =>
        new AuthService($c->get(FileOperationsInterface::class), $appDir));

    $c->singleton(FileOperationsInterface::class, fn($c) =>
        new FileOperations(
            $c->get(RepositoryManagerInterface::class),
            $c->get(OutputManager::class)
        ));

    $c->singleton(RepositoryManagerInterface::class, fn($c) =>
        new RepositoryManager(
            $c->get(OutputManager::class),
            $c->get(FileOperationsInterface::class),
            $appDir
        ));

    // ... etc
};
```

### 10.6: Builder Becomes Service Locator (Backward Compat)

```php
class Builder
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    // Thin wrappers for backward compat
    function file_get_contents($file, $repo = '') {
        return $this->container->get(FileOperationsInterface::class)
            ->fileGetContents($file, $repo);
    }

    // Expose services for plugin commands that want to use new API
    function getEditorService(): EditorServiceInterface {
        return $this->container->get(EditorServiceInterface::class);
    }
    // ...
}
```

### Checklist Phase 10

- [ ] Tạo `Container.php`
- [ ] Tạo interfaces cho 6 services chính
- [ ] Refactor `GitService` (đã clean — verify)
- [ ] Refactor `FileSearchService` — inject OutputManager, RepositoryManager
- [ ] Refactor `AuthService` — inject FileOperations
- [ ] Refactor `FileOperations` — inject RepositoryManager, OutputManager
- [ ] Refactor `ColorManager` — inject RepositoryManager, FileOperations
- [ ] Refactor `RevisionManager` — inject AuthService, FileOperations, RepositoryManager
- [ ] Refactor `SyncService` — inject RepositoryManager, FileOperations
- [ ] Refactor `SSHService` — inject OutputManager, RepositoryManager
- [ ] Refactor `RepositoryManager` — inject OutputManager, FileOperations
- [ ] Refactor `EditorService` — inject all direct dependencies
- [ ] Tạo `config/services.php`
- [ ] Builder nhận Container thay vì tự tạo services
- [ ] Test: mọi chức năng vẫn hoạt động

---

## Phase 11 — Session Abstraction {#phase-11}

**Mục tiêu:** Không còn `$_SESSION` trực tiếp trong business logic.

**Priority:** 🟠 Medium

### 11.1: Tạo Session Interface

```php
<?php
namespace CloudPad\Core\Session;

interface SessionInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function remove(string $key): void;
    public function all(): array;
}
```

### 11.2: Tạo NativeSession Implementation

```php
<?php
namespace CloudPad\Core\Session;

class NativeSession implements SessionInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }
    // ...
}
```

### 11.3: Tạo Typed Session Accessors

Thay vì `$_SESSION['filepaths'][$repository][$filename]`, tạo:

```php
<?php
namespace CloudPad\Core\Session;

class EditorSessionStore
{
    private SessionInterface $session;

    public function getFilePath(string $repository, string $filename): string
    {
        return $this->session->get("filepaths.{$repository}.{$filename}", '');
    }

    public function setFilePath(string $repository, string $filename, string $filepath): void
    {
        $paths = $this->session->get('filepaths', []);
        $paths[$repository][$filename] = $filepath;
        $this->session->set('filepaths', $paths);
    }

    public function getOpenFiles(?string $repository = null): array { /* ... */ }
    public function getRecentFiles(string $repository): array { /* ... */ }
    public function getQuickAccess(): array { /* ... */ }
}
```

### 11.4: Migrate Session Access

Thay thế tất cả `$_SESSION[...]` trong `src/` bằng typed accessors:

| File | Số lần `$_SESSION` | Migrate sang |
|---|---|---|
| EditorService | 15 | EditorSessionStore |
| AuthService | 8 | AuthSessionStore |
| RepositoryManager | 2 | AuthSessionStore (for user repos) |
| ColorManager | 1 | AuthSessionStore (for username) |
| Plugin commands (snr) | 8 | SNRSessionStore |

### Checklist Phase 11

- [ ] Tạo `SessionInterface` + `NativeSession`
- [ ] Tạo `EditorSessionStore`, `AuthSessionStore`, `SNRSessionStore`
- [ ] Migrate `$_SESSION` access trong EditorService (15 locations)
- [ ] Migrate `$_SESSION` access trong AuthService (8 locations)
- [ ] Migrate `$_SESSION` access trong RepositoryManager (2 locations)
- [ ] Migrate `$_SESSION` access trong ColorManager (1 location)
- [ ] Register session services trong Container
- [ ] Test: session data persists correctly

---

## Phase 12 — Chuẩn Hóa Plugin Commands {#phase-12}

**Mục tiêu:** Tất cả plugin commands dùng cùng một response pattern + exception-based error handling.

**Priority:** 🟠 Medium

### 12.1: Chuẩn hóa Response Pattern

**Rule:** Tất cả plugin commands PHẢI dùng 1 trong 2 patterns:

```php
// Pattern A: Exception-based (RECOMMENDED cho error cases)
use CloudPad\Core\Exceptions\NotFoundException;
use CloudPad\Core\Exceptions\ValidationException;

if (empty($filepath)) {
    throw new NotFoundException('Source file not found.');
}

// Pattern B: Response class (cho success cases)
\CloudPad\Core\Response::ok(['output' => $data]);
```

**Cần loại bỏ:**
```php
// ❌ DEPRECATED — không dùng nữa
$builder->json_response(['success' => false, 'message' => '...']);
\CloudPad\Core\Response::json(['success' => false, 'message' => '...']); // dùng Response::fail() thay vì
json_fail('...'); // dùng throw exception thay vì
```

### 12.2: Migration Script Template

Với mỗi plugin command, áp dụng pattern:

```php
<?php
// TRƯỚC:
function copy_files($builder) {
    $paths = \CloudPad\Core\Request::getArray('paths');
    $repository = \CloudPad\Core\Request::getString('repository');
    $toPath = \CloudPad\Core\Request::getString('to');

    if (empty($repository) || empty($toPath)) {
        $builder->json_response(['success' => false, 'message' => 'Missing required parameters.']);
    }
    if (!$builder->hasRepositoryPermission($repository)) {
        $builder->json_response(['success' => false, 'message' => 'Permission denied.']);
    }
    // ...
}

// SAU:
use CloudPad\Core\Exceptions\ValidationException;
use CloudPad\Core\Exceptions\PermissionDeniedException;

function copy_files($builder) {
    $paths      = \CloudPad\Core\Request::getArray('paths');
    $repository = \CloudPad\Core\Request::require('repository');
    $toPath     = \CloudPad\Core\Request::require('to');

    if (!$builder->hasRepositoryPermission($repository)) {
        throw new PermissionDeniedException('Permission denied.');
    }

    $toDir = $builder->getAbsolutePath($toPath, $repository);
    if (!is_dir($toDir)) {
        throw new ValidationException("Destination '$toPath' is not a directory.");
    }
    // ...
    \CloudPad\Core\Response::ok();
}
```

### 12.3: Danh Sách Plugin Commands Cần Migrate

**Ưu tiên cao (mixed patterns, nhiều error paths):**

| File | Pattern hiện tại | Cần sửa |
|---|---|---|
| `copy_files.php` | `$builder->json_response()` | → throw exceptions |
| `move_files.php` | `$builder->json_response()` | → throw exceptions |
| `fs_chmod.php` | `$builder->json_response()` | → throw exceptions |
| `new_file.php` | `Response::json()` raw | → throw exceptions + Response::ok() |
| `new_directory.php` | `Response::json()` raw | → throw exceptions + Response::ok() |
| `rename_file.php` | `Response::json()` raw | → throw exceptions + Response::ok() |
| `rename_directory.php` | `Response::json()` raw | → throw exceptions + Response::ok() |
| `upload_file.php` | Mixed | → chuẩn hóa |

**Ưu tiên trung bình (dùng json_ok/json_fail — chỉ cần đổi error path sang throw):**

| File | Thay đổi |
|---|---|
| `git_commit.php` | `json_fail()` → `throw` |
| `git_commit_all.php` | `json_fail()` → `throw` |
| `git_diff.php` | `json_fail()` → `throw` |
| `git_diff_all.php` | `json_fail()` → `throw` |
| `git_log.php` | `json_fail()` → `throw` |
| `git_log_all.php` | `json_fail()` → `throw` |
| `git_pull.php` | `json_fail()` → `throw` |
| `git_status.php` | `json_fail()` → `throw` |
| `git_revert.php` | `json_fail()` → `throw` |
| `git_remove_untracked.php` | `json_fail()` → `throw` |
| `delete_file.php` | `json_fail()` → `throw` |
| `snr_search.php` | `Request::require()` — OK |

**Đã OK (dùng Response::ok() hoặc thin wrapper):**

| File | Status |
|---|---|
| `editor_close_all.php` | ✅ |
| `editor_clear_recents.php` | ✅ |
| `save_current_file.php` | ✅ (delegates) |
| Tất cả commands chỉ delegate | ✅ |

### Checklist Phase 12

- [ ] Migrate 8 plugin commands ưu tiên cao
- [ ] Migrate 10 plugin commands ưu tiên trung bình (git, delete)
- [ ] Verify 42 plugin commands đều dùng exception + Response pattern
- [ ] Xoá `$builder->json_response()` method từ Builder
- [ ] Test: tất cả error responses trả JSON đúng format

---

## Phase 13 — Gộp Duplicate Plugin Commands {#phase-13}

**Mục tiêu:** Loại bỏ code trùng lặp, giảm số file, thống nhất API.

**Priority:** 🟠 Medium

### 13.1: Gộp Git Commands

**git_commit.php + git_commit_all.php → git_commit.php**

```php
// Thêm parameter `scope`: "file" (default) hoặc "all"
$scope = \CloudPad\Core\Request::getString('scope', 'file');

if ($scope === 'all') {
    // Logic git_commit_all: git add .
} else {
    // Logic git_commit: git add -- <specific files>
}
```

**git_diff.php + git_diff_all.php → git_diff.php**

```php
$scope = \CloudPad\Core\Request::getString('scope', 'file');
$diffArg = ($scope === 'all') ? '--' : '-- ' . escapeshellarg($relpath);
```

**git_log.php + git_log_all.php → git_log.php**

```php
$scope = \CloudPad\Core\Request::getString('scope', 'file');
// scope=all → log toàn repo; scope=file → log per-file
```

### 13.2: Gộp File Operations Commands

**copy_files.php + move_files.php → file_transfer.php**

```php
$operation = \CloudPad\Core\Request::getString('operation'); // "copy" hoặc "move"
$shellCmd = ($operation === 'move') ? 'mv' : 'cp';
```

### 13.3: Frontend Update Required

Cập nhật `builder.js` để gửi thêm parameters:

```javascript
// TRƯỚC:
ajax('git-commit-all', { path: path, message: msg, repository: repo })

// SAU:
ajax('git-commit', { path: path, message: msg, repository: repo, scope: 'all' })
```

### 13.4: Danh Sách Files Cần Xoá Sau Gộp

| File cũ | Gộp vào |
|---|---|
| `git_commit_all.php` | `git_commit.php` |
| `git_diff_all.php` | `git_diff.php` |
| `git_log_all.php` | `git_log.php` |
| `move_files.php` | `file_transfer.php` (rename `copy_files.php`) |

### Checklist Phase 13

- [ ] Gộp `git_commit` + `git_commit_all` (thêm param `scope`)
- [ ] Gộp `git_diff` + `git_diff_all` (thêm param `scope`)
- [ ] Gộp `git_log` + `git_log_all` (thêm param `scope`)
- [ ] Gộp `copy_files` + `move_files` (thêm param `operation`)
- [ ] Update `builder.js` calls cho 4 nhóm trên
- [ ] Xoá 4 files thừa
- [ ] Test: tất cả git + file ops vẫn hoạt động từ UI

---

## Phase 14 — Tách Remaining Business Logic Khỏi Builder {#phase-14}

**Mục tiêu:** Builder thực sự chỉ còn ~100 dòng: constructor + backward compat wrappers.

**Priority:** 🟡 Low-Medium

### 14.1: Di chuyển `isMobile()` → Request helper

```php
// src/Core/Request.php — thêm method:
public static function isMobile(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return preg_match('/(iphone|ipad|android)/i', $ua)
        || preg_match('/^m\./i', $host);
}
```

### 14.2: Di chuyển `standalone_editor()` → Router

Router xử lý standalone case trực tiếp thay vì delegate qua Builder.

### 14.3: Di chuyển `is_plugin_command()` + `execute_plugin_command()` → Router

Đây là routing logic, thuộc về Router:

```php
// src/Core/Router.php — thêm:
private function resolvePluginCommand(string $commandPath): ?callable { /* ... */ }
```

### 14.4: Di chuyển `get_public_user_info()` → AuthService

Đã có `AuthService::getPublicUserInfo()` nhưng Builder vẫn giữ bản riêng.

### Checklist Phase 14

- [ ] Di chuyển `isMobile()` → `Request::isMobile()`
- [ ] Di chuyển `standalone_editor()` → Router
- [ ] Di chuyển `is_plugin_command()` + `execute_plugin_command()` → Router
- [ ] Xoá `get_public_user_info()` từ Builder (dùng AuthService)
- [ ] Builder còn < 100 dòng (constructor + wrappers only)

---

## Phase 15 — Frontend Modularization {#phase-15}

**Mục tiêu:** Tách `builder.js` (2,558 dòng) thành modules có tổ chức.

**Priority:** 🟡 Low (có thể làm incremental)

### 15.1: Module Map

Phân tích `builder.js` theo chức năng:

| Module | Lines (ước tính) | Functions |
|---|---|---|
| `modules/utils.js` | ~100 | `append_url`, `guid`, `uniqid`, `ensureTabs`, `_t`, `copyTextToClipboard` |
| `modules/editor.js` | ~400 | `convertToAceEditor`, `getFileExtension`, ace editor config, `onEditorContentChanged`, `editorSave`, `gotoLine` |
| `modules/ajax.js` | ~300 | `ajaxableForm`, `ajaxableLinks`, `onAjaxableResponse` |
| `modules/file-tree.js` | ~200 | File tree navigation, context menus, drag-drop |
| `modules/search-replace.js` | ~300 | `onSearchDone`, `onSearchAndReplaceResponse`, SNR tab logic |
| `modules/ssh-terminal.js` | ~200 | `ssh_exec`, `ssh_exec_quiet`, terminal UI |
| `modules/git-panel.js` | ~150 | Git status, commit, diff UI handlers |
| `modules/ui.js` | ~200 | Divider, tabs, multiselect, color picker, tooltips |
| `modules/timer.js` | ~150 | `setTimerInfo`, `showAccumulatedWorkTime`, `countDown` |
| `modules/speech.js` | ~200 | `bingSpeak`, `bingSpeakWithToken`, speech recognition |
| `app.js` | ~50 | Import + initialize all modules |

### 15.2: Migration Strategy — IIFE → ES Module (incremental)

Không cần build tool. Dùng `<script type="module">` cho trình duyệt hiện đại:

```html
<!-- tpl/index.tpl -->
<script type="module" src="js/app.js"></script>
```

```javascript
// js/app.js
import { initEditor } from './modules/editor.js';
import { initAjax } from './modules/ajax.js';
import { initFileTree } from './modules/file-tree.js';
// ...

$(function() {
    initEditor();
    initAjax();
    initFileTree();
    // ...
});
```

**Fallback:** Nếu cần hỗ trợ browser cũ, giữ `builder.js` concat nhưng tách logic thành functions có namespace:

```javascript
window.CloudPad = window.CloudPad || {};
window.CloudPad.Editor = { init: function() { /* ... */ } };
```

### 15.3: Loại bỏ thư viện không cần thiết

| Library | Status | Action |
|---|---|---|
| Vue 2 + vue-i18n + vue-color | Loaded nhưng gần như không dùng | Xoá nếu confirm không dùng |
| Moment.js | Check usage | Thay bằng native `Intl.DateTimeFormat` nếu ít dùng |
| Lodash | Check usage | Thay bằng native methods nếu chỉ dùng vài functions |

### Checklist Phase 15

- [ ] Tách `builder.js` thành 10+ modules
- [ ] Tạo `app.js` entry point
- [ ] Update `tpl/index.tpl` để load modules
- [ ] Audit và loại bỏ unused libraries (Vue 2, etc.)
- [ ] Test: tất cả UI interactions vẫn hoạt động

---

## Phase 16 — Security Hardening {#phase-16}

**Mục tiêu:** Loại bỏ security concerns đã phát hiện.

**Priority:** 🟡 Low-Medium (nên làm sớm cho production)

### 16.1: Loại bỏ `chmod 777`

**Hiện tại:** `FileOperations::filePutContents()` tự động `chmod 777` khi file không writable.

**Sửa:** Dùng permission hợp lý hơn:
```php
// Thay chmod 777 bằng:
$this->tryChmod('0664', $file);   // File: owner/group rw, other read
$this->tryChmod('0775', $dir);    // Dir: owner/group rwx, other rx
```

### 16.2: Audit `execute.sh` wrapper

File `execute.sh` được dùng bởi `FileOperations::tryExec()`. Cần:
- Verify script sanitizes input
- Thêm allowlist cho commands được phép chạy
- Log all command executions

### 16.3: Xoá `X-XSS-Protection: 0`

```php
// Thay:
header("X-XSS-Protection: 0");
// Bằng:
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com");
```

### 16.4: Path Traversal Protection

Thêm validation trong `FileOperations`:

```php
private function validatePath(string $path): void
{
    if (strpos(realpath($path) ?: $path, '..') !== false) {
        throw new \CloudPad\Core\Exceptions\PermissionDeniedException('Path traversal detected.');
    }
}
```

### Checklist Phase 16

- [ ] Thay `chmod 777` → permissions hợp lý (0664/0775)
- [ ] Audit `execute.sh` — thêm command allowlist
- [ ] Cập nhật security headers
- [ ] Thêm path traversal protection
- [ ] Review tất cả `exec()`, `shell_exec()` calls cho injection risks
- [ ] Đảm bảo error messages không leak internal paths cho end users

---

## Phase 17 — Tổ Chức Thư Mục Public {#phase-17}

**Mục tiêu:** Tách web-accessible files ra thư mục `public/`, non-web files KHÔNG nằm trong document root.

**Priority:** 🟡 Low

### 17.1: Tạo cấu trúc `public/`

```bash
mkdir -p public/{css,js,fonts,images,lib}
mv css/* public/css/
mv js/* public/js/
mv fonts/* public/fonts/
mv images/* public/images/
mv lib/* public/lib/
mv tpl/ public/tpl/    # hoặc giữ ngoài public nếu không cần web access
```

### 17.2: Cập nhật document root

**Nginx:**
```nginx
root /var/www/cloudpad9/public;
```

**Apache (.htaccess):**
```apache
DocumentRoot /var/www/cloudpad9/public
```

### 17.3: Cập nhật paths trong code

- `tpl/index.tpl`: Tất cả `src="js/..."` → giữ relative (đã ở trong public)
- `App.php`: Define `PUBLIC_DIR` constant
- Template paths cần update nếu `tpl/` di chuyển

### Checklist Phase 17

- [ ] Tạo `public/` directory structure
- [ ] Di chuyển static assets
- [ ] Cập nhật `index.php` entry point vào `public/index.php`
- [ ] Cập nhật web server config
- [ ] Cập nhật Docker config
- [ ] Test: tất cả assets load đúng

---

## Phase 18 — Cleanup & Documentation {#phase-18}

**Mục tiêu:** Code documentation, coding standards, developer guide.

**Priority:** 🟡 Low

### 18.1: PHPDoc cho tất cả public methods

Tất cả service methods phải có:
```php
/**
 * Mô tả ngắn gọn.
 *
 * @param  string $filename  Tên file (relative)
 * @param  string $repository  Repository code
 * @return string  Absolute file path, empty nếu không tìm thấy
 * @throws NotFoundException  Khi repository không tồn tại
 */
```

### 18.2: Tạo ARCHITECTURE.md

Mô tả:
- Cấu trúc thư mục và vai trò mỗi module
- Service dependency graph
- Request lifecycle (from HTTP request → response)
- Plugin system overview
- Session data structure

### 18.3: Tạo CONTRIBUTING.md

Rules cho developers:
- Coding standards (PSR-12)
- Naming conventions (camelCase cho methods, snake_case cho plugin commands)
- Response pattern (exception + Response::ok)
- Không dùng `$_SESSION` trực tiếp
- Không dùng `global`

### 18.4: Xoá Dead Code

| Item | Action |
|---|---|
| `index2.php` | Xoá (file gần trống) |
| Commented code trong Builder | Xoá |
| `// die('xxx'...)` trong snr_replace | Xoá |
| `if (true \|\| empty($repodir))` trong git.php | Fix condition |
| `finediff.php` include | Chuyển sang composer dependency hoặc tách file |

### Checklist Phase 18

- [ ] PHPDoc cho tất cả public methods trong `src/`
- [ ] Tạo `ARCHITECTURE.md`
- [ ] Tạo `CONTRIBUTING.md`
- [ ] Xoá dead code, commented code, debug statements
- [ ] Chạy PHP-CS-Fixer (PSR-12)
- [ ] Xoá `index2.php`
- [ ] Fix `if (true || ...)` condition trong `plugins/fs/git/git.php`

---

## Phụ Lục: Missing Methods — Chi Tiết Implement {#phụ-lục-missing-methods}

### Danh sách đầy đủ methods cần khôi phục vào Builder (Phase 9.1)

Dưới đây là implementation cụ thể cho mỗi missing method. Agent thực hiện cần thêm TẤT CẢ vào Builder class trước khi làm bất kỳ refactoring nào khác.

```php
// ============================================================
// GROUP 1: Output (sẽ move sang OutputManager ở Phase 9.2)
// ============================================================

/**
 * Log/output verbose message. Hiện tại flush ra response stream.
 * Chỉ output khi request có verbose=true.
 */
function verbose(string $message): void
{
    $this->flush_line($message . "\n");
}

// ============================================================
// GROUP 2: Plugin Filesystem
// ============================================================

/**
 * Load filesystem plugin handler theo type (local, git, sftp, svn).
 *
 * @param string $type    FS type identifier
 * @param object &$handler  Populated with plugin_fs_* instance on success
 * @return bool
 */
function has_plugin_fs(string $type, &$handler = null): bool
{
    $type = preg_replace('/[^a-z0-9_]/', '', strtolower($type));
    $filepath = __DIR__ . "/plugins/fs/{$type}/{$type}.php";

    if (!file_exists($filepath)) {
        return false;
    }

    require_once $filepath;

    $classname = 'plugin_fs_' . $type;

    if (!class_exists($classname)) {
        return false;
    }

    $handler = new $classname($this);
    return true;
}

// ============================================================
// GROUP 3: Process Management
// ============================================================

/**
 * Lưu PID của child process để có thể kill nếu cần.
 */
function save_pid(int $pid): void
{
    $file = $this->getUserDataDir() . '/.pid';
    @file_put_contents($file, (string)$pid);
}

/**
 * Kiểm tra user có yêu cầu stop process không.
 * Frontend gửi request tạo file .stop, method này kiểm tra và xoá.
 */
function is_stop_pending(): bool
{
    $file = $this->getUserDataDir() . '/.stop';

    if (file_exists($file)) {
        @unlink($file);
        return true;
    }

    return false;
}

// ============================================================
// GROUP 4: SSH Shell Commands
// ============================================================

/**
 * Trả danh sách shell commands thường dùng cho repository hiện tại.
 * Lấy từ repository handler (plugin_fs_local, plugin_fs_git, etc.)
 */
function getFrequentUsedShellCommands(): array
{
    $repository = \CloudPad\Core\Request::getString('repository');

    if (empty($repository)) {
        return [];
    }

    $settings = $this->getRepositorySettings($repository);

    if (empty($settings) || empty($settings['handler'])) {
        return [];
    }

    return $settings['handler']->getRepositoryOperations($settings);
}

// ============================================================
// GROUP 5: Repository Manager wrappers
// ============================================================

function getRepositories(): array
{
    return $this->_repositoryManager->getRepositories();
}

function getRepositorySettings(string $repository): ?array
{
    return $this->_repositoryManager->getRepositorySettings($repository);
}

function hasRepositoryPermission(string $repository): bool
{
    return $this->_repositoryManager->hasRepositoryPermission($repository);
}

function getRepositoryFilePaths(string $repository, bool $forceRebuild = false): array
{
    return $this->_repositoryManager->getRepositoryFilePaths($repository, $forceRebuild);
}

function getRepositoryWisePath(string $filepath, string $repository, string $filename): string
{
    return $this->_repositoryManager->getRepositoryWisePath($filepath, $repository, $filename);
}

function getFileRepository(string $filepath): string
{
    return $this->_repositoryManager->getFileRepository($filepath);
}

function getRepositoryCacheFile(string $repository): string
{
    return $this->_repositoryManager->getRepositoryCacheFile($repository);
}

// ============================================================
// GROUP 6: File Search wrappers
// ============================================================

function searchForFile(string $filename, string $repository): string
{
    return $this->_fileSearch->searchForFile($filename, $repository);
}

function searchForFiles(string $filename, string $repository, int $limit = 0, bool $exact = false): array
{
    return $this->_fileSearch->searchForFiles($filename, $repository, $limit, $exact);
}

function rsearch(string $dir, array $excludes = [], array $includes = []): array
{
    return $this->_fileSearch->rsearch($dir, $excludes, $includes);
}

function glob(string $dir): array
{
    return $this->_fileSearch->glob($dir);
}

function isExcludedPath(string $file, array $excludes, array $includes): bool
{
    return $this->_fileSearch->isExcludedPath($file, $excludes, $includes);
}
```

---

## Thứ Tự Thực Hiện & Dependencies {#thứ-tự-thực-hiện}

```
Phase 9.1 (FIX MISSING METHODS) ←←←← LÀM NGAY ĐẦU TIÊN
    │
    ▼
Phase 8 (Tách Bootstrap)
    │
    ├──→ Phase 9.2-9.5 (Phá Builder God Object)
    │       │
    │       ▼
    │    Phase 10 (Loại bỏ Circular Deps) ←← QUAN TRỌNG NHẤT
    │       │
    │       ├──→ Phase 11 (Session Abstraction)
    │       │
    │       └──→ Phase 14 (Tách remaining logic)
    │
    ├──→ Phase 12 (Chuẩn hóa Plugin Commands)
    │       │
    │       └──→ Phase 13 (Gộp Duplicates)
    │
    ├──→ Phase 15 (Frontend Modules) ←← Có thể làm song song
    │
    ├──→ Phase 16 (Security) ←← Có thể làm song song
    │
    └──→ Phase 17 (Public dir) ←← Cuối cùng
            │
            └──→ Phase 18 (Cleanup & Docs)
```

### Ước tính effort

| Phase | Effort | Risk | Notes |
|---|---|---|---|
| **9.1** (Fix missing methods) | 🟢 1-2h | 🔴 High nếu bỏ qua | Codebase không chạy được nếu thiếu |
| **8** (Bootstrap) | 🟡 4-6h | 🟡 Medium | Cần test kỹ entry flow |
| **9.2-9.5** (Break Builder) | 🟡 6-8h | 🟡 Medium | Tách methods + test wrappers |
| **10** (DI/Circular deps) | 🔴 12-16h | 🔴 High | Core architecture change |
| **11** (Session) | 🟡 4-6h | 🟡 Medium | 50+ locations to migrate |
| **12** (Plugin commands) | 🟡 4-6h | 🟢 Low | Repetitive, low risk |
| **13** (Merge duplicates) | 🟢 2-3h | 🟡 Medium | Need frontend update |
| **14** (Remaining Builder) | 🟢 2-3h | 🟢 Low | Small methods |
| **15** (Frontend) | 🟡 8-12h | 🟡 Medium | Large file, manual split |
| **16** (Security) | 🟡 4-6h | 🟢 Low | Important for production |
| **17** (Public dir) | 🟢 2-3h | 🟡 Medium | Docker/nginx config change |
| **18** (Cleanup) | 🟢 3-4h | 🟢 Low | Polish |

**Tổng ước tính:** ~50-75 giờ (engineer có kinh nghiệm với codebase)

---

## Metric Mục Tiêu Sau Refactor

| Metric | v2.0.4 (hiện tại) | v3.0 (mục tiêu) |
|---|---|---|
| `index.php` dòng | 827 | **~30** |
| Builder class dòng | ~560 (trong index.php) | **< 100** (thin wrappers) |
| Circular dependencies | 11 services → Builder → 11 services | **0** |
| Direct `$_SESSION` in src/ | 50 | **0** |
| Global variables | 5 | **0** |
| Response patterns | 4 | **1** (exception + Response) |
| Missing methods (runtime errors) | ~13 | **0** |
| builder.js dòng | 2,558 | **~50** (app.js) + 10 modules |
| Interfaces | 0 | **6+** |
| Unit testable services | 0 | **11** |

---

*Document tạo ngày 2026-03-26 — CloudPad9 Refactor Plan v3.0*
