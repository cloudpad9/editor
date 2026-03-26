# WORKLOG — CloudPad9 Refactor v2.0.3

**Ngày tạo:** 2026-03-25
**Dựa trên:** `WORKLOG_V2.0.1.md` + master branch integration (v2.0.2)
**Trạng thái tổng quát:** Phase 0 ✅ · Phase 1 ✅ · Phase 2 ✅ · Phase 3 ✅ · Phase 4 ✅ · Phase 5/6/7 🔄

---

## TÓM TẮT SỐ LIỆU

| Metric | v2.0.1 | v2.0.3 | Ghi chú |
|--------|--------|--------|---------|
| `index.php` dòng | 3,049 | **2,030** | −33% so với v2.0.1, −47% so với ban đầu |
| Service classes | 0 | **8** | Translator, AuthService, ColorManager, RevisionManager, SyncService, SSHService, RepositoryManager, FileSearchService |
| `src/` files | 3 | **11** | +8 service files |
| Builder methods (full body) | ~141 | **~60** | ~80 methods đã delegate sang services |

---

## ✅ Phase 3 — Tách Builder Class Thành Service Classes

**Chiến lược:** Delegation pattern — Builder giữ method signature cũ, body delegate sang service. Backward compatibility 100% với plugin commands hiện có.

### Service classes tạo mới

#### `src/I18n/Translator.php`
Methods tách từ Builder:
- `getLang()` ← `get_lang()`
- `loadLanguageFile()` ← `load_language_file()`
- `getUserLanguage()` ← `get_user_language()`
- `getBrowserLanguage()` ← `get_browser_language()`

#### `src/Auth/AuthService.php`
Methods tách từ Builder:
- `serializeUserSessionData()`, `reloadUserSessionData()` — session persistence
- `isUserLoggedIn()`, `auth()`, `ensureAuth()` — auth checks
- `getCurrentUser()`, `getCurrentUsername()`, `getUserSessionId()`, `getPublicUserInfo()`, `getUsers()` — identity
- `getUserDataDir()`, `getUserUploadDir()`, `getUserRepositoryDir()`, `getUserTempDir()`, `getUserRevisionDir()`, `getUserTempRevisionDir()` — user directories

#### `src/Editor/ColorManager.php`
Methods tách từ Builder:
- `setColor()` ← `set_color()`
- `getColor()` ← `get_color()`
- `getColorFile()` ← `get_color_file()`

#### `src/Editor/RevisionManager.php`
Methods tách từ Builder:
- `saveFileRevision()`, `createTempRevision()` — ghi revision
- `getRevisionCount()`, `getRevisionPrefix()`, `getLatestRevisionContent()`, `getLatestTempRevisionContent()` — đọc revision
- `revertFile()`, `recoverFile()`, `reloadFile()` — file-level actions

#### `src/Editor/SyncService.php`
Methods tách từ Builder:
- `syncFile()` ← `sync_file()` — fix bug: `$sync_routes` chưa init (undefined variable trong Builder cũ)
- `getSyncDest()` ← `get_sync_dest()`

#### `src/SSH/SSHService.php`
Methods tách từ Builder:
- `ensureSafeCommand()` ← `ssh_ensure_safe_command()`
- `sshExec()` ← `ssh_exec()`
- `privateSshExec()` ← `private_ssh_exec()`
- `getAugmentedOutput()` ← `ssh_getAugmentedOutput()`
- `sshExec2()` ← `ssh_exec_2()`
- `executeLinux()` ← `execute_linux()`

#### `src/Repository/RepositoryManager.php`
Methods tách từ Builder:
- `getRepositoriesFromFile()`, `getRepositories()`, `getRepositorySettings()`, `hasRepositoryPermission()`, `getRepositoryHandler()` — repository list & settings
- `getAbsolutePath()`, `getAbsoluteFilePath()`, `getRepositoryWisePath()`, `getFileRepository()` — path resolution
- `getRepositoryCacheFile()`, `getRepositoryFilePaths()`, `getProjectFilePaths()` — file index
- `getSftpPrefix()` ← `get_sftp_prefix()`
- `rebuildIndexesUsingRust()` (từ private → public, vẫn gọi nội bộ)

