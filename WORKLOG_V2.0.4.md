# WORKLOG — CloudPad9 Refactor v2.0.4

**Ngày tạo:** 2026-03-26
**Dựa trên:** `WORKLOG_V2.0.3.md`
**Trạng thái tổng quát:** Phase 0 ✅ · Phase 1 ✅ · Phase 2 ✅ · Phase 3 ✅ · Phase 4 ✅ · Phase 6 ✅ · Phase 7 (partial) ✅

---

## TÓM TẮT SỐ LIỆU

| Metric | v2.0.3 | v2.0.4 | Ghi chú |
|--------|--------|--------|---------|
| `index.php` dòng | 2,030 | **827** | −59% so với v2.0.3, −79% so với ban đầu |
| Service classes | 8 | **11** | +GitService, +FileOperations, +EditorService |
| `src/` files | 11 | **20** | +9 files (3 services + 4 exception classes + Router update) |
| Builder methods (full body) | ~60 | **~25** | ~35 methods thêm đã delegate ra service |
| Exception classes | 0 | **4** | NotFoundException, PermissionDeniedException, ValidationException, FileSystemException |
| Duplicate lib dirs xoá | 0 | **3** | lib/axios/, js/bootstrap.min.js, lib/color/ |

---

## ✅ Phase 6 — Error Handling Standardization

### 6.1: Exception classes tạo mới (`src/Core/Exceptions/`)

```
src/Core/Exceptions/
├── NotFoundException.php          ← 404-style: file/repo/path không tồn tại
├── PermissionDeniedException.php  ← 403-style: user thiếu quyền
├── ValidationException.php        ← 422-style: input validation fail
└── FileSystemException.php        ← 500-style: I/O error
```

Tất cả extend `\RuntimeException`. Dùng trong plugin commands bằng cách throw thay vì `echo/exit`.

### 6.2: Router catch exceptions

`src/Core/Router.php` đã được cập nhật:
- Import 4 exception classes
- `dispatch()` bọc plugin command execution trong `try/catch`
- Mapping:
  - `NotFoundException` → `Response::fail($msg, ['code' => 404])`
  - `PermissionDeniedException` → `Response::fail($msg, ['code' => 403])`
  - `ValidationException` → `Response::fail($msg, ['code' => 422])`
  - `FileSystemException` → `error_log()` + `Response::fail($msg, ['code' => 500])`
  - `\Throwable` (catch-all) → `error_log()` + `Response::fail('Internal error', ['code' => 500])`

### 6.3: Plugin commands — migration guide (chưa migrate hàng loạt)

Pattern mới khi viết hoặc update plugin command:

```php
// TRƯỚC (legacy):
if (empty($filepath)) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    return;
}

// SAU (Phase 6):
use CloudPad\Core\Exceptions\NotFoundException;
if (empty($filepath)) {
    throw new NotFoundException('File not found');
}
```

Các plugin commands hiện tại chưa được migrate sang throw pattern — đây là việc tiếp theo (từng file, theo nhu cầu).

---

## ✅ Phase 3 (gap fill) — 3 Service Classes Còn Thiếu

### `src/Git/GitService.php` (2 methods)

Tách từ Builder — hai methods được thêm thẳng vào Builder thay vì tách ra service từ đầu:

| Method trong Builder | Method trong GitService |
|---------------------|------------------------|
| `execGitCommand(repoDir, subCmd, &output)` | `execGitCommand(repoDir, subCmd, &output)` |
| `get_git_info(filepath)` | `getGitInfo(filepath)` |

Builder giữ thin wrappers: `function execGitCommand(...) { return $this->_gitService->execGitCommand(...); }`

### `src/FileSystem/FileOperations.php` (9 methods)

Tách I/O layer thuần túy từ Builder:

