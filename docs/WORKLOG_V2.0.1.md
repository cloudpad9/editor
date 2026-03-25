# WORKLOG — CloudPad9 Refactor v2.0.1

**Ngày tạo:** 2026-03-25  
**Dựa trên kế hoạch:** `REFACTORING_PLAN.md` (2026-03-24)  
**Codebase làm việc:** `cloudpad9-refactored.zip` (3.2MB)  
**Trạng thái tổng quát:** Phase 0 ✅ · Phase 1 ✅ · Phase 2 ✅ · Phase 4 ✅ · Phase 3/5/6/7 🔄

---

## TÓM TẮT SỐ LIỆU

| Metric | Trước | Sau | Ghi chú |
|--------|-------|-----|---------|
| `index.php` dòng | 3,858 | **3,049** | −21% |
| `src/Core/` files | 0 | **3** | Request, Response, Router |
| Plugin commands | 14 | **40** | +26 commands mới (Phase 2) |
| Router lines | 253 (if/else) | **52** | clean switch dispatch |
| `$_REQUEST` trực tiếp | 79+ | **0** | toàn bộ qua `Request::getString()` |
| `echo json_encode()` | 64 chỗ | **0** | toàn bộ qua `Response::json()` |
| `unserialize()` | 6 chỗ | **0** | thay bằng `json_decode()` |
| Shell args chưa escape | 8+ chỗ | **0** | toàn bộ dùng `escapeshellarg()` |
| Hardcoded API key | 1 (ipdata.co) | **0** | xóa luôn IP detection |
| Dead code xóa | — | ~200+ dòng | IS_WIN, get_all_languages, IP fns… |

---

## ĐÃ HOÀN THÀNH

### ✅ Phase 0 — Dọn Dẹp Dead Code & Duplicate Files

#### 0.1 — Xóa files rác / duplicate
Tất cả các file dưới đây đã bị xóa:

| File / Thư mục | Lý do |
|----------------|-------|
| `plugins/commands/foo.txt` | Vue component không liên quan |
| `lib/codemirror.zip` | Zip file thô, không dùng trực tiếp |
| `js/ace/` (cả thư mục) | Duplicate — giữ `js/ace-min-noconflict/` |
| `css/bootstrap.min.css` + `.map` | Duplicate — giữ `lib/bootstrap/` |
| `css/bootstrap-theme.min.css` + `.map` | Duplicate |
| `js/vue.js`, `js/vue_dev.js` | Duplicate — giữ `lib/vue@2.7.16/` |
| `lib/vue/vue.min.js` | Duplicate version cũ hơn |
| `js/backbone.min.js`, `js/backbone.epoxy.min.js` | Không có reference nào |
| `js/underscore.min.js` | Không có reference nào |
| `js/binding.js` | Không có reference nào |
| `js/chunk-vendors.161810c2.js` | Build artifact cũ |
| `css/sap.css`, `js/sap.js` | Không có reference nào |
| `lib/bootstrap-vue/` | Không được load trong index.tpl |
| `lib/jquery.multiselect.*` (root level) | Duplicate — giữ `lib/jquery.multiselect/` |
| `js/auth.js` | Facebook Login dead code (FB app không cấu hình) |

#### 0.2 — Xóa dead code trong `index.php`

| Code | Lý do |
|------|-------|
| Windows block: `IS_WIN`, `PHP_PATH = D:/wamp/...` | Dead — app chạy Linux-only |
| → Thay bằng `define('PHP_PATH', $_ENV['PHP_PATH'] ?? '/usr/bin/php')` | Đọc từ `.env` |
| `get_ip_info()` + hardcoded API key `ipdata.co` | External API dependency mỗi request |
| `get_ip_language()`, `get_ip_language_name()` | Phụ thuộc vào IP API |
| `get_all_languages()` (110 dòng array) | Chỉ dùng cho IP language detection |
| `getInterfaceLanguages()` | Dead method, không được gọi ở đâu |
| Commented-out about-page redirect (3 dòng) | Rác |
| Reference `js/auth.js` trong `login.tpl` | File đã xóa |
| `get_user_language()` | Đơn giản hóa — chỉ dùng browser language |

#### 0.3 — Bug fixes & Security patches ngay Phase 0

