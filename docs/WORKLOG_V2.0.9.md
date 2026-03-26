# WORKLOG — v2.0.9

> **Project:** CloudPad9
> **Date:** 2026-03-26
> **Author:** Claude (Sonnet 4.6)
> **Status:** ✅ Done

---

## 1. Problem Statement

### Vấn đề hiện tại
Sau v2.0.8 (Phase 11 — Session Abstraction), còn 2 phases tồn đọng:

**Phase 14 — Builder Cleanup:** Builder vẫn chứa routing logic (`is_plugin_command`, `execute_plugin_command`, `standalone_editor`) và các utility methods nhỏ (`isMobile`, `get_public_user_info`) không thuộc về Builder.

**Phase 16 — Security Hardening:**
- `chmod 777` hardcoded ở 6+ nơi — để world-writable không cần thiết
- `X-XSS-Protection: 0` header — disable XSS protection của browser
- `execute.sh` wrapper không có safety checks, không có logging, không có timeout

### Mục tiêu
- [x] Phase 14: Tách routing logic ra khỏi Builder → Router
- [x] Phase 14: `Request::isMobile()` static method
- [x] Phase 14: Builder chỉ còn thin wrappers
- [x] Phase 16: `chmod 777` → `664`/`775` + cap tối đa ở `0775`
- [x] Phase 16: Thêm security headers (`X-Frame-Options`, `Referrer-Policy`)
- [x] Phase 16: Harden `execute.sh` với safety checks + logging + timeout

---

## 2. Phân tích Giải pháp

### Phase 14: Routing Logic

**Vấn đề:** `is_plugin_command()` (50+ dòng) và `execute_plugin_command()` là routing logic nhưng nằm trong Builder. Router phải gọi lại Builder để dispatch.

**Giải pháp:** Tách `is_plugin_command` logic vào `Router::resolvePluginCommand()`. Builder giữ backward-compat wrappers delegate sang Router.

### Phase 16: chmod 777

**Vấn đề:** `chmod 777` đặt write permission cho tất cả (owner/group/world). Web server không cần world-write — chỉ cần owner/group có write.

**Giải pháp:**
- Files: `777` → `664` (owner/group rw, world read-only)
- Dirs: `777` → `775` (owner/group rwx, world rx)
- `tryChmod()` có hard cap ở `0775` — ngay cả khi caller truyền `777`, sẽ bị clip xuống `775`

---

## 3. Việc Đã Làm (Done)

### Phase 14 — Builder Cleanup

- [x] `Request::isMobile()` — static method mới trong `Request.php` (tách từ `Builder::isMobile()`)
- [x] `Router::resolvePluginCommand()` — toàn bộ plugin command lookup logic (50+ dòng) tách từ Builder
- [x] `Router::renderStandaloneEditor()` — private, thay thế `Builder::standalone_editor()`
- [x] `Router::dispatch()` — gọi trực tiếp `resolvePluginCommand()`, không còn qua Builder
- [x] Builder: `isMobile()` → `Request::isMobile()` (1 dòng)
- [x] Builder: `standalone_editor()` → removed (Router xử lý trực tiếp)
- [x] Builder: `get_public_user_info()` → `_auth->getPublicUserInfo()` (1 dòng)
- [x] Builder: `getCurrentUser/Username()` → `_auth->getCurrentUser/Username()` (1 dòng each)
- [x] Builder: `is_plugin_command/execute_plugin_command` → thin wrappers gọi `Router::resolvePluginCommand()`

### Phase 16 — Security Hardening

#### 16.1 chmod 777 → 664/775
| File | Thay đổi |
|---|---|
| `src/FileSystem/FileOperations.php` | `tryChmod('777', $file)` → `'664'`; `tryChmod('777', $dir)` → `'775'`; `mkdir(0777)` → `0775` |
| `src/Editor/ColorManager.php` | `mkdir(0777)` → `0775` |
| `src/Editor/SyncService.php` | `mkdir(0777)` → `0775` |
| `src/Auth/AuthService.php` | `mkdir(0777)` → `0775` |
| `plugins/commands/new_file.php` | `try_chmod(777)` → `try_chmod('775')` |

#### 16.2 tryChmod hard cap
```php
// FileOperations.php — PHP không bao giờ set chmod > 0775
private const SAFE_MAX_MODE = 0775;

public function tryChmod(string $mode, string $filepath): bool
{
    $octal = octdec($mode);
    if ($octal > self::SAFE_MAX_MODE) {
        $octal = self::SAFE_MAX_MODE;
        $mode  = decoct($octal);
    }
    return $this->tryExec('chmod ' . escapeshellarg($mode) . ' ' . escapeshellarg($filepath));
}
```

#### 16.3 Security headers
```php
// App.php configurePhp() — thay thế header("X-XSS-Protection: 0")
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
// NOTE: CSP chưa thêm — cần Phase 15 (Frontend refactor) trước
```