| Method trong Builder | Method trong FileOperations |
|---------------------|----------------------------|
| `getLocalizedPath(file, repository)` | `getLocalizedPath(file, repository)` |
| `getRelPath(filepath, repository)` | `getRelPath(filepath, repository)` |
| `file_exists(file, repository)` | `fileExists(file, repository)` |
| `rename(file, newfile, repository)` | `rename(file, newfile, repository)` |
| `file_get_contents(file, repository)` | `fileGetContents(file, repository)` |
| `file_put_contents(file, content, ...)` | `filePutContents(file, content, ...)` |
| `try_chmod(mode, filepath)` | `tryChmod(mode, filepath)` |
| `try_exec(command, &error)` | `tryExec(command, &error)` |
| `is_empty_dir(dir)` | `isEmptyDir(dir)` |

### `src/Editor/EditorService.php` (13+ methods)

Tách editor workflow layer từ Builder:

| Builder method | EditorService method |
|---------------|---------------------|
| `open_file_by_name(filename, fromcache, repo)` | `openFileByName(filename, fromcache, repo)` |
| `get_directory_children(repo, path)` | `getDirectoryChildren(repo, path)` |
| `get_directory_structure(repo)` | `getDirectoryStructure(repo)` |
| `getDirectoryStructure(filepaths)` | `buildDirectoryStructure(filepaths)` |
| `addToDirectoryStructure(&structure, parts, basePath)` | `addToDirectoryStructure(...)` |
| `file_live_search(repo, filename)` | `fileLiveSearch(repo, filename)` |
| `get_file_content(filename, repo)` | `getFileContent(filename, repo)` |
| `close_file(filename, repo, standalone)` | `closeFile(filename, repo, standalone)` |
| `getUserUploadFiles()` | `getUserUploadFiles()` |
| `upload_file()` | `uploadFile()` |
| `download_user_file(filename)` | `downloadUserFile(filename)` |
| `delete_user_file(filename)` | `deleteUserFile(filename)` |
| `getUserTempFilePaths()` | `getUserTempFilePaths()` |
| `getNewFilePath()` | `getNewFilePath()` |
| `new_temp_file()` | `newTempFile()` |
| `clone_file(filename, repo, newname)` | `cloneFile(filename, repo, newname)` |
| `rebuild_sub_indexes(filename, repo)` | `rebuildSubIndexes(filename, repo)` |
| `save_current_file(filename, repo, content, flag)` | `saveCurrentFile(filename, repo, content, flag)` |
| `convert_tabs_to_whitespaces(content)` | `convertTabsToWhitespaces(content)` |
| `trim_trailing_whitespaces(content)` | `trimTrailingWhitespaces(content)` |
| `trim_trailing_commas(content)` | `trimTrailingCommas(content)` |
| `onBeforeSavingFile(&content, ext)` | `onBeforeSavingFile(&content, ext)` |
| `is_same_content(c1, c2)` | `isSameContent(c1, c2)` |
| `setFilePath(filename, filepath, repo)` | `setFilePath(filename, filepath, repo)` |
| `addToRepositoryFilePaths(filepath, repo)` | `addToRepositoryFilePaths(filepath, repo)` |
| `getOpenFiles(tempOnly)` | `getOpenFiles(tempOnly)` |
| `getEditorOpenFiles(tempOnly)` | `getEditorOpenFiles(tempOnly)` |

**Builder constructor** đã được cập nhật để khởi tạo cả 3 service mới:
```php
$this->_gitService    = new \CloudPad\Git\GitService($this);
$this->_fileOps       = new \CloudPad\FileSystem\FileOperations($this);
$this->_editorService = new \CloudPad\Editor\EditorService($this);
```

---

## ✅ Phase 7 (partial) — Frontend Cleanup

### Xoá duplicate libraries

| File / Thư mục | Lý do xoá | Thay thế |
|----------------|-----------|----------|
| `lib/axios/` | Duplicate không versioned | `lib/axios@1.6.7/axios.min.js` ✅ |
| `js/bootstrap.min.js` | Duplicate | `lib/bootstrap/bootstrap.min.js` ✅ |
| `lib/color/` | Zero references trong codebase | N/A |

Kiểm tra trước khi xoá: `grep -rn "lib/axios[^@]\|js/bootstrap.min\|lib/color"` → 0 kết quả trong tpl, builder.js, plugins/.

---

## CÒN LẠI — CHƯA LÀM

