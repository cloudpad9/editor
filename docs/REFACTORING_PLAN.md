# CloudPad9 — Kế Hoạch Refactor Chi Tiết

**Ngày tạo:** 2026-03-24
**Phiên bản codebase hiện tại:** Legacy monolith (~3858 dòng `index.php`, ~2935 dòng `builder.js`)
**Mục tiêu:** Clean architecture, dễ mở rộng, dễ bảo trì, error handling chuẩn

---

## MỤC LỤC

1. [Tổng quan hiện trạng](#1-tổng-quan-hiện-trạng)
2. [Danh sách vấn đề phát hiện](#2-danh-sách-vấn-đề-phát-hiện)
3. [Kiến trúc mục tiêu](#3-kiến-trúc-mục-tiêu)
4. [Kế hoạch thực hiện theo Phase](#4-kế-hoạch-thực-hiện-theo-phase)
5. [Chi tiết từng Phase](#5-chi-tiết-từng-phase)
6. [Files cần xoá / gộp](#6-files-cần-xoá--gộp)
7. [Checklist kiểm tra sau refactor](#7-checklist-kiểm-tra-sau-refactor)

---

## 1. Tổng Quan Hiện Trạng

### Cấu trúc hiện tại

```
index.php                  ← 3858 dòng: God class Builder + global functions + action routing
index2.php                 ← Wrapper include index.php (cho nginx gzip config)
repositories.conf.php      ← Config repositories
users.conf.php             ← Config users (bcrypt password)
tpl/index.tpl              ← Main HTML template (142 dòng)
js/builder.js              ← 2935 dòng jQuery + vanilla JS
css/builder.css            ← 31K CSS
plugins/
  commands/                ← Plugin commands (đã migrate 1 phần từ index.php)
  fs/                      ← Filesystem plugins (local, sftp, git, svn)
  tabs/                    ← Tab plugins (editor, snr, diff, directory-structure-viewer)
lib/                       ← Vendor JS/CSS libraries (nhiều bản duplicate)
```

### Các con số quan trọng

| Metric | Giá trị |
|--------|---------|
| Dòng code `index.php` | 3,858 |
| Số method trong class `Builder` | ~120+ |
| Số action chưa migrate sang plugin | ~25 |
| Số lần dùng `echo json_encode()` trực tiếp | 29 (trong index.php) |
| Số lần truy cập `$_REQUEST` không validate | 79 (trong index.php) |
| Library JS duplicate | 5+ bộ (multiselect, vue, ace, bootstrap, codemirror) |
| API key hardcode | 1 (ipdata.co) |

---

## 2. Danh Sách Vấn Đề Phát Hiện

### 2.1. Architecture — God Class & God File

**[DEBT:COUPLING] `index.php` là God File**

File `index.php` chứa TẤT CẢ trong 1 file duy nhất:
- Global helper functions (`_t()`, `json_response()`, `json_fail()`, `json_ok()`)
- Base classes (`plugin_fs`, `plugin_tab`, `ProfilingHelper`)
- God class `Builder` (~120 methods, ~3300 dòng)
- Action routing (if/else chain ~300 dòng)
- Bootstrap/init logic

**Hệ quả:** Mọi thay đổi dù nhỏ đều phải sửa file này. Khó test, khó review, dễ conflict khi nhiều người cùng sửa.

### 2.2. Action Routing — Spaghetti if/else

**[DEBT:FRAGILE] Action routing bằng chuỗi if/else if (dòng 3597-3854)**

```php
if ($action == 'open-file' && $standalone) { ... }
if ($builder->is_plugin_command($action, $handler, $methodname)) { ... }
else if ($action == 'download-user-file') { ... }
else if ($action == 'delete-user-file') { ... }
// ... 25+ actions nữa
```

Một số action đã migrate sang `plugins/commands/`, nhưng ~25 action vẫn nằm inline trong `index.php`. Không có pattern thống nhất.

### 2.3. Response Format không nhất quán

**[DEBT:HIDDEN] 4 cách trả JSON response khác nhau**

| Pattern | Nơi dùng |
|---------|----------|
| `echo json_encode(['success' => ...])` | index.php (29 lần), nhiều plugin commands |
| `$builder->json_response([...])` | Builder method (cũng `echo json_encode` + `exit`) |
| `$builder->error($message)` | Builder method (echo + exit) |
| `json_ok()` / `json_fail()` | Global function (một số plugin commands mới) |

**Vấn đề cụ thể:**
- `Builder::json_response()` KHÔNG set Content-Type header → có thể trả text/html
- `Builder::error()` KHÔNG set Content-Type → client có thể parse fail
- Global `json_response()` CÓ set Content-Type nhưng khác implementation với `Builder::json_response()`
- Một số plugin commands tự set `header('Content-Type: application/json')` riêng

### 2.4. Input Validation hầu như không có

**[DEBT:SECURITY] `$_REQUEST` truy cập trực tiếp không validate**

```php
// Ví dụ trong action routing (index.php:3611-3613)
$name = $_REQUEST['name'];          // Không check isset, không validate
$directory = isset($_REQUEST['directory']) ? $_REQUEST['directory'] : '';

// Ví dụ trong plugin commands
$file = $_REQUEST['file'];          // fs_chmod.php - truyền thẳng vào shell command!
```

**Các rủi ro cụ thể:**
- `fs_chmod.php`: `$file = $_REQUEST['file']` → truyền thẳng vào `chmod 777 $file` → **Command Injection**
- `copy_files.php`: `$paths = explode(',', $_REQUEST['paths'])` → truyền vào `cp $path $toDir` → **Path Traversal**
- `move_files.php`: Tương tự copy_files
- `download_user_file()`: Cho phép download path tuyệt đối nếu `is_file($filename)` → **Arbitrary File Read**
- Nhiều action không check `hasRepositoryPermission()`

### 2.5. Error Handling thiếu và không nhất quán

**[DEBT:FRAGILE] Error handling hiện tại**

| Tình huống | Cách xử lý hiện tại |
|------------|---------------------|
| File not found | Một số `return`, một số `exit`, một số tiếp tục chạy |
| JSON parse fail | Silent fail (catch rỗng trong JS) |
| SSH connection fail | `exit('SSH login failed')` — không JSON response |
| Plugin command not found | `return false` — request treo, không response |
| Invalid repository | `$this->error()` — exit nhưng không đúng format |
| `unpin_from_quick_access` | Gọi `json_response` SAU vòng `foreach` → nếu tìm thấy sẽ gọi response 2 lần |

**Bug cụ thể trong `unpin_from_quick_access.php`:**
```php
foreach ($_SESSION['quick-access'] as $index => $item) {
    if ($item['repository'] == $repository && $item['path'] == $path) {
        unset($_SESSION['quick-access'][$index]);
        $builder->json_response(array('success' => true));  // Gọi ở đây → exit
    }
}
$builder->json_response(array('success' => false, 'message' => 'Item not found'));
// ↑ Dòng này luôn chạy nếu item ở cuối array (vì response trước đã exit)
// Nhưng nếu array rỗng hoặc không match → chạy bình thường
// Tuy nhiên logic chính xác: cần return/exit sau khi unset
```

**Bug trong `git_commit.php`:**
```php
$message = $_REQUEST['path'] ?? '';  // Dòng 5: DÙNG 'path' thay vì 'message'!
```

### 2.6. Security Issues

**[DEBT:SECURITY] Các vấn đề bảo mật nghiêm trọng**

1. **API Key hardcode (dòng 389):**
   ```php
   $content = file_get_contents('https://api.ipdata.co/'.$ip.'?api-key=8ff234d18e6e03cb9109fce555a66e23c56e7d542495cd55a7bf0013');
   ```

2. **Command Injection via `fs_chmod.php`:**
   ```php
   $file = $_REQUEST['file'];
   $command = "chmod 777 $file";  // $file KHÔNG được escape!
   ```

3. **Path Traversal trong `copy_files.php` và `move_files.php`:**
   ```php
   $path = $builder->getAbsolutePath($path, $repository);
   $builder->try_exec("cp $path $toDir");  // $path KHÔNG escape
   ```

4. **XSS qua theme parameter (tpl/index.tpl:45):**
   ```php
   <link href="css/theme-<?php echo $_REQUEST['theme']; ?>.css" rel="stylesheet">
   // $_REQUEST['theme'] không escape → XSS via CSS injection
   ```

5. **SSH password trong session (index.php:3103-3106):**
   ```php
   $_SESSION['SSH_PASSWORD'] = $password;  // Lưu password vào session
   ```

6. **`serialize()`/`unserialize()` cho session data (dòng 287, 301):**
   ```php
   $this->file_put_contents($filepath, serialize($data));
   $data = unserialize($this->file_get_contents($filepath));
   // unserialize data từ file → Object Injection nếu file bị tamper
   ```

7. **PHP_PATH hardcode cho Windows (dòng 32):**
   ```php
   define('PHP_PATH', 'D:/wamp/bin/php/php5.5.12/php.exe');  // Dead code, cần xoá
   ```

### 2.7. Code Duplication

**[DEBT:COUPLING] Code trùng lắp đáng kể**

1. **`git_log.php` vs `git_log_all.php`:** Gần giống nhau hoàn toàn, chỉ khác 1 dòng (có/không truyền file path vào `git log`)

2. **`git_diff.php` vs `git_diff_all.php`:** Tương tự, khác duy nhất scope (file vs repo)

3. **`git_commit.php` vs `git_commit_all.php`:** Logic gần giống, commit file cụ thể vs commit all

4. **`copy_files.php` vs `move_files.php`:** Gần như identical, chỉ khác `cp` vs `mv`

5. **SNR duplication:** `snr_search()` method (142 dòng trong Builder class) ĐỒNG THỜI có `search_and_replace.php` (656 dòng) — hai implementation riêng biệt cho search & replace

6. **`Builder::json_response()` vs global `json_response()`:** Hai function cùng tên, khác implementation (Builder version thiếu Content-Type header)

7. **`Builder::ensure_auth()` vs `plugin_command_user::ensure_auth()`:** Logic duplicate trong 2 class

8. **`getRepositoryOperations()` trong `plugin_fs` (base) vs `plugin_fs_local`:** Base class đã có logic git/svn operations, local plugin override lại gần giống

9. **Duplicate JS/CSS libraries:**
   - `lib/jquery.multiselect/` vs `lib/jquery.multiselect.*` (root level)
   - `js/ace/` vs `js/ace-min-noconflict/` (2 bản ACE editor)
   - `lib/vue/vue.min.js` vs `lib/vue@2.7.16/vue.min.js` vs `js/vue.js` vs `js/vue_dev.js`
   - `lib/bootstrap/` vs `css/bootstrap.*`
   - `lib/codemirror.zip` vs `lib/codemirror@5.65.13/`

### 2.8. Dead Code & Unused Files

**[DEBT:HIDDEN] Files/code không còn sử dụng**

| File / Code | Lý do cần xoá |
|-------------|---------------|
| `plugins/commands/foo.txt` | Chứa Vue component code không liên quan (từ dự án chart khác) |
| `xxx.php` | Không rõ mục đích, cần kiểm tra |
| `js/ace/` (thư mục) | Duplicate với `js/ace-min-noconflict/` |
| `lib/jquery.multiselect.*` (root level) | Duplicate với `lib/jquery.multiselect/` |
| `lib/vue/vue.min.js` + `js/vue.js` + `js/vue_dev.js` | Duplicate, chỉ cần `lib/vue@2.7.16/` |
| `lib/codemirror.zip` | File zip chưa extract, không dùng trực tiếp |
| `css/bootstrap.min.css*`, `css/bootstrap-theme.min.css*` | Duplicate với `lib/bootstrap/` |
| `css/sap.css`, `js/sap.js` | Kiểm tra xem còn dùng không |
| `js/backbone.*`, `js/underscore.min.js` | Kiểm tra xem còn dùng không |
| `js/binding.js`, `js/auth.js` | Kiểm tra xem còn dùng không |
| `js/chunk-vendors.161810c2.js` | Build artifact, không nên commit |
| Windows code block (dòng 29-35) | IS_WIN + D:/wamp path — dead code |
| `index.php` dòng 3582-3585 | Commented-out about page redirect |
| `index2.php` | Nếu không cần nginx special config, gộp logic |
| `standalones/text-diff.html` | Standalone tool, cân nhắc tách riêng |
| `plugins/fs/svn/svn.php` | Kiểm tra xem SVN còn dùng không |
| `bin/rust/rebuild-indexes/` | Kiểm tra xem có build/deploy không |

### 2.9. Design Issues khác

1. **`_t()` function ghi file trên production (dòng 37-71):** Nếu key chưa có trong lang file → ghi thêm vào file. Trên production, nhiều request đồng thời → race condition, file corruption.

2. **`get_ip_info()` gọi external API mỗi request (dòng 387-399):** Không cache, mỗi lần load trang đều gọi ipdata.co API → chậm, tốn quota, fail nếu API down.

3. **Session-based state quá nặng:** Open files, file paths, search state, SSH passwords... tất cả lưu trong `$_SESSION`. Khi session lớn → serialize/deserialize chậm.

4. **`set_time_limit(0)` global (dòng 3577):** Mọi request đều chạy vô hạn → risk resource exhaustion.

5. **`ob_implicit_flush(true)` global (dòng 3578):** Ảnh hưởng tất cả actions, không chỉ streaming actions.

6. **Magic numbers:** `8 * 3600` (token validity?), `86400` (cookie lifetime comment sai "10 days" nhưng thực tế là 1 day), chmod `777`, revision `maxcount = 10`.

---

## 3. Kiến Trúc Mục Tiêu

### Cấu trúc thư mục sau refactor

```
cloudpad9/
├── .env                        ← Environment config (không commit)
├── .env.sample                 ← Template
├── composer.json
├── index.php                   ← Thin bootstrap + router (~50 dòng)
├── index2.php                  ← Giữ nếu cần nginx config riêng
│
├── src/
│   ├── Core/
│   │   ├── Application.php     ← Bootstrap, dependency wiring
│   │   ├── Router.php          ← Action → Command dispatcher
│   │   ├── Request.php         ← Input validation & sanitization wrapper
│   │   ├── Response.php        ← json_ok(), json_fail(), download(), stream()
│   │   ├── Session.php         ← Session management (replace direct $_SESSION)
│   │   └── Config.php          ← Load .env + repositories.conf + users.conf
│   │
│   ├── Auth/
│   │   ├── AuthMiddleware.php  ← Check login, replace auth() + ensure_auth()
│   │   └── AuthService.php     ← Login/logout logic (from plugin_command_user)
│   │
│   ├── Repository/
│   │   ├── RepositoryManager.php  ← getRepositories(), getSettings(), permissions
│   │   └── RepositoryIndex.php    ← File indexing, search, cache management
│   │
│   ├── FileSystem/
│   │   ├── FileSystemInterface.php ← Contract cho FS plugins
│   │   ├── LocalFileSystem.php
│   │   ├── SftpFileSystem.php
│   │   └── FileOperations.php     ← read, write, chmod, rename wrapper
│   │
│   ├── Editor/
│   │   ├── EditorService.php    ← Open, close, save, clone, revert
│   │   ├── RevisionManager.php  ← File revision logic
│   │   ├── SyncService.php      ← File sync logic
│   │   └── ColorManager.php     ← Tab color management
│   │
│   ├── Git/
│   │   ├── GitService.php       ← execGitCommand, get_git_info, get_git_toplevel
│   │   ├── GitCommitCommand.php ← Unified commit (file + all)
│   │   ├── GitDiffCommand.php   ← Unified diff (file + all)
│   │   └── GitLogCommand.php    ← Unified log (file + all)
│   │
│   ├── Search/
│   │   ├── FileSearchService.php    ← file_live_search, searchFiles
│   │   └── SearchReplaceService.php ← SNR logic (unified)
│   │
│   ├── SSH/
│   │   ├── SSHService.php       ← SSH exec, connection management
│   │   └── CommandSafety.php    ← Command validation
│   │
│   ├── I18n/
│   │   └── Translator.php       ← _t() logic (without file-write on production)
│   │
│   └── Helpers/
│       ├── ProfilingHelper.php  ← Profiling (giữ nguyên, move file)
│       └── PathHelper.php       ← Path utilities
│
├── plugins/
│   ├── commands/               ← Giữ plugin commands, refactor dần
│   ├── fs/                     ← Giữ, implement FileSystemInterface
│   └── tabs/                   ← Giữ tab plugins
│
├── config/
│   ├── repositories.php        ← Rename từ repositories.conf.php
│   └── users.php               ← Rename từ users.conf.php
│
├── public/                     ← Static assets (JS/CSS/images/fonts)
│   ├── css/
│   ├── js/
│   ├── lib/                    ← Cleaned vendor libs (no duplicates)
│   ├── images/
│   └── fonts/
│
├── templates/                  ← Rename từ tpl/
│   └── index.tpl
│
└── storage/                    ← Rename từ tmp/
    └── {username}/
```

### Nguyên tắc thiết kế

1. **Single Responsibility:** Mỗi class làm 1 việc, mỗi file chứa 1 class
2. **Dependency Injection:** Không dùng `global $builder`, truyền dependencies qua constructor
3. **Unified Response:** Mọi API response đều qua `Response::json_ok()` / `Response::json_fail()`
4. **Input Validation:** Mọi `$_REQUEST` đều qua `Request` class với validation
5. **No direct `echo`/`exit`:** Trả về data, để router xử lý output

---

## 4. Kế Hoạch Thực Hiện Theo Phase

### Tổng quan timeline

| Phase | Mô tả | Ưu tiên | Ước lượng |
|-------|--------|---------|-----------|
| **0** | Dọn dẹp: xoá dead code, duplicate files | Critical | 1-2 giờ |
| **1** | Extract core infrastructure (Request, Response, Router) | Critical | 4-6 giờ |
| **2** | Migrate tất cả inline actions → plugin commands | High | 4-6 giờ |
| **3** | Tách Builder class thành các Service classes | High | 8-12 giờ |
| **4** | Security hardening | Critical | 3-4 giờ |
| **5** | Gộp duplicate plugin commands | Medium | 2-3 giờ |
| **6** | Error handling standardization | Medium | 3-4 giờ |
| **7** | Frontend cleanup (JS/CSS libs, builder.js) | Low | 4-6 giờ |

**Nguyên tắc:** Mỗi phase phải hoàn tất và test xong trước khi sang phase tiếp theo. Mỗi phase giữ backward compatibility — app vẫn chạy được sau mỗi phase.

---

## 5. Chi Tiết Từng Phase

### Phase 0 — Dọn Dẹp Dead Code & Duplicate Files

**Mục tiêu:** Giảm noise, loại bỏ confusion trước khi refactor.

#### Bước 0.1: Xoá files rác

```
XOÁ:
  plugins/commands/foo.txt         ← Vue component không liên quan
  lib/codemirror.zip               ← Zip file thô, không dùng
  js/ace/                          ← Duplicate ACE (giữ js/ace-min-noconflict/)
  css/bootstrap.min.css            ← Duplicate (giữ lib/bootstrap/)
  css/bootstrap.min.css.map
  css/bootstrap-theme.min.css      ← Duplicate
  css/bootstrap-theme.min.css.map
  xxx.php                          ← Kiểm tra trước, nếu không dùng thì xoá

KIỂM TRA RỒI XOÁ NẾU KHÔNG DÙNG:
  js/vue.js                        ← Duplicate? Kiểm tra version so với lib/vue@2.7.16/
  js/vue_dev.js                    ← Development build, không cần trên production
  lib/vue/vue.min.js               ← Version cũ? So sánh với lib/vue@2.7.16/
  js/backbone.min.js               ← Kiểm tra có file nào import không
  js/backbone.epoxy.min.js
  js/underscore.min.js
  js/binding.js
  js/auth.js
  js/chunk-vendors.161810c2.js
  css/sap.css + js/sap.js
  lib/jquery.multiselect.*         ← Root level duplicates (giữ lib/jquery.multiselect/)
```

**Cách kiểm tra file có dùng không:**
```bash
# Tìm references trong tất cả PHP/HTML/JS/TPL files
grep -rn "tên_file" --include="*.php" --include="*.tpl" --include="*.js" --include="*.html" .
```

#### Bước 0.2: Xoá dead code trong index.php

```php
// XOÁ: Windows code block (dòng 29-35)
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    define('IS_WIN', true);
    define('PHP_PATH', 'D:/wamp/bin/php/php5.5.12/php.exe');
} else {
    define('IS_WIN', false);
    define('PHP_PATH', '/usr/bin/php');
}
// THAY BẰNG:
define('PHP_PATH', $_ENV['PHP_PATH'] ?? '/usr/bin/php');

// XOÁ: Commented-out about page redirect (dòng 3582-3585)
// XOÁ: get_all_languages() — 110 dòng array nếu không dùng i18n
// XOÁ: get_ip_info(), get_ip_language(), get_ip_language_name() — external API dependency
```

#### Bước 0.3: Fix bugs rõ ràng

```php
// FIX 1: git_commit.php dòng 5 — sai biến
// TRƯỚC:
$message = $_REQUEST['path'] ?? '';
// SAU:
$message = $_REQUEST['message'] ?? 'Update file';

// FIX 2: copy_files.php — escape shell arguments
// TRƯỚC:
$builder->try_exec("cp $path $toDir");
// SAU:
$builder->try_exec("cp " . escapeshellarg($path) . " " . escapeshellarg($toDir));

// FIX 3: move_files.php — tương tự
// TRƯỚC:
$builder->try_exec("mv $path $toDir");
// SAU:
$builder->try_exec("mv " . escapeshellarg($path) . " " . escapeshellarg($toDir));

// FIX 4: fs_chmod.php — escape + validate
// TRƯỚC:
$command = "chmod 777 $file";
// SAU:
$mode = $_REQUEST['mode'] ?? '755';
if (!preg_match('/^[0-7]{3,4}$/', $mode)) { json_fail('Invalid mode'); }
$command = "chmod " . escapeshellarg($mode) . " " . escapeshellarg($file);

// FIX 5: tpl/index.tpl dòng 45 — XSS via theme
// TRƯỚC:
<link href="css/theme-<?php echo $_REQUEST['theme']; ?>.css" rel="stylesheet">
// SAU:
<?php $theme = preg_replace('/[^a-z0-9\-]/', '', $_REQUEST['theme'] ?? ''); ?>
<link href="css/theme-<?php echo htmlspecialchars($theme, ENT_QUOTES); ?>.css" rel="stylesheet">
```

---

### Phase 1 — Extract Core Infrastructure

**Mục tiêu:** Tạo 3 class nền tảng mà tất cả code khác sẽ dùng: `Request`, `Response`, `Router`.

#### Bước 1.1: Tạo `src/Core/Request.php`

```php
<?php
namespace CloudPad\Core;

class Request {
    /**
     * Lấy parameter từ $_REQUEST với validation cơ bản
     */
    public static function get(string $key, $default = null): mixed {
        return $_REQUEST[$key] ?? $default;
    }

    public static function require(string $key): string {
        $value = $_REQUEST[$key] ?? null;
        if ($value === null || $value === '') {
            Response::fail("Missing required parameter: $key");
        }
        return (string)$value;
    }

    public static function getString(string $key, string $default = ''): string {
        return trim((string)($_REQUEST[$key] ?? $default));
    }

    public static function getInt(string $key, int $default = 0): int {
        return (int)($_REQUEST[$key] ?? $default);
    }

    public static function getBool(string $key, bool $default = false): bool {
        $val = $_REQUEST[$key] ?? $default;
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function isAjax(): bool {
        return isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == 1;
    }
}
```

#### Bước 1.2: Tạo `src/Core/Response.php`

```php
<?php
namespace CloudPad\Core;

class Response {
    /**
     * Trả JSON response thành công — CÁCH DUY NHẤT để trả success response
     */
    public static function ok($payload = null, string $message = null): never {
        $result = [];
        if (is_array($payload)) {
            $result = array_merge($result, $payload);
        } elseif ($payload !== null) {
            $result['data'] = $payload;
        }
        $result['success'] = true;
        if ($message !== null) {
            $result['message'] = $message;
        }
        self::json($result);
    }

    /**
     * Trả JSON response thất bại — CÁCH DUY NHẤT để trả error response
     */
    public static function fail(string $message, array $extra = []): never {
        $payload = array_merge([
            'success' => false,
            'message' => $message
        ], $extra);
        self::json($payload);
    }

    /**
     * Trả raw JSON — internal use
     */
    public static function json(array $data): never {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Download file
     */
    public static function download(string $filepath, string $filename = null): never {
        // ... download logic
        exit;
    }
}
```

#### Bước 1.3: Update global functions

```php
// Trong index.php, redirect global functions sang Response class:
function json_ok($payload = null, $message = null) {
    \CloudPad\Core\Response::ok($payload, $message);
}
function json_fail($message, $extra = []) {
    \CloudPad\Core\Response::fail($message, $extra);
}
function json_response($arr) {
    \CloudPad\Core\Response::json($arr);
}
function json_success($data = []) {
    \CloudPad\Core\Response::ok($data);
}
```

Sau đó dần thay thế các chỗ gọi `echo json_encode()` trực tiếp bằng `Response::ok()` / `Response::fail()`.

#### Bước 1.4: Tạo `src/Core/Router.php`

```php
<?php
namespace CloudPad\Core;

class Router {
    private $builder;

    public function __construct($builder) {
        $this->builder = $builder;
    }

    public function dispatch(string $action): void {
        // 1. Plugin commands (giữ nguyên logic hiện tại)
        if ($this->builder->is_plugin_command($action, $handler, $methodname)) {
            if (is_object($handler)) {
                $handler->$methodname($this->builder);
            } else {
                $handler($this->builder);
            }
            return;
        }

        // 2. Fallback — action chưa migrate (sẽ giảm dần qua Phase 2)
        $this->dispatchLegacy($action);
    }

    private function dispatchLegacy(string $action): void {
        // Chuyển if/else chain hiện tại vào đây
        // Mỗi khi migrate 1 action sang plugin command → xoá case tương ứng ở đây
    }
}
```

---

### Phase 2 — Migrate Inline Actions → Plugin Commands

**Mục tiêu:** Chuyển TẤT CẢ ~25 inline actions trong `index.php` thành plugin commands.

#### Danh sách actions cần migrate

| Action | File đích | Độ phức tạp |
|--------|-----------|-------------|
| `download-user-file` | `plugins/commands/download_user_file.php` | Low |
| `delete-user-file` | `plugins/commands/delete_user_file.php` | Low |
| `open-file-by-name` | `plugins/commands/open_file_by_name.php` | Medium |
| `editor-close-all` | `plugins/commands/editor_close_all.php` | Low |
| `editor-clear-recents` | `plugins/commands/editor_clear_recents.php` | Low |
| `file-live-search` | `plugins/commands/file_live_search.php` | Low |
| `search-files` | `plugins/commands/search_files.php` | Low |
| `get-directory-structure` | `plugins/commands/get_directory_structure.php` | Low |
| `get-directory-children` | `plugins/commands/get_directory_children.php` | Medium |
| `new-temp-file` | `plugins/commands/new_temp_file.php` | Low |
| `set-color` | `plugins/commands/set_color.php` | Low |
| `clone-file` | `plugins/commands/clone_file.php` | Medium |
| `revert-file` | `plugins/commands/revert_file.php` | Low |
| `recover-file` | `plugins/commands/recover_file.php` | Low |
| `reload-file` | `plugins/commands/reload_file.php` | Low |
| `sync-file` | `plugins/commands/sync_file.php` | Medium |
| `revert-sync-file` | `plugins/commands/revert_sync_file.php` | Low |
| `rebuild-sub-indexes` | `plugins/commands/rebuild_sub_indexes.php` | Low |
| `close-file` | `plugins/commands/close_file.php` | Low |
| `upload-file-x` | Gộp với `plugins/commands/upload_file.php` | Low |
| `save-current-file` | `plugins/commands/save_current_file.php` | Medium |
| `rebuild-filepaths-indexes` | `plugins/commands/rebuild_filepaths_indexes.php` | Medium |
| `snr-search` | Gộp với `plugins/commands/search_and_replace.php` | High |
| `open-inline-file` | `plugins/commands/open_inline_file.php` | Low |
| `save-inline-file` | `plugins/commands/save_inline_file.php` | Low |
| `diff` | `plugins/commands/diff.php` | Low |

#### Template cho mỗi plugin command mới

```php
<?php
// plugins/commands/reload_file.php
function reload_file($builder) {
    $filename   = \CloudPad\Core\Request::require('filename');
    $repository = \CloudPad\Core\Request::require('repository');

    // Validate
    if (!$builder->hasRepositoryPermission($repository)) {
        \CloudPad\Core\Response::fail('Permission denied');
    }

    // Execute
    $builder->reload_file($filename, $repository);
}
```

#### Sau khi migrate xong Phase 2

`index.php` action routing section sẽ rút gọn còn:

```php
// Dispatch action
$router = new \CloudPad\Core\Router($builder);
$router->dispatch($action);

// Render UI nếu cần
if ($renderable) {
    include __DIR__ . '/tpl/index.tpl';
}
```

---

### Phase 3 — Tách Builder Class Thành Service Classes

**Mục tiêu:** Tách ~120 methods của Builder thành các service classes có trách nhiệm rõ ràng.

#### Mapping methods → Service classes

**`src/Editor/EditorService.php`** (từ Builder):
- `open_file_by_name()`
- `close_file()`
- `new_temp_file()`
- `clone_file()`
- `save_current_file()`
- `onBeforeSavingFile()`
- `convert_tabs_to_whitespaces()`
- `trim_trailing_whitespaces()`
- `trim_trailing_commas()`
- `is_same_content()`
- `getOpenFiles()`, `getEditorOpenFiles()`
- `setFilePath()`, `addToRepositoryFilePaths()`
- `standalone_editor()`

**`src/Editor/RevisionManager.php`** (từ Builder):
- `save_file_revision()`
- `create_temp_revision()`
- `get_revision_count()`
- `get_revision_prefix()`
- `get_latest_revision_content()`
- `get_latest_temp_revision_content()`
- `revert_file()`
- `recover_file()`
- `reload_file()`

**`src/Editor/SyncService.php`** (từ Builder):
- `sync_file()`
- `revert_sync_file()`
- `get_sync_dest()`

**`src/Editor/ColorManager.php`** (từ Builder):
- `set_color()`
- `get_color()`
- `get_color_file()`

**`src/Repository/RepositoryManager.php`** (từ Builder):
- `getRepositories()`
- `getRepositoriesFromFile()`
- `getRepositorySettings()`
- `getRepositoryHandler()`
- `hasRepositoryPermission()`
- `getRepositoryOperations()`
- `getFileRepository()`
- `getRepositoryWisePath()`

**`src/Repository/RepositoryIndex.php`** (từ Builder):
- `getRepositoryFilePaths()`
- `getProjectFilePaths()`
- `getRepositoryCacheFile()`
- `rebuildIndexesUsingRust()`
- `rebuild_sub_indexes()`

**`src/Search/FileSearchService.php`** (từ Builder):
- `searchFiles()`
- `searchForFile()`
- `searchForFiles()`
- `searchForFilesInArray()`
- `file_live_search()`
- `search_files()`

**`src/Search/SearchReplaceService.php`** (từ Builder + search_and_replace.php):
- `snr_search()`
- `snr_get_regex()`
- `snr_get_pos()`
- `snr_get_pos_with_regex()`
- `snr_replace()`
- `snr_revert()`
- Logic từ `search_and_replace.php` (hợp nhất 2 implementation)

**`src/FileSystem/FileOperations.php`** (từ Builder):
- `file_exists()`
- `file_get_contents()`
- `file_put_contents()`
- `rename()`
- `try_chmod()`
- `try_exec()`
- `rsearch()`
- `glob()`
- `is_empty_dir()`
- `getLocalizedPath()`
- `getRelPath()`
- `getAbsolutePath()`
- `getAbsoluteFilePath()`

**`src/Git/GitService.php`** (từ Builder):
- `execGitCommand()`
- `get_git_info()`
- `get_git_toplevel()`
- `compute_relpath()`

**`src/SSH/SSHService.php`** (từ Builder):
- `ssh_exec()`
- `ssh_exec_2()`
- `private_ssh_exec()`
- `ssh_getAugmentedOutput()`
- `ssh_ensure_safe_command()`
- `execute_linux()`
- `exec()`

**`src/Auth/AuthService.php`** (từ Builder + plugin_command_user):
- `isUserLoggedIn()`
- `auth()`
- `ensure_auth()`
- `login()`
- `logout()`
- `getCurrentUser()`
- `getCurrentUsername()`
- `getUsers()`
- `hasPermission()`
- `getAvailablePluginsOfCurrentUser()`
- `getEnabledPluginsOfCurrentUser()`
- `getCurrentUserPermission()`

**`src/Core/Session.php`** (từ Builder):
- `serializeUserSessionData()`
- `reloadUserSessionData()`
- `getUserSessionId()`
- `getUserDataDir()`
- `getUserUploadDir()`
- `getUserRepositoryDir()`
- `getUserTempDir()`
- `getUserRevisionDir()`
- `getUserTempRevisionDir()`

#### Cách thực hiện (gradual extraction)

1. Tạo service class mới
2. Copy methods vào class mới
3. Trong Builder class, delegate sang service: `$this->editorService->open_file_by_name()`
4. Test
5. Sau khi tất cả callers đã dùng service trực tiếp → xoá delegation method trong Builder

---

### Phase 4 — Security Hardening

**Mục tiêu:** Fix tất cả security issues đã phát hiện.

#### 4.1: Di chuyển API key vào .env

```php
// TRƯỚC (index.php:389):
file_get_contents('https://api.ipdata.co/'.$ip.'?api-key=8ff234d18e6e03cb9109fce555a66e23c56e7d542495cd55a7bf0013');

// SAU:
$apiKey = $_ENV['IPDATA_API_KEY'] ?? '';
if (!empty($apiKey)) {
    file_get_contents("https://api.ipdata.co/{$ip}?api-key={$apiKey}");
}

// .env.sample thêm:
IPDATA_API_KEY=your_api_key_here
```

#### 4.2: Thêm input validation cho tất cả plugin commands

Mỗi plugin command phải:
1. Dùng `Request::require()` hoặc `Request::getString()` thay vì `$_REQUEST` trực tiếp
2. Validate format (regex check cho paths, filenames)
3. Check `hasRepositoryPermission()` cho mọi action liên quan repository

#### 4.3: Escape tất cả shell arguments

Tìm mọi chỗ gọi `try_exec()`, `exec()`, `ssh_exec()` và đảm bảo arguments được `escapeshellarg()`.

#### 4.4: Thay `serialize()`/`unserialize()` bằng JSON

```php
// TRƯỚC:
$this->file_put_contents($filepath, serialize($data));
$data = unserialize($this->file_get_contents($filepath));

// SAU:
$this->file_put_contents($filepath, json_encode($data));
$data = json_decode($this->file_get_contents($filepath), true);
```

#### 4.5: Không lưu SSH password trong session

```php
// XOÁ:
$_SESSION['SSH_PASSWORD'] = $password;
// Yêu cầu nhập lại mỗi lần, hoặc dùng key-based auth only
```

#### 4.6: Path validation

Tạo helper function kiểm tra path không chứa `..`, không escape khỏi repository root:

```php
function validatePath(string $path, string $repositoryRoot): bool {
    $realPath = realpath($repositoryRoot . '/' . $path);
    return $realPath && strpos($realPath, realpath($repositoryRoot)) === 0;
}
```

---

### Phase 5 — Gộp Duplicate Plugin Commands

**Mục tiêu:** Giảm code duplication trong plugin commands.

#### 5.1: Gộp git_log + git_log_all → git_log.php

```php
<?php
function git_log($builder) {
    $repository = Request::getString('repository');
    $path       = Request::getString('path');
    $scope      = Request::getString('scope', 'file'); // 'file' hoặc 'all'

    $filepath = $builder->getAbsoluteFilePath($path, $repository);
    if (empty($filepath)) {
        Response::fail('Source file not found.');
    }

    $info = $builder->get_git_info($filepath);
    if (empty($info) || empty($info['ok'])) {
        Response::fail($info['message'] ?? 'Not a valid git repository root.');
    }

    $toplevel = $info['toplevel'];

    if ($scope === 'all') {
        $cmd = '--no-pager log --stat -n 20';
    } elseif (is_dir($filepath)) {
        $cmd = '--no-pager log --stat -n 20 -- ' . escapeshellarg($filepath);
    } else {
        $cmd = '--no-pager log -p -- ' . escapeshellarg($filepath);
    }

    $out = '';
    $builder->execGitCommand($toplevel, $cmd, $out);

    Response::ok(['output' => (string)$out]);
}
```

Xoá: `git_log_all.php`
Frontend: Thay `action=git_log_all` → `action=git_log&scope=all`

#### 5.2: Gộp git_diff + git_diff_all → git_diff.php (tương tự)

Thêm parameter `scope=all|file`. Xoá `git_diff_all.php`.

#### 5.3: Gộp git_commit + git_commit_all → git_commit.php (tương tự)

Thêm parameter `scope=all|file`. Xoá `git_commit_all.php`.

#### 5.4: Gộp copy_files + move_files

```php
<?php
// plugins/commands/file_operations.php
class plugin_command_file_operations {
    function copy($builder) { $this->transfer($builder, 'cp'); }
    function move($builder) { $this->transfer($builder, 'mv'); }

    private function transfer($builder, $command) {
        $paths = explode(',', Request::require('paths'));
        $repository = Request::require('repository');
        $toPath = Request::require('to');

        $toDir = $builder->getAbsolutePath($toPath, $repository);
        if (!is_dir($toDir)) {
            Response::fail('Destination directory not found.');
        }

        $toDir = rtrim($toDir, '/') . '/';
        $errors = [];

        foreach ($paths as $p) {
            $path = $builder->getAbsolutePath(trim($p), $repository);
            if (empty($path) || !file_exists($path)) {
                $errors[] = "Not found: $p";
                continue;
            }
            $builder->try_exec("$command " . escapeshellarg($path) . " " . escapeshellarg($toDir));
        }

        if (!empty($errors)) {
            Response::fail(implode('; ', $errors));
        }
        Response::ok();
    }
}
```

Xoá: `copy_files.php`, `move_files.php`
Frontend: Thay `action=copy_files` → `action=file_operations/copy`, `action=move_files` → `action=file_operations/move`

#### 5.5: Xoá SNR duplication

Quyết định giữ 1 implementation:
- **Giữ `search_and_replace.php`** (mới hơn, 656 dòng, JSON API response)
- **Xoá `snr_search()` method** trong Builder class (legacy streaming HTML output)
- Update frontend SNR tab để gọi `search_and_replace` command thay vì `snr-search` action

---

### Phase 6 — Error Handling Standardization

**Mục tiêu:** Mọi error đều được handle nhất quán.

#### 6.1: Tạo Exception classes

```php
<?php
namespace CloudPad\Core\Exceptions;

class NotFoundException extends \RuntimeException {}
class PermissionDeniedException extends \RuntimeException {}
class ValidationException extends \RuntimeException {}
class FileSystemException extends \RuntimeException {}
```

#### 6.2: Error handler trong Router

```php
try {
    $router->dispatch($action);
} catch (NotFoundException $e) {
    Response::fail($e->getMessage(), ['code' => 404]);
} catch (PermissionDeniedException $e) {
    Response::fail($e->getMessage(), ['code' => 403]);
} catch (ValidationException $e) {
    Response::fail($e->getMessage(), ['code' => 422]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    Response::fail('Internal error', ['code' => 500]);
}
```

#### 6.3: Plugin commands throw exceptions thay vì echo/exit

```php
// TRƯỚC:
if (empty($filepath)) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    return;
}

// SAU:
if (empty($filepath)) {
    throw new NotFoundException('File not found');
}
```

---

### Phase 7 — Frontend Cleanup

**Mục tiêu:** Giảm JS/CSS bloat, organize assets.

#### 7.1: Dọn duplicate libraries

```
GIỮ:
  lib/vue@2.7.16/vue.min.js       ← Vue 2 chính
  lib/axios@1.6.7/axios.min.js    ← Axios chính
  lib/bootstrap/                   ← Bootstrap chính
  lib/jquery.multiselect/          ← Multiselect chính
  js/ace-min-noconflict/           ← ACE editor chính
  lib/codemirror@5.65.13/          ← CodeMirror chính

XOÁ:
  lib/vue/vue.min.js
  js/vue.js, js/vue_dev.js
  lib/bootstrap-vue/               ← Kiểm tra xem dùng không
  lib/jquery.multiselect.* (root)
  js/ace/
  lib/codemirror.zip
  lib/color/                       ← Kiểm tra
```

#### 7.2: Tổ chức lại thư mục assets

```
public/
  css/
    builder.css
    builder-responsive.css
    theme-dark.css
  js/
    builder.js
    ace-min-noconflict/
  lib/
    vue@2.7.16/
    axios@1.6.7/
    bootstrap/
    jquery-ui/
    jquery.multiselect/
    codemirror@5.65.13/
    xterm@5.3.0/
    ...
  images/
  fonts/
```

#### 7.3: Tách `builder.js` (future)

File `builder.js` (2935 dòng) chứa tất cả frontend logic. Tách thành modules là cải tiến dài hạn, không bắt buộc trong đợt refactor này.

---

## 6. Files Cần Xoá / Gộp

### Xoá ngay (Phase 0)

| File | Lý do |
|------|-------|
| `plugins/commands/foo.txt` | File rác từ dự án khác |
| `lib/codemirror.zip` | Zip file thô |
| `js/ace/` (cả thư mục) | Duplicate |
| `css/bootstrap.min.css` | Duplicate |
| `css/bootstrap.min.css.map` | Duplicate |
| `css/bootstrap-theme.min.css` | Duplicate |
| `css/bootstrap-theme.min.css.map` | Duplicate |

### Xoá sau khi kiểm tra (Phase 0)

| File | Kiểm tra gì |
|------|-------------|
| `xxx.php` | `grep -rn "xxx.php"` trong codebase |
| `js/vue.js`, `js/vue_dev.js` | References trong .tpl và .html files |
| `lib/vue/vue.min.js` | References |
| `js/backbone.min.js`, `js/backbone.epoxy.min.js` | References |
| `js/underscore.min.js` | References |
| `js/binding.js`, `js/auth.js` | References |
| `js/chunk-vendors.161810c2.js` | References |
| `css/sap.css`, `js/sap.js` | References |
| `lib/jquery.multiselect.*` (root level) | So sánh với `lib/jquery.multiselect/` |
| `lib/bootstrap-vue/` | References |
| `plugins/fs/svn/svn.php` | Có dùng SVN repos không |
| `standalones/text-diff.html` | Có deploy riêng không |

### Gộp (Phase 5)

| Gộp từ | Thành | Xoá file |
|--------|-------|----------|
| `git_log.php` + `git_log_all.php` | `git_log.php` (thêm param scope) | `git_log_all.php` |
| `git_diff.php` + `git_diff_all.php` | `git_diff.php` (thêm param scope) | `git_diff_all.php` |
| `git_commit.php` + `git_commit_all.php` | `git_commit.php` (thêm param scope) | `git_commit_all.php` |
| `copy_files.php` + `move_files.php` | `file_operations.php` (class) | cả 2 file cũ |
| SNR trong Builder + `search_and_replace.php` | Giữ `search_and_replace.php` | Xoá methods trong Builder |

---

## 7. Checklist Kiểm Tra Sau Refactor

### Sau mỗi Phase

- [ ] App khởi động được, login/logout hoạt động
- [ ] Mở file từ repository hoạt động
- [ ] Save file hoạt động
- [ ] Search & Replace hoạt động
- [ ] File tree navigation hoạt động
- [ ] Git operations (status, diff, commit, log) hoạt động
- [ ] Upload/download file hoạt động
- [ ] SSH terminal hoạt động (nếu có)
- [ ] Quick access pin/unpin hoạt động
- [ ] Tất cả plugin tabs render đúng

### Regression Test Cases

1. **Login flow:** Đăng nhập → redirect → session persist
2. **Open file:** Chọn repo → search file → mở → content hiển thị
3. **Save file:** Edit content → Ctrl+S → reload → content đúng
4. **SNR:** Search keyword → hiện results → replace → verify
5. **Git commit:** Edit file → git diff → git commit → git log
6. **File operations:** New file → Rename → Clone → Delete
7. **Directory ops:** New directory → Rename directory → Upload file vào
8. **Rebuild indexes:** Rebuild → search lại → kết quả cập nhật
9. **Multi-user:** Login user khác → repo permissions đúng
10. **Error cases:** Mở file không tồn tại → thông báo lỗi rõ ràng

---

## Appendix: Quy Ước Code Cho Agent Thực Hiện

1. **PHP version:** Giữ PHP 8.0+ compatible
2. **Namespace:** `CloudPad\` prefix cho tất cả class mới trong `src/`
3. **Autoload:** Dùng PSR-4 autoload qua composer
4. **Response:** Luôn dùng `Response::ok()` / `Response::fail()`, KHÔNG bao giờ `echo json_encode()`
5. **Input:** Luôn dùng `Request::require()` / `Request::getString()`, KHÔNG truy cập `$_REQUEST` trực tiếp
6. **Shell commands:** Luôn `escapeshellarg()` mọi user input trước khi truyền vào shell
7. **Error:** Throw exception, để Router catch — KHÔNG `exit()` trong business logic
8. **Backward compatibility:** Giữ URL structure (`index.php?action=xxx`) không đổi
9. **Testing:** Sau mỗi file được refactor, kiểm tra chức năng liên quan ngay
10. **Git commits:** Một commit cho mỗi logical change, message rõ ràng