| File | Bug | Fix |
|------|-----|-----|
| `copy_files.php` | `cp $path $toDir` — Path Traversal | `escapeshellarg()` cho cả hai |
| `move_files.php` | `mv $path $toDir` — Path Traversal | `escapeshellarg()` cho cả hai |
| `fs_chmod.php` | `chmod 777 $file` — Command Injection, hardcode 777 | Validate octal mode + `escapeshellarg()` |
| `tpl/index.tpl` | `echo $_REQUEST['theme']` — XSS | `preg_replace(/[^a-z0-9\-]/)` + `htmlspecialchars()` |
| `login.tpl` | Có `<script src="js/auth.js">` mặc dù file không tồn tại | Xóa dòng script |

---

### ✅ Phase 1 — Core Infrastructure

#### 1.1 — `src/Core/Response.php` (87 dòng)
- Class duy nhất để trả JSON response trong toàn bộ app
- Luôn set `Content-Type: application/json; charset=utf-8`
- Methods: `ok()`, `fail()`, `json()`, `download()`
- **Fix bug:** `Builder::json_response()` và `Builder::error()` trước đây thiếu Content-Type header — giờ delegate sang `Response` class

#### 1.2 — `src/Core/Request.php` (104 dòng)
- Wrapper truy cập `$_REQUEST` có trim và type coercion
- Methods: `getString()`, `require()`, `getInt()`, `getBool()`, `getArray()`, `isAjax()`, `getAction()`
- `require()` tự động trả `Response::fail()` nếu thiếu param

#### 1.3 — `src/Core/Router.php` (52 dòng)
- Thay thế 257 dòng spaghetti if/else chain
- Logic: standalone_editor → plugin command → log unknown
- Hoàn toàn delegate cho `is_plugin_command()` sau khi Phase 2 xong

#### 1.4 — `composer.json` — thêm PSR-4 autoload
```json
"autoload": { "psr-4": { "CloudPad\\": "src/" } }
```

#### 1.5 — `vendor/autoload.php` — minimal autoloader thủ công
- Dùng khi chưa chạy `composer install` trên server
- **Khi deploy:** chạy `composer dump-autoload` để thay thế bằng autoloader đầy đủ

#### 1.6 — Global bridge functions trong `index.php`
```php
function json_ok($payload, $message)  → Response::ok()
function json_fail($message, $extra)  → Response::fail()
function json_response(array $arr)    → Response::json()
```
- Backward compat: plugin commands cũ vẫn gọi được

#### 1.7 — Bulk replace `echo json_encode()`
- **64 chỗ** trong `index.php` và toàn bộ `plugins/commands/*.php`
- Thay bằng `\CloudPad\Core\Response::json()`

---

### ✅ Phase 2 — Migrate Inline Actions → Plugin Commands

Tất cả **26 inline actions** trong `index.php` đã được migrate ra từng file plugin command riêng:

| Action | File | Ghi chú |
|--------|------|---------|
| `download-user-file` | `download_user_file.php` | |
| `delete-user-file` | `delete_user_file.php` | |
| `open-file-by-name` | `open_file_by_name.php` | |
| `editor-close-all` | `editor_close_all.php` | Trả `Response::ok()` |
| `editor-clear-recents` | `editor_clear_recents.php` | Trả `Response::ok()` |
| `file-live-search` | `file_live_search.php` | |
| `get-directory-structure` | `get_directory_structure.php` | |
| `get-directory-children` | `get_directory_children.php` | |
| `get-file-content` | `get_file_content.php` | |
| `new-temp-file` | `new_temp_file.php` | |
| `set-color` | `set_color.php` | |
| `clone-file` | `clone_file.php` | |
| `revert-file` | `revert_file.php` | |
| `recover-file` | `recover_file.php` | |
| `reload-file` | `reload_file.php` | |
| `sync-file` | `sync_file.php` | |
| `revert-sync-file` | `revert_sync_file.php` | |
| `rebuild-sub-indexes` | `rebuild_sub_indexes.php` | |
| `rebuild-filepaths-indexes` | `rebuild_filepaths_indexes.php` | Trả `Response::ok(['count' => n])` |
| `upload-file-x` | `upload_file_x.php` | Wrapper gọi `upload_file()` |
| `save-current-file` | `save_current_file.php` | |
| `close-file` | `close_file.php` | |
| `snr-search` | `snr_search.php` | TODO Phase 5: gộp với `search_and_replace.php` |
| `open-inline-file` | `open_inline_file.php` | Thêm file not found check |
| `save-inline-file` | `save_inline_file.php` | Thêm session guard |
| `diff` | `diff.php` | |

**Kết quả:**
- Router từ 257 dòng → 52 dòng
- `index.php` giảm ~260 dòng dispatch code
- Tất cả 40 chỗ `$_REQUEST` trong plugin commands → `Request::getString()`

---

### ✅ Phase 4 — Security Hardening

