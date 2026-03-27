# CloudPad9 — Kế hoạch Refactor v2

## Mục lục

1. [Tổng quan hiện trạng](#1-tổng-quan-hiện-trạng)
2. [Kiến trúc mục tiêu](#2-kiến-trúc-mục-tiêu)
3. [Phase R1: Xoá duplicate + migrate plugins](#3-phase-r1-xoá-duplicate--migrate-plugins)
4. [Phase R2: Decompose Builder God Object](#4-phase-r2-decompose-builder-god-object)
5. [Phase R3: Refactor editor/index.tpl](#5-phase-r3-refactor-editorindextpl)
6. [Phase R4: Fix globals & code smells](#6-phase-r4-fix-globals--code-smells)
7. [Phase R5: Cleanup & polish](#7-phase-r5-cleanup--polish)
8. [Phase R6: Unit tests](#8-phase-r6-unit-tests)
9. [Execution order & effort](#9-execution-order--effort)

---

## 1. Tổng quan hiện trạng

### 1.1 Cấu trúc codebase

```
CloudPad9/
├── index.php                    # Entry point (~20 dòng, clean)
├── config/services.php          # DI Container wiring (~150 dòng, clean)
├── src/
│   ├── App.php                  # Bootstrap (~160 dòng, clean)
│   ├── Builder.php              # GOD OBJECT (~881 dòng, CẦN REFACTOR MẠNH)
│   ├── Auth/                    # AuthService + Interface (clean)
│   ├── Core/
│   │   ├── Container.php        # Simple DI (clean)
│   │   ├── Router.php           # Action dispatch (clean)
│   │   ├── Request.php          # Input helper (clean)
│   │   ├── Response.php         # Output helper (clean)
│   │   ├── helpers.php          # Legacy global functions
│   │   ├── Debug/ProfilingHelper.php  # Namespaced version (clean)
│   │   ├── Exceptions/          # Custom exceptions (clean)
│   │   ├── Output/OutputManager.php   # Flush/verbose (clean)
│   │   ├── Process/ProcessManager.php # Shell exec (clean)
│   │   └── Session/             # 6 session stores (clean)
│   ├── Editor/                  # EditorService, ColorManager, RevisionManager, SyncService
│   ├── FileSystem/              # FileOperations + Interface
│   ├── Git/                     # GitService + Interface
│   ├── I18n/                    # Translator + helpers.php
│   ├── Plugin/                  # BaseFilesystemPlugin, BaseTabPlugin (CHƯA ĐƯỢC SỬ DỤNG)
│   ├── Repository/              # RepositoryManager + Interface
│   ├── SSH/                     # SSHService
│   └── Search/                  # FileSearchService, SNRService
├── plugins/
│   ├── commands/                # ~50 action handlers (hỗn hợp function/class)
│   ├── fs/                      # 4 filesystem plugins (extend global `plugin_fs`)
│   └── tabs/                    # 3 tab plugins (standalone, không extend Base)
├── tpl/index.tpl                # Main HTML template
└── js/builder.js                # Frontend JS (~87KB)
```

### 1.2 Các vấn đề chính

| # | Vấn đề | Vị trí | Mức độ |
|---|--------|--------|--------|
| 1 | **3 global classes duplicate** trong Builder.php (`plugin_fs`, `plugin_tab`, `ProfilingHelper`) — đã có namespaced versions tại `src/Plugin/` và `src/Core/Debug/` | `src/Builder.php:19-146` | 🔴 |
| 2 | **Builder = God Object** ~133 methods, trong đó ~79 là thin wrappers chỉ delegate sang services | `src/Builder.php` (881 dòng) | 🔴 |
| 3 | **FS plugins extend global `plugin_fs`** thay vì `BaseFilesystemPlugin` | `plugins/fs/*.php` | 🔴 |
| 4 | **Tab plugins không extend `BaseTabPlugin`** | `plugins/tabs/*/index.php` | 🟠 |
| 5 | **editor/index.tpl = 3665 dòng** monolith (HTML + 15 Vue components inline) — copy từ nơi khác, chưa conform | `plugins/tabs/editor/index.tpl` | 🔴 |
| 6 | **`global $ajax`** trong EditorService::openFileByName() | `src/Editor/EditorService.php:59` | 🟠 |
| 7 | **`_t()` dùng `global $builder`** | `src/I18n/helpers.php` | 🟠 |
| 8 | **Legacy debug `echo`** trong EditorService::isSameContent() | `src/Editor/EditorService.php:694` | 🟡 |
| 9 | **SSHService duplicate** — `sshExec()`, `privateSshExec()`, `sshExec2()` gần giống nhau nhưng dùng 2 auth strategies khác nhau | `src/SSH/SSHService.php` | 🟠 |
| 10 | **`if (true \|\| empty($repodir))`** — dead code | `plugins/fs/git/git.php:23,53` | 🟡 |
| 11 | **`die()` call** thay vì throw exception | `plugins/fs/sftp/sftp.php:25` | 🟠 |
| 12 | **`global $sftps`** | `plugins/fs/sftp/sftp.php:5` | 🟠 |
| 13 | **`$_SESSION` trực tiếp** trong templates | `plugins/tabs/snr/index.tpl`, `editor/index.tpl` | 🟡 |
| 14 | **`mb_convert_encoding` deprecated** (PHP 8.2+) | `src/Builder.php:396-397` | 🟡 |
| 15 | **Router tạo mới mỗi lần** trong backward-compat wrappers | `src/Builder.php:424,439` | 🟡 |
| 16 | **`getUsers()` duplicate** — cả Builder và AuthService | `src/Builder.php:638` | 🟡 |
| 17 | **Builder chưa có namespace** — class duy nhất ở global scope | `src/Builder.php` | 🟡 |
| 18 | **`index2.php` hardcoded 32 lần** trong editor/index.tpl JS | `plugins/tabs/editor/index.tpl` | 🟡 (defer) |

---

## 2. Kiến trúc mục tiêu

### 2.1 Nguyên tắc thiết kế

- **Builder.php ≤ 150 dòng** — Container facade + plugin orchestration + template helpers
- **Plugins extend đúng Base classes** — sửa trực tiếp, không dùng alias
- **Không có global class** nào ngoài namespace `CloudPad\`
- **Không có `global $variable`** trong bất kỳ service nào
- **Templates dùng `session_get()` helper** thay vì `$_SESSION` trực tiếp
- **editor/index.tpl**: HTML gọn + Vue components tách file, gộp runtime bằng PHP include

### 2.2 Dependency flow mục tiêu

```
index.php
  └─ App::boot() → App::run()
       ├─ Container (services.php) → resolve all services
       ├─ Router::dispatch() → plugin commands
       │    └─ commands gọi services qua $builder->get(ServiceClass::class)
       │       hoặc qua ~10 wrapper methods phổ biến được giữ lại
       └─ tpl/index.tpl → tab plugins render
            └─ tab plugins extend BaseTabPlugin, nhận $builder qua render()
```

---

## 3. Phase R1: Xoá duplicate + migrate plugins

**Mục tiêu:** Xoá 3 global classes, sửa thẳng 7 plugin files extend đúng Base class. Giảm Builder ~130 dòng.

### R1.1 — Xoá global `ProfilingHelper` (Builder.php:87-146)

**Vấn đề:** Bản copy của `CloudPad\Core\Debug\ProfilingHelper`. Bản namespaced đã clean hơn (type hints, fix typo).

**Thao tác:**
1. Xoá lines 87-146 trong `src/Builder.php`
2. Search toàn bộ codebase: `grep -rn 'ProfilingHelper::' --include='*.php'`
3. Thay tất cả `ProfilingHelper::` (không prefix) thành `\CloudPad\Core\Debug\ProfilingHelper::`
4. **Không tạo class_alias** — ProfilingHelper chỉ gọi chủ động khi debug, fatal error nếu thiếu sẽ dễ phát hiện

**Files ảnh hưởng:** `src/Builder.php` (xoá ~60 dòng)

### R1.2 — Xoá global `plugin_fs` + sửa thẳng 4 FS plugins

**Vấn đề:** `plugin_fs` global (Builder.php:19-78) trùng với `BaseFilesystemPlugin`. 4 FS plugins extend class global.

**Thao tác:**
1. Xoá class `plugin_fs` (Builder.php:19-78)
2. Sửa 4 plugin files extend `\CloudPad\Plugin\BaseFilesystemPlugin` trực tiếp
3. Đồng thời fix code smells trong từng plugin (gộp với R2.1 cũ)

**Sửa `plugins/fs/local/local.php`:**
```php
<?php
class plugin_fs_local extends \CloudPad\Plugin\BaseFilesystemPlugin {
    public function isAccessible(array $settings): bool {
        $dirs = $settings['dirs'] ?? [];
        foreach ($dirs as $dir) {
            if (is_dir($dir)) return true;
        }
        return false;
    }

    public function getRepositoryOperations(array $settings): array {
        $dirs       = $settings['dirs'] ?? [];
        $repository = $settings['code'] ?? '';
        $operations = [];

        foreach ($dirs as $dir) {
            if (file_exists($dir . '/composer.json')) {
                $operations['composer install'] = "cd $dir; composer install";
                $operations['composer update']  = "cd $dir; composer update";
            }
            if (is_dir($dir . '/.git')) {
                $operations['git status'] = "cd $dir; echo \\# repository $repository $dir; git status";
                $operations['git add']    = "cd $dir; git add --all";
                $operations['git commit'] = "cd $dir; git commit -m 'Commit message'";
                $operations['git rebase'] = "cd $dir; git fetch; git rebase origin/master";
                $operations['git push']   = "cd $dir; git push origin master";
            }
            if (is_dir($dir . '/.svn')) {
                $svn = file_exists('/usr/local/bin/svn') ? '/usr/local/bin/svn' : 'svn';
                $operations['svn info']   = "$svn info $dir";
                $operations['svn status'] = "$svn status $dir";
                $operations['svn update'] = "$svn up $dir";
                $operations['svn commit'] = "$svn commit -m 'X' $dir";
            }
        }
        return $operations;
    }
}
```

**Sửa `plugins/fs/git/git.php`:**
```php
<?php
class plugin_fs_git extends \CloudPad\Plugin\BaseFilesystemPlugin {
    public function isAccessible(array $settings): bool {
        $repodir = $settings['git']['dir'] ?? '';
        return !is_object($repodir) && is_dir($repodir);
    }

    public function init(array &$settings): void {
        $path     = $settings['git']['path'];
        $reponame = basename($path);
        // FIX: xoá dead code `if (true || empty($repodir))`
        $repodir  = $this->builder->getUserRepositoryDir() . '/' . $reponame;

        $settings['dirs']     = array_merge($settings['dirs'] ?? [], [$repodir]);
        $settings['excludes'] = array_merge($settings['excludes'] ?? [], [$repodir . '/.git']);
    }

    public function getRepositoryOperations(array $settings): array {
        $path     = $settings['git']['path'];
        $reponame = basename($path);
        $repodir  = $this->builder->getUserRepositoryDir() . '/' . $reponame;
        $repository = $settings['code'] ?? '';

        $operations = [
            'git status' => "cd $repodir; echo \\# repository $repository $repodir; git status",
            'git add'    => "cd $repodir; git add --all",
            'git commit' => "cd $repodir; git commit -m 'Commit message'",
            'git rebase' => "cd $repodir; git fetch; git rebase origin/master",
            'git push'   => "cd $repodir; git push origin master",
        ];
        return array_merge($operations, parent::getRepositoryOperations($settings));
    }

    public function getLocalizedPath(array $settings, string $path): string {
        return $path;
    }
}
```

**Sửa `plugins/fs/sftp/sftp.php`:**
```php
<?php
class plugin_fs_sftp extends \CloudPad\Plugin\BaseFilesystemPlugin {
    // FIX: xoá `global $sftps` → static property
    private static array $connections = [];

    public function init(array &$settings): void {
        // no-op
    }

    public function getRepositoryOperations(array $settings): array {
        return parent::getRepositoryOperations($settings);
    }

    public function getLocalizedPath(array $settings, string $file): string {
        set_time_limit(0);
        $sftp = $this->getSftpConnection(
            $settings['sftp']['host'],
            $settings['sftp']['port'],
            $settings['sftp']['username'],
            $settings['sftp']['password']
        );
        $fs_prefix = 'ssh2.sftp://' . intval($sftp);
        if (empty($fs_prefix)) {
            // FIX: xoá die() → throw exception
            throw new \CloudPad\Core\Exceptions\FileSystemException(
                'Cannot connect to remote file system via SFTP'
            );
        }
        return $fs_prefix . $file;
    }

    private function getSftpConnection(string $host, int $port, string $username, string $password) {
        $key = "$host:$port:$username";
        if (!isset(self::$connections[$key])) {
            $connection = ssh2_connect($host, $port);
            if (!$connection) {
                throw new \CloudPad\Core\Exceptions\FileSystemException(
                    "Cannot connect to SFTP server: $host:$port"
                );
            }
            if (!ssh2_auth_password($connection, $username, $password)) {
                throw new \CloudPad\Core\Exceptions\FileSystemException(
                    'Cannot authenticate with SFTP server'
                );
            }
            self::$connections[$key] = ssh2_sftp($connection);
        }
        return self::$connections[$key];
    }
}
```

**Sửa `plugins/fs/svn/svn.php`:** Thêm `extends \CloudPad\Plugin\BaseFilesystemPlugin`, thêm type hints.

### R1.3 — Xoá global `plugin_tab` + sửa thẳng 3 tab plugins

**Thao tác:**
1. Xoá class `plugin_tab` (Builder.php:80-85)
2. Sửa 3 tab plugins extend `\CloudPad\Plugin\BaseTabPlugin`

**Pattern cho cả 3 files** (`editor/index.php`, `snr/index.php`, `diff/index.php`):
```php
<?php
class plugin_tab_editor extends \CloudPad\Plugin\BaseTabPlugin {
    public function getTabTitle(): string {
        return 'EDITOR';
    }

    public function getPluginInfo(): ?array {
        return ['title' => 'Editor', 'category' => 'Development', 'description' => 'Editing files online'];
    }

    public function render($builder): void {
        include __DIR__ . '/index.tpl';
    }
}
```

### R1.4 — Xoá unused Builder methods

**Thao tác:**
1. Chạy: `grep -rn 'is_plugin_command\|execute_plugin_command\|get_sub_dirs\|getUsers' plugins/ tpl/ --include='*.php' --include='*.tpl'`
2. Xoá methods không có caller bên ngoài Builder:
   - `is_plugin_command()` (Builder:422-435) — chỉ backward-compat, Router owns logic
   - `execute_plugin_command()` (Builder:437-452) — chỉ backward-compat, Router owns logic
   - `get_sub_dirs()` (Builder:866-878) — nếu có caller thì inline 6 dòng logic tại chỗ gọi
   - `getUsers()` (Builder:638) — duplicate với AuthService::getUsers()
3. Update callers nếu cần:
   - `plugins/commands/user/index.php` nếu gọi `$builder->getUsers()` → đổi thành `$builder->get(\CloudPad\Auth\AuthService::class)->getUsers()`

**Files ảnh hưởng:** `src/Builder.php` (xoá ~50 dòng)

### R1 kết quả: Builder giảm từ 881 → ~650 dòng

---

## 4. Phase R2: Decompose Builder God Object

**Mục tiêu:** Builder từ ~650 dòng → ~150 dòng. Giữ lại vai trò: Container facade + plugin orchestration + template helpers.

### R2.1 — Thêm generic `get()` + 5 typed getters phổ biến

**Thêm vào Builder:**
```php
// ── Service access ──────────────────────────────────────────────────────

/**
 * Generic service getter — dùng cho commands và logic cần access service ít phổ biến.
 * Usage: $builder->get(\CloudPad\Editor\EditorService::class)->saveCurrentFile(...)
 */
public function get(string $class): object {
    return $this->_container->get($class);
}

// Typed getters cho 5 services dùng nhiều nhất trong templates và commands:
public function getAuth(): \CloudPad\Auth\AuthService              { return $this->_auth; }
public function getRepoManager(): \CloudPad\Repository\RepositoryManager { return $this->_repositoryManager; }
public function getFileOps(): \CloudPad\FileSystem\FileOperations  { return $this->_fileOps; }
public function getOutput(): \CloudPad\Core\Output\OutputManager   { return $this->_output; }
public function getEditorService(): \CloudPad\Editor\EditorService { return $this->_editorService; }
```

### R2.2 — Xác định wrappers GIỮ LẠI vs XOÁ

**Giữ lại ~10 wrapper methods** được gọi bởi nhiều (>5) command files — tránh phải sửa quá nhiều files cùng lúc:

| Wrapper giữ lại | Lý do |
|-----------------|-------|
| `getRepositorySettings($repo)` | Gọi bởi ~15 commands |
| `getAbsoluteFilePath($filename, $repo)` | Gọi bởi ~12 commands |
| `file_get_contents($file, $repo)` | Gọi bởi ~8 commands |
| `file_put_contents($file, $content, $repo, ...)` | Gọi bởi ~8 commands |
| `file_exists($file, $repo)` | Gọi bởi ~6 commands |
| `flush_line($s, ...)` | Gọi bởi ~10 commands |
| `verbose($content)` | Gọi bởi ~8 commands |
| `error($message)` | Gọi bởi ~6 commands (alias Response::fail) |
| `getRepositories()` | Gọi bởi templates + ~5 commands |
| `getRelPath($filepath, $repo)` | Gọi bởi ~5 commands |

**Giữ lại template helper wrappers** — view layer nên gọn:

| Wrapper giữ lại | Gọi bởi |
|-----------------|---------|
| `isUserLoggedIn()` | `tpl/index.tpl` |
| `getUserSessionId()` | `tpl/index.tpl` |
| `getUserTabs()` | `tpl/index.tpl` |
| `getEditorOpenFiles()` | `editor/index.tpl` |
| `get_lang()` | `tpl/index.tpl` |

**Xoá TẤT CẢ wrapper methods còn lại** (~60 methods). Đây là phần lớn nhất của Phase R2.

### R2.3 — Migrate command plugins sang direct service calls

**Quy trình cho mỗi command file:**
1. Mở file, tìm tất cả `$builder->xxx()` calls
2. Nếu `xxx` nằm trong danh sách "giữ lại" → không sửa
3. Nếu `xxx` đã bị xoá → thay bằng direct service call

**Bảng mapping cho các wrappers bị XOÁ** (command files cần update):

```
$builder->save_current_file(...)      → $builder->getEditorService()->saveCurrentFile(...)
$builder->open_file_by_name(...)      → $builder->getEditorService()->openFileByName(...)
$builder->get_file_content(...)       → $builder->getEditorService()->getFileContent(...)
$builder->close_file(...)             → $builder->getEditorService()->closeFile(...)
$builder->clone_file(...)             → $builder->getEditorService()->cloneFile(...)
$builder->new_temp_file()             → $builder->getEditorService()->newTempFile()
$builder->upload_file()               → $builder->getEditorService()->uploadFile()
$builder->download_user_file(...)     → $builder->getEditorService()->downloadUserFile(...)
$builder->delete_user_file(...)       → $builder->getEditorService()->deleteUserFile(...)
$builder->get_directory_children(...) → $builder->getEditorService()->getDirectoryChildren(...)
$builder->get_directory_structure(...)→ $builder->getEditorService()->getDirectoryStructure(...)
$builder->file_live_search(...)       → $builder->getEditorService()->fileLiveSearch(...)
$builder->rebuild_sub_indexes(...)    → $builder->getEditorService()->rebuildSubIndexes(...)

$builder->set_color(...)              → $builder->get(ColorManager::class)->setColor(...)
$builder->get_color(...)              → $builder->get(ColorManager::class)->getColor(...)

$builder->revert_file(...)            → $builder->get(RevisionManager::class)->revertFile(...)
$builder->recover_file(...)           → $builder->get(RevisionManager::class)->recoverFile(...)
$builder->reload_file(...)            → $builder->get(RevisionManager::class)->reloadFile(...)
$builder->save_file_revision(...)     → $builder->get(RevisionManager::class)->saveFileRevision(...)
$builder->create_temp_revision(...)   → $builder->get(RevisionManager::class)->createTempRevision(...)

$builder->sync_file(...)              → $builder->get(SyncService::class)->syncFile(...)
$builder->get_sync_dest(...)          → $builder->get(SyncService::class)->getSyncDest(...)

$builder->snr_search(...)             → $builder->get(SNRService::class)->search(...)
$builder->snr_revert()                → $builder->get(SNRService::class)->revert()
$builder->snr_get_regex(...)          → $builder->get(SNRService::class)->getRegex(...)
$builder->snr_replace(...)            → $builder->get(SNRService::class)->replace(...)

$builder->ssh_exec(...)               → $builder->get(SSHService::class)->sshExec(...)
$builder->ssh_exec_2(...)             → $builder->get(SSHService::class)->sshExec2(...)
$builder->private_ssh_exec(...)       → $builder->get(SSHService::class)->privateSshExec(...)
$builder->execute_linux(...)          → $builder->get(SSHService::class)->executeLinux(...)
$builder->ssh_ensure_safe_command(...)→ $builder->get(SSHService::class)->ensureSafeCommand(...)

$builder->exec(...)                   → $builder->get(ProcessManager::class)->exec(...)
$builder->save_pid(...)               → $builder->get(ProcessManager::class)->savePid(...)
$builder->is_stop_pending()           → $builder->get(ProcessManager::class)->isStopPending()

$builder->execGitCommand(...)         → $builder->get(GitService::class)->execGitCommand(...)
$builder->get_git_info(...)           → $builder->get(GitService::class)->getGitInfo(...)

$builder->flush(...)                  → $builder->getOutput()->flush(...)
$builder->flush_block(...)            → $builder->getOutput()->flushBlock(...)
$builder->flush_js_message(...)       → $builder->getOutput()->flushJsMessage(...)
$builder->flush_js_notification(...)  → $builder->getOutput()->flushJsNotification(...)

$builder->try_chmod(...)              → $builder->getFileOps()->tryChmod(...)
$builder->try_exec(...)               → $builder->getFileOps()->tryExec(...)
$builder->rename(...)                 → $builder->getFileOps()->rename(...)
$builder->is_empty_dir(...)           → $builder->getFileOps()->isEmptyDir(...)
$builder->getLocalizedPath(...)       → $builder->getFileOps()->getLocalizedPath(...)

$builder->getAbsolutePath(...)        → $builder->getRepoManager()->getAbsolutePath(...)
$builder->hasRepositoryPermission(...)→ $builder->getRepoManager()->hasRepositoryPermission(...)
$builder->getRepositoryFilePaths(...) → $builder->getRepoManager()->getRepositoryFilePaths(...)
$builder->getRepositoryWisePath(...)  → $builder->getRepoManager()->getRepositoryWisePath(...)
$builder->getFileRepository(...)      → $builder->getRepoManager()->getFileRepository(...)
$builder->getRepositoryCacheFile(...) → $builder->getRepoManager()->getRepositoryCacheFile(...)
$builder->getProjectFilePaths(...)    → $builder->getRepoManager()->getProjectFilePaths(...)

$builder->searchForFile(...)          → $builder->get(FileSearchService::class)->searchForFile(...)
$builder->searchForFiles(...)         → $builder->get(FileSearchService::class)->searchForFiles(...)
$builder->searchForFilesInArray(...)  → $builder->get(FileSearchService::class)->searchForFilesInArray(...)
$builder->rsearch(...)                → $builder->get(FileSearchService::class)->rsearch(...)
$builder->glob(...)                   → $builder->get(FileSearchService::class)->glob(...)
$builder->isExcludedPath(...)         → $builder->get(FileSearchService::class)->isExcludedPath(...)

$builder->getUserDataDir()            → $builder->getAuth()->getUserDataDir()
$builder->getUserUploadDir()          → $builder->getAuth()->getUserUploadDir()
$builder->getUserRepositoryDir()      → $builder->getAuth()->getUserRepositoryDir()
$builder->getUserTempDir()            → $builder->getAuth()->getUserTempDir()
$builder->getUserRevisionDir()        → $builder->getAuth()->getUserRevisionDir()
$builder->getUserTempRevisionDir()    → $builder->getAuth()->getUserTempRevisionDir()
$builder->getCurrentUser()            → $builder->getAuth()->getCurrentUser()
$builder->getCurrentUsername()        → $builder->getAuth()->getCurrentUsername()
$builder->get_public_user_info()      → $builder->getAuth()->getPublicUserInfo()
$builder->getUserUploadFiles()        → $builder->getEditorService()->getUserUploadFiles()
$builder->getUserTempFilePaths()      → $builder->getEditorService()->getUserTempFilePaths()
$builder->getNewFilePath()            → $builder->getEditorService()->getNewFilePath()

$builder->json_response(...)          → \CloudPad\Core\Response::json(...)
$builder->isMobile()                  → \CloudPad\Core\Request::isMobile()
```

**Chia thành 2 batches để giảm rủi ro:**

**Batch 1 — Commands đơn giản** (function-based, 1-2 builder calls mỗi file):
```
clone_file.php, close_file.php, delete_file.php, delete_user_file.php,
download_user_file.php, editor_clear_recents.php, editor_close_all.php,
file_live_search.php, fs_chmod.php, get_directory_children.php,
get_directory_structure.php, get_file_content.php, get_full_file_path.php,
new_temp_file.php, open_file_by_name.php, pin_to_quick_access.php,
rebuild_sub_indexes.php, recover_file.php, reload_file.php,
rename_file.php, revert_file.php, revert_sync_file.php,
save_current_file.php, set_color.php, snr_search.php,
sync_file.php, unpin_from_quick_access.php, upload_file.php,
upload_file_x.php
```

**Batch 2 — Commands phức tạp hơn** (nhiều builder calls, branching logic):
```
copy_files.php, diff.php, file_transfer.php, git_commit.php,
git_commit_all.php, git_diff.php, git_diff_all.php, git_log.php,
git_log_all.php, git_pull.php, git_remove_untracked.php,
git_revert.php, git_status.php, move_files.php, new_directory.php,
new_file.php, new_hugo_content.php, open_inline_file.php,
rebuild_filepaths_indexes.php, rename_directory.php,
rename_directory_of_file.php, save_inline_file.php,
user/index.php
```

### R2.4 — Tách diff() vào command handler

**Vấn đề:** `Builder::diff()` (lines 383-405) là business logic, không phải wrapper. Include `finediff.php`.

**Thao tác:** Inline logic vào `plugins/commands/diff.php`:
```php
<?php
function diff($builder) {
    $from_text = \CloudPad\Core\Request::getString('DIFF_FROM');
    $to_text   = \CloudPad\Core\Request::getString('DIFF_TO');

    if (empty($from_text) || empty($to_text)) {
        $builder->getOutput()->flushLine("[ERROR] Please specify both texts to compare\n", true);
        return;
    }

    \CloudPad\Core\Session\NativeSession::getInstance()->set('DIFF_FROM', $from_text);
    \CloudPad\Core\Session\NativeSession::getInstance()->set('DIFF_TO', $to_text);

    $from_text = htmlspecialchars($from_text, ENT_QUOTES, 'UTF-8');
    $to_text   = htmlspecialchars($to_text, ENT_QUOTES, 'UTF-8');

    $appDir = defined('BUILDER_DIR') ? BUILDER_DIR : dirname(dirname(__DIR__));
    include $appDir . '/finediff.php';

    $opcodes       = FineDiff::getDiffOpcodes($from_text, $to_text);
    $rendered_diff = FineDiff::renderDiffToHTMLFromOpcodes($from_text, $opcodes);

    echo '<div class="diff-response">' . $rendered_diff . '</div>';
}
```

### R2.5 — Migrate template calls

**`tpl/index.tpl`:** Giữ nguyên — các wrapper dùng ở đây đều trong danh sách "giữ lại".

**`plugins/tabs/editor/index.tpl`:** Sửa duy nhất 1 chỗ:
```php
// Line 159 — TRƯỚC:
files: <?php echo json_encode($builder->getEditorOpenFiles()); ?>,
// SAU (wrapper vẫn giữ, nên không cần sửa — nhưng nếu muốn explicit):
files: <?php echo json_encode($builder->getEditorService()->getEditorOpenFiles()); ?>,
```

**`plugins/tabs/snr/index.tpl` và `plugins/tabs/editor/index.tpl`:** Các chỗ gọi `$builder->getRepositories()` — wrapper giữ lại, không cần sửa.

### R2.6 — Builder cuối cùng (~150 dòng)

```
Builder final structure:
├── Constructor: Container init + resolve services (~30 dòng)
├── get() + 5 typed getters (~15 dòng)
├── ~10 wrapper methods phổ biến (~30 dòng)
├── ~5 template helper wrappers (~10 dòng)
├── Plugin discovery: has_plugin_fs, has_plugin_tab, getUserTabs (~60 dòng)
├── Static permission helpers (~20 dòng)
└── Lifecycle: auth, initProcessManager, load_language_file, serializeUserSessionData (~10 dòng)
Total: ~175 dòng (giảm 80% từ 881)
```

---

## 5. Phase R3: Refactor editor/index.tpl

**Mục tiêu:** Tách file 3665 dòng thành manageable pieces. HTML giữ nguyên trong index.tpl, tách 13 Vue components thành JS files riêng, gộp runtime bằng PHP include (zero extra HTTP requests).

### R3.1 — Cấu trúc mới

```
plugins/tabs/editor/
├── index.php              # Plugin class (extend BaseTabPlugin)
├── index.tpl              # ~180 dòng: HTML + PHP include components
└── components/
    ├── input-dialog.js
    ├── inline-ace-editor.js
    ├── git-diff-viewer.js
    ├── git-status-viewer.js
    ├── apply-patch-modal.js
    ├── console-output-viewer.js
    ├── command-center.js
    ├── search-workspace.js
    ├── context-menu.js
    ├── text-search-panel.js
    ├── explorer-files-search.js
    ├── file-item.js
    └── explorer-app.js        # Main Vue instance — PHẢI load cuối cùng
```

### R3.2 — Refactor index.tpl

```php
<?php if (\CloudPad\Builder::hasPermission('editor')) : ?>
<div id="editor" style="height:100%;display:flex;flex-direction:column;">

    <!-- ── Toolbar ────────────────────────────────────────── -->
    <div class="editor-file-bar commandbar">
        <!-- ...giữ nguyên HTML toolbar hiện tại (~40 dòng)... -->
    </div>

    <!-- ── Editor tabs + sidebar ──────────────────────────── -->
    <div id="dev_editor" class="editor-tabs" style="display:flex;flex-direction:column;">
        <!-- ...giữ nguyên HTML editor area (~100 dòng)... -->
    </div>

    <!-- ── Init editor ────────────────────────────────────── -->
    <script type="text/javascript">
        $(function() {
            window.dev_editor = new Editor({
                el: '#dev_editor',
                files: <?php echo json_encode($builder->getEditorOpenFiles()); ?>,
                style: 2
            })
            initSplitPanes(document.getElementById('dev_editor'), { minHeight: 30 })
        })
    </script>

    <!-- ── Vue components (gộp runtime, zero extra requests) ── -->
    <script>
    window.editorEventBus = new Vue();
    <?php
    // Load order quan trọng: base components trước, explorer-app cuối cùng
    $components = [
        'context-menu',
        'input-dialog',
        'inline-ace-editor',
        'git-diff-viewer',
        'git-status-viewer',
        'apply-patch-modal',
        'console-output-viewer',
        'command-center',
        'search-workspace',
        'text-search-panel',
        'explorer-files-search',
        'file-item',
        'explorer-app',  // PHẢI cuối cùng — khởi tạo Vue instance
    ];
    foreach ($components as $c) {
        include __DIR__ . '/components/' . $c . '.js';
        echo "\n";
    }
    ?>
    </script>
</div>
<?php endif; ?>
```

### R3.3 — Extract Vue components

**Thao tác cho mỗi component:**
1. Xác định boundaries trong file gốc (mỗi `Vue.component('xxx', { ... })` block)
2. Copy nguyên block vào `components/xxx.js`
3. Verify JS syntax hợp lệ (mở/đóng ngoặc đúng)

**Ví dụ `components/input-dialog.js`:**
```javascript
Vue.component('input-dialog', {
    props: {
        title: { type: String, default: 'Input' },
        // ... rest of props
    },
    template: `
        <div v-if="isOpen" ...>
            <!-- ... -->
        </div>
    `,
    data() {
        return {
            isOpen: false,
            // ...
        };
    },
    methods: {
        open(options = {}) { /* ... */ },
        confirm() { /* ... */ },
        cancel() { /* ... */ }
    },
    mounted() { /* ... */ },
    beforeDestroy() { /* ... */ }
});
```

**Ví dụ `components/explorer-app.js`** (cuối cùng — khởi tạo Vue instance):
```javascript
new Vue({
    el: '#explorer',
    data: {
        repository: '<?php echo session_get("repository", ""); ?>',
        directoryStructure: [],
        // ... rest of data
    },
    methods: {
        onChangeRepository() { /* ... */ },
        // ... rest of methods
    },
    mounted() { /* ... */ }
});
```

**Lưu ý cho `explorer-app.js`:** File này chứa PHP expressions (`<?php echo ... ?>`) vì nó được PHP-include. Đây là pattern hợp lệ trong context này — file `.js` nhưng thực chất là PHP-rendered JS.

### R3.4 — Fix `$_SESSION` trực tiếp trong templates

Thêm helper function vào `src/Core/helpers.php`:
```php
if (!function_exists('session_get')) {
    function session_get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }
}
```

**Sửa trong `plugins/tabs/editor/index.tpl`:**
```php
// TRƯỚC: $_SESSION['repository'] == $code ? 'selected' : ''
// SAU:   session_get('repository') == $code ? 'selected' : ''
```

**Sửa trong `plugins/tabs/snr/index.tpl`:**
```php
// TRƯỚC: isset($_SESSION['snr-batch-mode']) && $_SESSION['snr-batch-mode'] ? ...
// SAU:   session_get('snr-batch-mode') ? ...

// TRƯỚC: isset($_SESSION['search']) ? htmlspecialchars($_SESSION['search'], ...) : ''
// SAU:   htmlspecialchars(session_get('search', ''), ENT_QUOTES, 'UTF-8')
```

### R3.5 — Giữ `index2.php` references nguyên trạng

32 chỗ `index2.php` trong editor/index.tpl JS — giữ nguyên, xử lý sau nếu cần.

---

## 6. Phase R4: Fix globals & code smells

### R4.1 — Fix `global $ajax` trong EditorService

**File:** `src/Editor/EditorService.php:59`

```php
// TRƯỚC
public function openFileByName(string $filename, bool $fromcache, string $repository): void {
    global $ajax;
    // ...
    if ($ajax) {

// SAU
public function openFileByName(string $filename, bool $fromcache, string $repository): void {
    // ...
    if (\CloudPad\Core\Request::isAjax()) {
```

### R4.2 — Fix `_t()` dùng `global $builder`

**File:** `src/I18n/helpers.php`

**Vấn đề:** `_t()` dùng `global $builder` để lấy lang cho lazy-load (thêm missing key vào language file). Nhưng lang đã được lưu vào session bởi `loadLanguageFile()`. Dùng trực tiếp session + constant `BUILDER_DIR`.

```php
if (!function_exists('_t')) {
    function _t(string $key, bool $escape = false): string {
        global $_L;

        if (empty($key)) return '';

        if (!isset($_L[$key])) {
            // Lazy-load: thêm key vào language file nếu chưa có
            $session  = \CloudPad\Core\Session\NativeSession::getInstance();
            $lang     = $session->get('lang', 'en');
            $langfile = BUILDER_DIR . "/locales/{$lang}.php";

            if (file_exists($langfile)) {
                $safeKey  = addslashes($key);
                $content  = file_get_contents($langfile);
                $content .= "\$_L['{$safeKey}'] = '{$safeKey}';\n";
                file_put_contents($langfile, $content);
                $_L[$key] = $key;
            }

            $text = $key;
        } else {
            $text = $_L[$key];
        }

        return $escape ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8', true) : $text;
    }
}
```

### R4.3 — Fix debug echo trong EditorService::isSameContent()

**File:** `src/Editor/EditorService.php:694-695`

```php
// TRƯỚC
if ($content1 !== $content2) {
    echo "content1 = $content1<br/>";
    echo "content2 = $content2<br/>";
}

// SAU — xoá echo, chỉ return boolean
// (Debug output trong production code vi phạm SRP và gây unexpected output)
```

---

## 7. Phase R5: Cleanup & polish

### R5.1 — SSHService: extract shared logic

**File:** `src/SSH/SSHService.php`

**Vấn đề:** 3 public methods (`sshExec`, `privateSshExec`, `sshExec2`) có SSH connection setup + command execution loop gần giống nhau, nhưng dùng 2 auth strategies khác nhau:
- `sshExec()` + `privateSshExec()` → RSA key auth (qua constants)
- `sshExec2()` → Password auth (qua session)

**Thao tác — tách thành 3 private helpers:**

```php
class SSHService
{
    // ... existing properties ...

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Connect bằng RSA key (dùng constants SSH_RSA_*).
     */
    private function connectWithKey(): \phpseclib3\Net\SSH2
    {
        $ssh = new \phpseclib3\Net\SSH2('localhost', SSH_PORT);

        $rsaPrivateKey = file_get_contents(SSH_RSA_PRIVATE_FILE);
        if (empty($rsaPrivateKey)) {
            throw new \RuntimeException('Cannot read RSA private key file');
        }

        $key = \phpseclib3\Crypt\RSA::load($rsaPrivateKey, SSH_RSA_PASSPHRASE);

        if (!$ssh->login(SSH_RSA_USERNAME, $key)) {
            throw new \RuntimeException('SSH login with RSA key failed: ' . $ssh->getLastError());
        }

        $ssh->setWindowColumns(160);
        return $ssh;
    }

    /**
     * Connect bằng password (từ session).
     */
    private function connectWithPassword(): \phpseclib3\Net\SSH2
    {
        $host     = $this->sshSession->getHost();
        $port     = $this->sshSession->getPort();
        $username = $this->sshSession->getUsername();
        $password = $this->sshSession->getPassword();

        foreach (['SSH_HOST' => $host, 'SSH_PORT' => $port, 'SSH_USERNAME' => $username, 'SSH_PASSWORD' => $password] as $name => $value) {
            if (empty($value)) {
                throw new \RuntimeException("$name is required");
            }
        }

        $ssh = new \phpseclib3\Net\SSH2($host, $port);
        if (!$ssh->login($username, $password)) {
            throw new \RuntimeException('SSH login with password failed');
        }

        $ssh->setWindowColumns(160);
        return $ssh;
    }

    /**
     * Execute commands trên SSH connection đã có.
     * @return string Combined output
     */
    private function runCommands(\phpseclib3\Net\SSH2 $ssh, array $commands, bool $echo = true): string
    {
        $ptyRequiredCommands = ['top', 'sudo', 'svn'];
        $combinedOutput = '';

        foreach ($commands as $command) {
            $command = trim($command);
            if (empty($command) || $command[0] === '#') continue;

            $this->ensureSafeCommand($command);

            [$firstWord] = explode(' ', $command);
            $requirePty = in_array($firstWord, $ptyRequiredCommands);

            if ($requirePty) {
                $ssh->enablePTY();
                $ssh->exec($command);
                $ssh->setTimeout(100);
                $output = $ssh->read();
            } else {
                $output = $ssh->exec($command);
            }

            $output = $this->getAugmentedOutput($output, $command);
            $combinedOutput .= $output;

            if ($echo) {
                echo $output;
            }
        }

        return $combinedOutput;
    }

    // ── Public methods (giờ mỗi method ~10-15 dòng) ────────────────

    public function sshExec(bool $execBuiltin = true, bool $execCustom = true): void
    {
        // ... gather commands from request (giữ nguyên logic hiện tại) ...
        // ... gather + validate host/port/username/password from request ...

        $ssh = $this->connectWithKey();
        $this->runCommands($ssh, $actualCommands);

        // Save session params
        $this->sshSession->setHost($host);
        $this->sshSession->setPort($port);
        $this->sshSession->setUsername($username);
        $this->sshSession->setPassword($password);
    }

    public function privateSshExec(array|string $commands): void
    {
        if (is_string($commands)) {
            $commands = explode("\n", $commands);
        }
        $ssh = $this->connectWithKey();
        $this->runCommands($ssh, $commands);
    }

    public function sshExec2(array|string $commands, bool $verbose = true): string
    {
        static $ssh = null;
        if ($ssh === null) {
            $ssh = $this->connectWithPassword();
        }

        $commandStr = is_array($commands) ? implode(';', $commands) : $commands;
        return $this->runCommands($ssh, [$commandStr], $verbose);
    }

    // ... ensureSafeCommand, executeLinux, getAugmentedOutput giữ nguyên ...
}
```

### R5.2 — Namespace cho Builder

**Thao tác:**
1. Thêm `namespace CloudPad;` vào đầu `src/Builder.php`
2. Update references:
   - `src/App.php`: `\Builder` → `\CloudPad\Builder` (2 chỗ: property type + `new`)
   - `src/Core/Router.php`: `\Builder` → `\CloudPad\Builder` (property type)
   - `src/Plugin/BaseFilesystemPlugin.php`: docblock `@var \Builder` → `@var \CloudPad\Builder`
3. Update `composer.json` autoload:
```json
{
    "autoload": {
        "psr-4": { "CloudPad\\": "src/" },
        "files": ["src/Core/helpers.php", "src/I18n/helpers.php"]
    }
}
```
4. Xoá `require_once __DIR__ . '/src/Builder.php';` khỏi `index.php`
5. Chạy `composer dump-autoload`

**Lưu ý:** ~50 command plugins nhận `$builder` parameter — chúng KHÔNG cần sửa vì chỉ gọi methods trên object, không reference class name.

### R5.3 — Fix deprecated `mb_convert_encoding`

**File:** `plugins/commands/diff.php` (sau R2.4 di chuyển từ Builder::diff())

```php
// TRƯỚC
$from_text = mb_convert_encoding($from_text, 'HTML-ENTITIES', 'UTF-8');
$to_text   = mb_convert_encoding($to_text,   'HTML-ENTITIES', 'UTF-8');

// SAU — dùng htmlspecialchars (đủ cho diff rendering)
$from_text = htmlspecialchars($from_text, ENT_QUOTES, 'UTF-8');
$to_text   = htmlspecialchars($to_text,   ENT_QUOTES, 'UTF-8');
```

---

## 8. Phase R6: Unit tests

### R6.1 — Setup

```bash
composer require --dev phpunit/phpunit ^10
mkdir -p tests/Unit tests/Support
```

**`phpunit.xml`:**
```xml
<?xml version="1.0"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### R6.2 — ArraySession (test double)

**`tests/Support/ArraySession.php`:**
```php
<?php
namespace Tests\Support;

use CloudPad\Core\Session\SessionInterface;

class ArraySession implements SessionInterface
{
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }
    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }
    public function has(string $key): bool {
        return isset($this->data[$key]);
    }
    public function remove(string $key): void {
        unset($this->data[$key]);
    }
    public function all(): array {
        return $this->data;
    }
}
```

### R6.3 — Test priority matrix

| Priority | Test class | Methods covered | Lý do |
|----------|-----------|-----------------|-------|
| **P0** | `FileSearchServiceTest` | `searchForFile`, `searchForFiles`, `searchForFilesInArray`, `isExcludedPath` | Core search logic, pure functions |
| **P0** | `RepositoryManagerPathTest` | `getAbsolutePath`, `getAbsoluteFilePath`, `getRepositoryWisePath` | Path resolution phức tạp nhất, dễ regression khi refactor |
| **P0** | `RequestTest` | `getString`, `require`, `getInt`, `getBool`, `getArray`, `isAjax` | Input validation, security |
| **P1** | `ContainerTest` | `singleton`, `get`, `has`, `instance` | DI wiring correctness |
| **P1** | `EditorContentTest` | `buildDirectoryStructure`, `convertTabsToWhitespaces`, `trimTrailingWhitespaces`, `trimTrailingCommas`, `isSameContent`, `onBeforeSavingFile` | Content transformations |
| **P1** | `RevisionManagerTest` | `getRevisionPrefix`, `getRevisionCount` | Naming/counting logic |
| **P2** | `GitServiceTest` | `getGitInfo` | Path normalization |
| **P2** | `AuthSessionStoreTest` | `isAuthed`, `getUsername`, `getRepositories` | Session data integrity |
| **P2** | `TranslatorTest` | `getLang`, `getBrowserLanguage` | I18n fallback logic |

### R6.4 — P0 test implementations

**`tests/Unit/Search/FileSearchServiceTest.php`:**
```php
<?php
namespace Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use CloudPad\Search\FileSearchService;
use CloudPad\Core\Output\OutputManager;

class FileSearchServiceTest extends TestCase
{
    private FileSearchService $service;

    protected function setUp(): void
    {
        $this->service = new FileSearchService(new OutputManager());
    }

    public function test_exact_match_by_basename(): void
    {
        $files = ['/app/foo.php', '/app/bar.php', '/app/sub/foo.php'];
        $result = $this->service->searchForFilesInArray('foo.php', $files, 0, true);
        $this->assertCount(2, $result);
        $this->assertContains('/app/foo.php', $result);
        $this->assertContains('/app/sub/foo.php', $result);
    }

    public function test_partial_match_case_insensitive(): void
    {
        $files = ['/app/FooBar.php', '/app/foo.txt', '/app/bar.php'];
        $result = $this->service->searchForFilesInArray('foo', $files, 0, false);
        $this->assertCount(2, $result);
    }

    public function test_limit_results(): void
    {
        $files = ['/a/foo.php', '/b/foo.php', '/c/foo.php'];
        $result = $this->service->searchForFilesInArray('foo', $files, 2, false);
        $this->assertCount(2, $result);
    }

    public function test_wildcard_pattern(): void
    {
        $files = ['/app/test.php', '/app/test.js', '/app/test.css'];
        $result = $this->service->searchForFilesInArray('*.php', $files, 0, false);
        $this->assertCount(1, $result);
        $this->assertEquals('/app/test.php', $result[0]);
    }

    public function test_path_match_with_slash(): void
    {
        $files = ['/app/src/models/User.php', '/app/src/controllers/UserController.php'];
        $result = $this->service->searchForFilesInArray('src/models', $files, 0, false);
        $this->assertCount(1, $result);
    }

    public function test_empty_search_returns_all(): void
    {
        // empty string matches everything via stripos
        $files = ['/a.php', '/b.php'];
        $result = $this->service->searchForFilesInArray('', $files, 0, false);
        $this->assertCount(2, $result);
    }

    public function test_isExcludedPath_basic(): void
    {
        $this->assertTrue($this->service->isExcludedPath('/app/node_modules/foo', ['node_modules'], []));
        $this->assertFalse($this->service->isExcludedPath('/app/src/foo.php', ['node_modules'], []));
    }

    public function test_isExcludedPath_include_overrides_exclude(): void
    {
        $this->assertFalse($this->service->isExcludedPath(
            '/app/vendor/important.php',
            ['vendor'],
            ['vendor/important']
        ));
    }

    public function test_isExcludedPath_no_rules(): void
    {
        $this->assertFalse($this->service->isExcludedPath('/any/path', [], []));
    }
}
```

**`tests/Unit/Core/ContainerTest.php`:**
```php
<?php
namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use CloudPad\Core\Container;

class ContainerTest extends TestCase
{
    public function test_singleton_returns_same_instance(): void
    {
        $c = new Container();
        $c->singleton('service', fn() => new \stdClass());

        $a = $c->get('service');
        $b = $c->get('service');
        $this->assertSame($a, $b);
    }

    public function test_get_unregistered_throws(): void
    {
        $c = new Container();
        $this->expectException(\RuntimeException::class);
        $c->get('nonexistent');
    }

    public function test_has(): void
    {
        $c = new Container();
        $this->assertFalse($c->has('foo'));
        $c->singleton('foo', fn() => new \stdClass());
        $this->assertTrue($c->has('foo'));
    }

    public function test_instance(): void
    {
        $c = new Container();
        $obj = new \stdClass();
        $obj->value = 42;
        $c->instance('thing', $obj);
        $this->assertSame($obj, $c->get('thing'));
    }

    public function test_factory_receives_container(): void
    {
        $c = new Container();
        $c->singleton('dep', fn() => (object)['x' => 1]);
        $c->singleton('main', fn($c) => (object)['dep' => $c->get('dep')]);

        $main = $c->get('main');
        $this->assertEquals(1, $main->dep->x);
    }
}
```

**`tests/Unit/Editor/EditorContentTest.php`:**
```php
<?php
namespace Tests\Unit\Editor;

use PHPUnit\Framework\TestCase;

/**
 * Test content transformation methods trên EditorService.
 * Sử dụng reflection hoặc mock để test riêng các pure functions.
 */
class EditorContentTest extends TestCase
{
    private \CloudPad\Editor\EditorService $service;

    protected function setUp(): void
    {
        // EditorService cần nhiều deps — dùng mock cho constructor
        $this->service = new \CloudPad\Editor\EditorService(
            $this->createMock(\CloudPad\Repository\RepositoryManagerInterface::class),
            $this->createMock(\CloudPad\FileSystem\FileOperationsInterface::class),
            $this->createMock(\CloudPad\Search\FileSearchServiceInterface::class),
            $this->createMock(\CloudPad\Auth\AuthServiceInterface::class),
            $this->createMock(\CloudPad\Editor\ColorManager::class),
            $this->createMock(\CloudPad\Editor\RevisionManager::class),
            $this->createMock(\CloudPad\Core\Output\OutputManager::class),
            $this->createMock(\CloudPad\Core\Session\EditorSessionStore::class)
        );
    }

    public function test_convertTabsToWhitespaces(): void
    {
        $this->assertEquals('    x', $this->service->convertTabsToWhitespaces("\tx"));
        $this->assertEquals('        x', $this->service->convertTabsToWhitespaces("\t\tx"));
    }

    public function test_trimTrailingWhitespaces(): void
    {
        $input    = "hello   \nworld  \n  foo  ";
        $expected = "hello\nworld\n  foo";
        $this->assertEquals($expected, $this->service->trimTrailingWhitespaces($input));
    }

    public function test_trimTrailingCommas(): void
    {
        $input    = "[\n    'a',\n    'b',\n]";
        $expected = "[\n    'a',\n    'b'\n]";
        $this->assertEquals($expected, $this->service->trimTrailingCommas($input));
    }

    public function test_isSameContent_identical(): void
    {
        $this->assertTrue($this->service->isSameContent('hello', 'hello'));
    }

    public function test_isSameContent_whitespace_difference(): void
    {
        $this->assertTrue($this->service->isSameContent("  hello\n", "hello"));
    }

    public function test_isSameContent_different(): void
    {
        $this->assertFalse($this->service->isSameContent('hello', 'world'));
    }

    public function test_buildDirectoryStructure(): void
    {
        $paths  = ['/src/App.php', '/src/Core/Router.php', '/README.md'];
        $result = $this->service->buildDirectoryStructure($paths);

        $this->assertCount(2, $result); // 'src' dir + 'README.md' file
        $srcNode = $result[0];
        $this->assertEquals('src', $srcNode['name']);
        $this->assertCount(2, $srcNode['children']); // App.php + Core/
    }

    public function test_onBeforeSavingFile_php(): void
    {
        $content = "\tclass Foo {\n\t\treturn true;  \n}  \n";
        $this->service->onBeforeSavingFile($content, 'php');

        $this->assertStringNotContainsString("\t", $content);       // tabs → spaces
        $this->assertStringNotContainsString("  \n", $content);     // no trailing ws
        $this->assertStringEndsWith("\n", $content);                 // trailing newline
    }

    public function test_onBeforeSavingFile_nonweb(): void
    {
        $content = "\tdata  \n";
        $this->service->onBeforeSavingFile($content, 'txt');

        $this->assertStringContainsString("\t", $content);           // tabs preserved
        $this->assertStringNotContainsString("  \n", $content);     // trailing ws still trimmed
    }
}
```

---

## 9. Execution order & effort

### Dependency graph

```
R1 (Xoá duplicate + migrate plugins)    ← Không dependency, làm trước
     │
R2 (Decompose Builder)                  ← Depends on R1
     │
R3 (editor/index.tpl)                   ← Independent, có thể song song với R2
     │
R4 (Fix globals)                        ← Depends on R2
     │
R5 (Cleanup & polish)                   ← Depends on R2, R4
     │
R6 (Tests)                              ← Bắt đầu ngay sau R1, mở rộng dần
```

### Effort estimate

| Phase | Effort | Risk | Notes |
|-------|--------|------|-------|
| **R1** | 1.5 giờ | Low | Xoá code + sửa 7 plugin files |
| **R2** | 3-4 giờ | Medium | Batch 1 commands (~30 files) + Batch 2 (~20 files) |
| **R3** | 2 giờ | Medium | Tách 13 Vue components, verify JS boundaries |
| **R4** | 30 phút | Low | 3 fixes nhỏ |
| **R5** | 1.5 giờ | Medium | SSHService refactor cần test, Builder namespace |
| **R6** | 2 giờ | Low | Test infrastructure + P0/P1 tests |
| **Total** | **~10-12 giờ** | |

### Verification checklist (chạy sau MỖI phase)

- [ ] `composer dump-autoload` thành công
- [ ] Không có PHP fatal/syntax errors khi load `index.php`
- [ ] Login flow hoạt động
- [ ] Editor tab render đúng
- [ ] Mở file, save file thành công
- [ ] File tree sidebar hiển thị đúng
- [ ] Search & Replace tab hoạt động
- [ ] Git operations (status, commit) hoạt động
- [ ] Context menu hoạt động (right-click trong file tree)
- [ ] `vendor/bin/phpunit` pass (sau R6)

---

## Appendix: Command files inventory

Danh sách tất cả command files cần update trong R2.3:

**Batch 1 — Đơn giản (function-based, 1-2 builder calls):**
```
plugins/commands/clone_file.php
plugins/commands/close_file.php
plugins/commands/delete_file.php
plugins/commands/delete_user_file.php
plugins/commands/download_user_file.php
plugins/commands/editor_clear_recents.php
plugins/commands/editor_close_all.php
plugins/commands/file_live_search.php
plugins/commands/fs_chmod.php
plugins/commands/get_directory_children.php
plugins/commands/get_directory_structure.php
plugins/commands/get_file_content.php
plugins/commands/get_full_file_path.php
plugins/commands/new_temp_file.php
plugins/commands/open_file_by_name.php
plugins/commands/pin_to_quick_access.php
plugins/commands/rebuild_sub_indexes.php
plugins/commands/recover_file.php
plugins/commands/reload_file.php
plugins/commands/rename_file.php
plugins/commands/revert_file.php
plugins/commands/revert_sync_file.php
plugins/commands/save_current_file.php
plugins/commands/set_color.php
plugins/commands/snr_search.php
plugins/commands/sync_file.php
plugins/commands/unpin_from_quick_access.php
plugins/commands/upload_file.php
plugins/commands/upload_file_x.php
```

**Batch 2 — Phức tạp hơn (nhiều builder calls, branching):**
```
plugins/commands/copy_files.php
plugins/commands/diff.php
plugins/commands/file_transfer.php
plugins/commands/git_commit.php
plugins/commands/git_commit_all.php
plugins/commands/git_diff.php
plugins/commands/git_diff_all.php
plugins/commands/git_log.php
plugins/commands/git_log_all.php
plugins/commands/git_pull.php
plugins/commands/git_remove_untracked.php
plugins/commands/git_revert.php
plugins/commands/git_status.php
plugins/commands/move_files.php
plugins/commands/new_directory.php
plugins/commands/new_file.php
plugins/commands/new_hugo_content.php
plugins/commands/open_inline_file.php
plugins/commands/rebuild_filepaths_indexes.php
plugins/commands/rename_directory.php
plugins/commands/rename_directory_of_file.php
plugins/commands/save_inline_file.php
plugins/commands/user/index.php
```