#### `src/Search/FileSearchService.php`
Methods tách từ Builder:
- `searchForFile()`, `searchForFiles()`, `searchForFilesInArray()` — search
- `isPathMatched()` ← `is_path_matched()`
- `isExcludedPath()` ← `is_excluded_path()`
- `glob()`, `rsearch()` — filesystem traversal

### Thay đổi `index.php`

- Thêm **8 service properties** và **constructor** vào `Builder`
- **~80 Builder methods** giờ là thin wrappers: `function foo(...) { return $this->_service->bar(...); }`
- Không có breaking change — tất cả method signatures giữ nguyên

---

## CÒN LẠI — CHƯA LÀM

### 🔄 Phase 5 — Gộp Duplicate Plugin Commands

- `snr_search.php` → gộp vào `search_and_replace.php` (đã bỏ qua per quyết định)
- Xóa `lib/axios/` (duplicate của `lib/axios@1.6.7/`)
- Xóa `js/bootstrap.min.js` (duplicate của `lib/bootstrap/`)
- Xóa `lib/color/` (không có reference)

### 🔄 Phase 6 — Error Handling Standardization

- Tạo Exception classes (`src/Core/Exceptions/`)
- Router catch exceptions
- Plugin commands throw exceptions thay vì `echo/exit`
- Chuyển `set_time_limit(0)` và `ob_implicit_flush(true)` vào từng command cần streaming

### 🔄 Phase 7 — Frontend Cleanup

- Dọn duplicate JS/CSS libraries
- Tổ chức lại thư mục `public/`
- Tách `builder.js` thành modules (optional)

---

## NHỮNG VẤN ĐỀ CẦN CHÚ Ý

### ⚠️ `getFrequentUsedShellCommands()` — method chưa tìm thấy
`SSHService::sshExec()` gọi `$this->builder->getFrequentUsedShellCommands()`. Method này không có trong codebase hiện tại (có thể bị xóa trong phase trước hoặc defined ở nơi khác). Cần kiểm tra và implement nếu SSH exec được dùng.

### ⚠️ `Builder::rebuildIndexesUsingRust()` — private delegate
Builder vẫn giữ `private function rebuildIndexesUsingRust()` để delegate sang `RepositoryManager`. Không có code bên ngoài gọi trực tiếp method này nên an toàn.

### ⚠️ Delegation pattern — chưa xóa Builder methods
Theo kế hoạch phase 3: "Sau khi không còn gì gọi Builder method → xóa khỏi Builder". Hiện tại Builder vẫn giữ thin wrappers. Việc xóa hoàn toàn là bước tiếp theo khi các plugin commands được update để gọi service trực tiếp.

### ⚠️ Cache và session (giữ nguyên từ v2.0.1)
Cache file trong `cache/` và session file `.session` trong `tmp/` dùng JSON format. Nếu có file cũ dạng `serialize`, cần xóa để regenerate.

### ⚠️ `vendor/autoload.php` là minimal stub (giữ nguyên từ v2.0.1)
Chạy `composer install` để thay thế bằng autoloader đầy đủ khi deploy.

---

## FILES CẤU TRÚC HIỆN TẠI

```
cloudpad9/
├── index.php                          ← 2,030 dòng (từ 3,858) — −47%
├── src/
│   ├── Core/
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Router.php
│   ├── I18n/
│   │   └── Translator.php             ← ✅ NEW Phase 3
│   ├── Auth/
│   │   └── AuthService.php            ← ✅ NEW Phase 3
│   ├── Editor/
│   │   ├── ColorManager.php           ← ✅ NEW Phase 3
│   │   ├── RevisionManager.php        ← ✅ NEW Phase 3
│   │   └── SyncService.php            ← ✅ NEW Phase 3
│   ├── SSH/
│   │   └── SSHService.php             ← ✅ NEW Phase 3
│   ├── Repository/
│   │   └── RepositoryManager.php      ← ✅ NEW Phase 3
│   └── Search/
│       └── FileSearchService.php      ← ✅ NEW Phase 3
├── plugins/
│   └── commands/                      ← 40 files (Phase 2) + 10 git commands (v2.0.2)
└── ...
```

---

*Worklog tạo sau khi hoàn thành Phase 3 — CloudPad9 Refactor*