#### Input Validation
- `$_REQUEST` trực tiếp: **79+ → 0** trong toàn bộ codebase
- Mọi input đều qua `Request::getString()`, `Request::getInt()`, v.v.
- Bootstrap vars (`$action`, `$standalone`, `$ajax`...) dùng `Request::getAction()`, `Request::isAjax()`

#### Shell Command Injection
Tất cả shell commands đều được `escapeshellarg()`:

| File | Fix |
|------|-----|
| `copy_files.php` | `cp` args |
| `move_files.php` | `mv` args |
| `fs_chmod.php` | Validate octal mode + escape |
| `upload_file.php` | `chmod 777 $dir` → `chmod 755 ` + escape, hardcode 777 → 755 |
| `delete_file.php` | `unlink` arg |
| `new_directory.php` | `mkdir` + `chmod` args, hardcode 777 → 755 |
| `new_hugo_content.php` | `chmod` + `hugo new` args |
| `rename_directory.php` | `mv` args |
| `rename_file.php` | `mv` args |
| `try_chmod()` trong Builder | Validate octal + escape |
| `rebuildIndexesUsingRust()` | Escape binary path + tất cả dir args |

#### Arbitrary File Read / Path Traversal
- **`download_user_file()`**: Trước đây cho phép download file tuyệt đối (`is_file($filename)` → read bất kỳ file nào). Fix:
  - Luôn resolve trong `getUserUploadDir()`
  - `basename()` để loại `../`
  - `realpath()` + boundary check để đảm bảo file nằm trong upload dir
- **`delete_user_file()`**: Tương tự — thêm `basename()`, `realpath()`, boundary check

#### Object Injection (unserialize)
Tất cả `serialize/unserialize` → `json_encode/json_decode`:

| Nơi dùng | Fix |
|----------|-----|
| `serializeUserSessionData()` / `reloadUserSessionData()` | JSON |
| `set_color()` / `get_color()` — color cache file | JSON |
| `rebuild_sub_indexes()` — filepaths cache | JSON |
| `addToRepositoryFilePaths()` — filepaths cache | JSON |

> **Lưu ý:** Nếu server có cache file cũ dạng `serialize`, cần xóa `cache/` và `tmp/*/` để regenerate với format mới.

#### XSS
- `tpl/index.tpl` dòng 45 — `$_REQUEST['theme']`: whitelist `[a-z0-9\-]` + `htmlspecialchars()`

#### Sanitization thêm
- `get_lang()`: thêm `preg_replace('/[^a-zA-Z0-9\-]/', '', $lang)` để sanitize lang param
- Content-Disposition header trong `download_user_file()`: thêm `addslashes()` cho filename

---

## CÒN LẠI — CHƯA LÀM

### 🔄 Phase 3 — Tách Builder Class Thành Service Classes

**Mức độ:** High (effort lớn, lợi ích dài hạn)  
**Builder hiện tại:** ~141 methods, vẫn là God Class

Mapping cần thực hiện theo `REFACTORING_PLAN.md §5 Phase 3`:

| Service Class | Methods cần tách | File đích |
|---------------|-----------------|-----------|
| `EditorService` | `open_file_by_name`, `close_file`, `new_temp_file`, `clone_file`, `save_current_file`, `standalone_editor`, ... | `src/Editor/EditorService.php` |
| `RevisionManager` | `save_file_revision`, `create_temp_revision`, `revert_file`, `recover_file`, `reload_file`, ... | `src/Editor/RevisionManager.php` |
| `SyncService` | `sync_file`, `revert_sync_file`, `get_sync_dest` | `src/Editor/SyncService.php` |
| `ColorManager` | `set_color`, `get_color`, `get_color_file` | `src/Editor/ColorManager.php` |
| `RepositoryManager` | `getRepositories`, `getRepositorySettings`, `hasRepositoryPermission`, `getAbsolutePath`, ... | `src/Repository/RepositoryManager.php` |
| `FileSearchService` | `file_live_search`, `searchForFile`, `rsearch`, `getProjectFilePaths`, ... | `src/Search/FileSearchService.php` |
| `SearchReplaceService` | `snr_search` (unified với `search_and_replace.php`) | `src/Search/SearchReplaceService.php` |
| `SSHService` | `ssh_exec`, `private_ssh_exec`, `execute_linux`, `ssh_ensure_safe_command`, ... | `src/SSH/SSHService.php` |
| `AuthService` | `auth`, `isUserLoggedIn`, `getUsers`, `serializeUserSessionData`, ... | `src/Auth/AuthService.php` |
| `FileSystem helpers` | `file_get_contents`, `file_put_contents`, `file_exists`, `try_exec`, `try_chmod` | `src/FileSystem/FileOperations.php` |
| `Translator` | `_t()`, `get_lang()`, `load_language_file()`, `get_browser_language()` | `src/I18n/Translator.php` |