### 🔄 Phase 5 — Gộp Duplicate Plugin Commands

- Gộp `git_log.php` + `git_log_all.php` → `git_log.php` (thêm param `scope`)
- Gộp `git_diff.php` + `git_diff_all.php` → `git_diff.php`
- Gộp `git_commit.php` + `git_commit_all.php` → `git_commit.php`
- Gộp `copy_files.php` + `move_files.php` → `file_operations.php`
- Yêu cầu update frontend (`builder.js`)

### 🔄 Phase 6.3 — Migrate plugin commands sang throw pattern

Plugin commands hiện tại vẫn dùng `echo json_encode()/exit`. Cần từng bước update sang `throw new NotFoundException(...)` để tận dụng Router catch.

### 🔄 Phase 7 (remaining) — Tổ chức lại thư mục `public/`

- Tách CSS/JS/lib ra thư mục `public/` riêng
- Tách `builder.js` thành modules (optional, long-term)

---

## FILES CẤU TRÚC HIỆN TẠI

```
cloudpad9/
├── index.php                              ← 827 dòng (từ 3,858) — −79%
├── src/
│   ├── Core/
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Router.php                     ← ✅ UPDATED Phase 6: exception catch
│   │   └── Exceptions/
│   │       ├── NotFoundException.php      ← ✅ NEW Phase 6
│   │       ├── PermissionDeniedException.php ← ✅ NEW Phase 6
│   │       ├── ValidationException.php    ← ✅ NEW Phase 6
│   │       └── FileSystemException.php    ← ✅ NEW Phase 6
│   ├── I18n/
│   │   └── Translator.php
│   ├── Auth/
│   │   └── AuthService.php
│   ├── Editor/
│   │   ├── ColorManager.php
│   │   ├── RevisionManager.php
│   │   ├── SyncService.php
│   │   └── EditorService.php              ← ✅ NEW Phase 3 gap-fill
│   ├── SSH/
│   │   └── SSHService.php
│   ├── Repository/
│   │   └── RepositoryManager.php
│   ├── Search/
│   │   └── FileSearchService.php
│   ├── Git/
│   │   └── GitService.php                 ← ✅ NEW Phase 3 gap-fill
│   └── FileSystem/
│       └── FileOperations.php             ← ✅ NEW Phase 3 gap-fill
├── plugins/
│   └── commands/                          ← 40+ files (Phase 2)
├── lib/                                   ← axios@1.6.7, bootstrap, jquery-ui, ...
│                                             (lib/axios/, lib/color/ ĐÃ XOÁ)
├── js/
│   ├── builder.js
│   └── ...                               ← bootstrap.min.js ĐÃ XOÁ
└── ...
```

---

## NHỮNG VẤN ĐỀ CẦN CHÚ Ý

### ⚠️ `EditorService::addToRepositoryFilePaths()` — cache path hardcode
Method dùng `dirname(dirname(dirname(__DIR__)))` để resolve `cache/` dir. Cần kiểm tra đường dẫn này đúng với deploy structure trên server thực. Nếu sai, `addToRepositoryFilePaths()` sẽ silently fail (file not found = no-op).

### ⚠️ `FileOperations::isEmptyDir()` — khác behavior so với Builder cũ
Builder cũ: `return NULL` nếu `!is_readable()`. FileOperations mới: `return false`. Đây là intentional improvement nhưng cần test nếu có code check `=== NULL`.

### ⚠️ `getFrequentUsedShellCommands()` — vẫn chưa tìm thấy (từ v2.0.3)
`SSHService::sshExec()` gọi `$this->builder->getFrequentUsedShellCommands()`. Method không tồn tại trong codebase. Cần implement hoặc remove SSH exec nếu không dùng.

### ⚠️ Plugin commands chưa dùng Exception classes
Router đã sẵn sàng catch, nhưng plugin commands vẫn dùng `echo/exit`. Việc migrate là incremental — ưu tiên các commands quan trọng nhất trước.

---

*Worklog tạo sau khi hoàn thành Phase 3 gap-fill + Phase 6 + Phase 7 partial — CloudPad9 Refactor*