#### 16.4 execute.sh hardened
```bash
# Trước: $COMMAND $ARGS (không check, không log)
# Sau:
- Reject blank command
- Reject dangerous patterns (rm -rf /)
- Log mọi execution qua logger (syslog)
- Timeout 30s (configurable qua CLOUDPAD_EXEC_TIMEOUT)
```

### Các thay đổi đáng chú ý

| File | Thay đổi | Phase |
|---|---|---|
| `src/Core/Request.php` | Thêm `isMobile()` static method | 14 |
| `src/Core/Router.php` | Thêm `resolvePluginCommand()` + `renderStandaloneEditor()` | 14 |
| `src/Builder.php` | 5 methods → thin wrappers/delegates | 14 |
| `src/FileSystem/FileOperations.php` | chmod 664/775, `SAFE_MAX_MODE` cap | 16 |
| `src/Editor/ColorManager.php` | mkdir 0775 | 16 |
| `src/Editor/SyncService.php` | mkdir 0775 | 16 |
| `src/Auth/AuthService.php` | mkdir 0775 | 16 |
| `plugins/commands/new_file.php` | try_chmod 775 | 16 |
| `src/App.php` | 3 security headers | 16 |
| `execute.sh` | Safety checks + logging + timeout | 16 |

---

## 4. Việc Còn Lại & Future Work

### 🔧 Việc cần làm tiếp

- [ ] **Phase 15 — Frontend Modularization** (lớn nhất còn lại)
  - Tách `builder.js` 2558 dòng thành 10+ ES modules
  - Loại bỏ Vue 2 (loaded nhưng gần như không dùng)
  - Sau Phase 15 mới có thể thêm Content-Security-Policy header

- [ ] **Phase 17 — Public dir restructure**
  - Tạo `public/` directory làm web root
  - Move CSS/JS/images vào `public/`
  - Update nginx/apache config

- [ ] **Phase 18 — Cleanup & Documentation**
  - Xoá các backward-compat stubs (`git_commit_all.php`, etc.)
  - Viết PHPDoc đầy đủ cho interfaces
  - README update

### ⚠️ Known Issues / Risks

- **Content-Security-Policy** chưa được set — cần Phase 15 (frontend refactor) để loại bỏ inline scripts trước khi có thể bật CSP nghiêm ngặt.
- **Builder backward-compat stubs** (`is_plugin_command`, `execute_plugin_command`) mỗi lần gọi đều tạo `new Router($this)` — có thể optimize sau bằng cách cache Router instance trong Builder.
- **execute.sh path** hardcoded là `/usr/local/bin/execute.sh` trong `FileOperations.php` — cần đảm bảo file được deploy đúng path.

---

## 5. Roadmap & Next Steps

```
Phase 9.1  (v2.0.4): Fix Missing Methods             ✅
Phase 5    (v2.0.6): Merge Duplicate Commands         ✅
Phase 6.3  (v2.0.6): Exception Pattern Migration     ✅
Phase 8    (v2.0.6): Bootstrap Separation             ✅
Phase 9.2-9.5 (v2.0.6): Break Builder God Object     ✅
Phase 10   (v2.0.7): Loại Bỏ Circular Dependency     ✅
Phase 11   (v2.0.8): Session Abstraction              ✅
Phase 14   (v2.0.9): Builder Cleanup                  ✅
Phase 16   (v2.0.9): Security Hardening               ✅

Phase 15   (v2.1.0): Frontend Modularization          ⬜ Next
  └─ builder.js 2558L → 10+ modules
  └─ Loại Vue 2 unused
  └─ Sau đó: Content-Security-Policy

Phase 17   (v2.1.x): Public dir restructure           ⬜
Phase 18   (v3.0.0): Cleanup & Docs                   ⬜
```

---

## 6. Ghi Chú Kỹ Thuật

### Metric so sánh

| Metric | v2.0.8 | v2.0.9 |
|---|---|---|
| `chmod 777` trong codebase | 6 locations | **0** |
| `X-XSS-Protection: 0` header | ✅ đã bỏ từ v2.0.6 | ✅ confirmed gone |
| Security headers | 1 (nosniff) | **3** (nosniff, SAMEORIGIN, Referrer-Policy) |
| `execute.sh` safety | Không có | **Reject dangerous, log, timeout** |
| Routing logic trong Builder | ~50 dòng | **0** (delegate to Router) |
| `Request::isMobile()` | ❌ (trong Builder) | ✅ `Request::isMobile()` |

### execute.sh cấu hình

```bash
# Timeout có thể cấu hình qua env var:
CLOUDPAD_EXEC_TIMEOUT=60 php index.php  # tăng timeout lên 60s

# Log output:
journalctl -t cloudpad-execute -f  # xem log realtime
```

### Permission model sau Phase 16

```
Files (PHP/HTML/etc): 664  — owner/group rw, world read-only
Directories:          775  — owner/group rwx, world rx
User data dirs (tmp): 775  — web server cần write
Hard cap trong code:  tryChmod() không bao giờ > 775
```