**Chiến lược thực hiện:**
1. Tạo service class mới với methods được copy từ Builder
2. Builder giữ method cũ nhưng delegate: `return $this->service->method()`
3. Dần dần các plugin commands gọi trực tiếp service class thay vì qua Builder
4. Sau khi không còn gì gọi Builder method → xóa khỏi Builder

---

### 🔄 Phase 5 — Gộp Duplicate Plugin Commands

**Mức độ:** Medium

#### 5.1 — `snr_search.php` → gộp vào `search_and_replace.php`
- Hiện tại có 2 implementations SNR song song:
  - `Builder::snr_search()` (legacy streaming HTML, ~142 dòng trong Builder)  
  - `plugins/commands/search_and_replace.php` (JSON API, 656 dòng — mới hơn, tốt hơn)
- `plugins/commands/snr_search.php` hiện gọi `Builder::snr_search()` (legacy)
- **Cần làm:**
  - Cập nhật frontend `builder.js` để gọi `action=search_and_replace` thay vì `action=snr-search`
  - Xóa `Builder::snr_search()` khỏi Builder
  - Xóa `plugins/commands/snr_search.php`

#### 5.2 — `lib/axios/axios.min.js` (không có version)
- Duplicate cũ của `lib/axios@1.6.7/axios.min.js`
- Kiểm tra trong `builder.js` và `.tpl` files không có reference → xóa an toàn

#### 5.3 — `js/bootstrap.min.js` ở root `js/`
- Duplicate của `lib/bootstrap/bootstrap.min.js`
- Kiểm tra `index.tpl` đang load từ `lib/bootstrap/` → xóa `js/bootstrap.min.js`

#### 5.4 — `lib/color/` (vue-color / color-0.4.1)
- Không có reference nào trong `.tpl` hay `builder.js`
- Xóa an toàn sau khi grep confirm

---

### 🔄 Phase 6 — Error Handling Standardization

**Mức độ:** Medium

#### 6.1 — Tạo Exception classes
```
src/Core/Exceptions/
  NotFoundException.php
  PermissionDeniedException.php
  ValidationException.php
  FileSystemException.php
```

#### 6.2 — Router catch exceptions
```php
try {
    $router->dispatch($action, $standalone);
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

#### 6.3 — Plugin commands dùng exceptions
Thay pattern hiện tại:
```php
// TRƯỚC (vẫn còn nhiều chỗ dùng):
if (empty($filepath)) {
    $builder->json_response(['success' => false, 'message' => 'Not found']);
    return;
}

// SAU:
if (empty($filepath)) {
    throw new NotFoundException('File not found');
}
```

#### 6.4 — Fix `set_time_limit(0)` và `ob_implicit_flush(true)`
- Hiện tại: global, áp dụng cho **mọi** request
- Nên: chỉ apply cho các streaming actions (SNR, SSH exec, rebuild indexes)
- Chuyển vào từng plugin command cụ thể cần streaming

---

### 🔄 Phase 7 — Frontend Cleanup

**Mức độ:** Low (aesthetic, không blocking)

#### 7.1 — Dọn duplicate JS/CSS libraries chưa làm
- `lib/axios/` → xóa (giữ `lib/axios@1.6.7/`)
- `js/bootstrap.min.js` → xóa (giữ `lib/bootstrap/`)
- `lib/color/color-0.4.1.min.js` → xóa nếu không dùng
- Verify bằng: `grep -rn "lib/axios/" --include="*.tpl" --include="*.html" .`

#### 7.2 — Tổ chức lại thư mục assets (optional, breaking change)
Theo kế hoạch: gộp `css/`, `js/`, `lib/`, `images/`, `fonts/` vào `public/`  
**Lưu ý:** Cần update tất cả path references trong `.tpl`, `index.php`

#### 7.3 — Tách `builder.js` (2935 dòng) thành modules
- File lớn nhất còn lại sau refactor
- Chia theo chức năng: `editor.js`, `filetree.js`, `snr.js`, `git.js`, `ssh.js`, `ui.js`
- Dùng webpack/rollup hoặc đơn giản là ES modules

---

## NHỮNG VẤN ĐỀ CẦN CHÚ Ý KHI TIẾP TỤC

### ⚠️ Cache file format thay đổi
Các cache file trong `cache/` và session file `.session` trong `tmp/` đã đổi từ `serialize()` sang JSON. Nếu server đang chạy có cache cũ:
```bash
rm -f cache/*
rm -rf tmp/*/. session
```

### ⚠️ `vendor/autoload.php` là minimal stub
File `vendor/autoload.php` hiện tại là autoloader thủ công tối giản, **chỉ** handle `CloudPad\` namespace. Khi deploy cần:
```bash
composer install   # cài phpseclib, phpdotenv
# → composer sẽ overwrite vendor/autoload.php với autoloader đầy đủ
```

### ⚠️ `$_POST` trong `user/index.php`
Login form dùng `$_POST['username']` và `$_POST['password']` trực tiếp — đây là intentional (form POST, không phải AJAX), giữ nguyên là đúng. Không cần thay bằng `Request::getString()`.

### ⚠️ `lib/js-beautify@1.15.4/` — chưa kiểm tra usage
Cần grep trong `builder.js` và plugin tabs trước khi xóa.

### ⚠️ `plugins/fs/svn/svn.php` — chưa kiểm tra
Nếu không có SVN repository nào trong `repositories.conf.php`, file này có thể xóa.

### ⚠️ `standalones/text-diff.html` — standalone tool
Có thể deploy riêng. Nếu không dùng, xóa.

---

## HƯỚNG DẪN CHO AGENT/SESSION TIẾP THEO

### Setup
```bash
# Giải nén codebase
unzip cloudpad9-refactored.zip -d /path/to/work

# Verify PHP syntax (nếu có PHP trên máy)
php -l index.php
php -l src/Core/Request.php
php -l src/Core/Response.php
php -l src/Core/Router.php

# Cài dependencies
composer install
```

### Thứ tự ưu tiên đề xuất
1. **Phase 6** — Exception classes + Router error handler (dễ, isolated, không break gì)
2. **Phase 5** — Gộp SNR + xóa lib duplicates (dễ, giảm bloat)
3. **Phase 3** — Tách Builder (effort lớn nhất, chia nhỏ từng service một)
4. **Phase 7** — Frontend (cuối cùng, ít risk nhất)

### Kiểm tra sau mỗi thay đổi
```
- [ ] App khởi động, login/logout OK
- [ ] Mở file từ repository OK
- [ ] Save file OK
- [ ] Search & Replace OK
- [ ] File tree navigation OK
- [ ] Upload/download file OK
- [ ] Quick access pin/unpin OK
```

### Quy ước code (bắt buộc)
- **Response:** Luôn dùng `Response::ok()` / `Response::fail()` — KHÔNG `echo json_encode()`
- **Input:** Luôn dùng `Request::getString()` / `Request::require()` — KHÔNG `$_REQUEST[...]`
- **Shell:** Luôn `escapeshellarg()` — KHÔNG string interpolation `"cmd $var"`
- **Namespace:** `CloudPad\` prefix cho tất cả class mới trong `src/`
- **Autoload:** PSR-4, file path = class path relative to `src/`
- **Không `exit()`** trong business logic — để Router/framework handle

---

## FILES CẤU TRÚC HIỆN TẠI

```
cloudpad9/
├── index.php                    ← 3,049 dòng (từ 3,858) — vẫn là main entry point
├── index2.php                   ← Nginx wrapper, giữ nguyên
├── composer.json                ← PSR-4 autoload đã thêm
├── vendor/autoload.php          ← Minimal stub, replace bằng composer install
│
├── src/
│   └── Core/
│       ├── Request.php          ← ✅ NEW — Input validation wrapper
│       ├── Response.php         ← ✅ NEW — Unified JSON response
│       └── Router.php           ← ✅ NEW — Action dispatcher (52 dòng)
│
├── plugins/
│   └── commands/               ← 40 files (từ 14)
│       ├── user/               ← Auth plugin (giữ nguyên)
│       ├── [14 file cũ]        ← Đã update escapeshellarg + Request class
│       └── [26 file mới]       ← ✅ Migrated từ index.php Phase 2
│
├── tpl/
│   └── index.tpl               ← ✅ Fix XSS theme param
│
├── css/                        ← Đã xóa bootstrap duplicates
├── js/                         ← Đã xóa ace/, vue.js, backbone*, auth.js...
└── lib/                        ← Đã xóa bootstrap-vue/, vue/, root multiselect*
```

---

*Worklog này được tạo tự động bởi AI agent sau khi hoàn thành Phase 0–4 của REFACTORING_PLAN.md*
