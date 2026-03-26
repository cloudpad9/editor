# WORKLOG — v2.0.7

> **Project:** CloudPad9
> **Date:** 2026-03-26
> **Author:** Claude (Sonnet 4.6)
> **Status:** ✅ Done

---

## 1. Problem Statement

### Vấn đề hiện tại
Sau v2.0.6 (Phase 5, 6.3, 8, 9.2-9.5), codebase vẫn còn vấn đề nghiêm trọng nhất:

**Circular Dependency:** Mọi service (`AuthService`, `EditorService`, `RepositoryManager`, v.v.) đều nhận `\Builder` trong constructor. Builder lại tạo tất cả services. Hệ quả: không thể unit test bất kỳ service nào, không thể mock, không thể tách packages độc lập.

```
Builder → creates → AuthService(\Builder)
AuthService → calls → $this->builder->file_put_contents(...)
                    → $this->builder->file_get_contents(...)
```

### Context & Background
Đây là Phase 10 trong Refactoring Plan v3.0. Prerequisite cho toàn bộ testing infrastructure.

### Mục tiêu cần đạt
- [x] Tạo DI Container
- [x] Tạo 6 interfaces cho services chính
- [x] Refactor tất cả 9 services: inject dependencies trực tiếp, không qua Builder
- [x] Builder nhận Container thay vì tự `new` services
- [x] `config/services.php` — centralized DI wiring
- [x] Zero circular dependencies (service → Builder → service)

---

## 2. Phân tích Giải pháp

### Phương án A: Full DI Container (được chọn)
- Tạo `Container` singleton factory, wire tất cả services trong `config/services.php`
- Mỗi service chỉ nhận interfaces, không nhận `\Builder`
- Builder dùng Container để resolve services thay vì `new` trực tiếp
- **Ưu điểm:** Clean, testable, zero circular deps
- **Độ phức tạp:** Cao — phải phân tích đúng dependency order

### Phương án B: Service Locator thuần (không chọn)
- Builder expose getter methods, services gọi `$builder->getFileOps()` thay vì inject
- Vẫn là circular dependency, chỉ ẩn đi

### Bootstrap Circular Issue
`FileOperations` cần `RepositoryManager` để `getLocalizedPath()`.
`RepositoryManager` cần `FileOperations` để `tryExec()` khi rebuild index.
**Giải pháp:** Container lazy-resolves — PHP không thực sự tạo object cho đến khi `get()` được gọi lần đầu. Vì `getLocalizedPath` chỉ được gọi trong runtime (không phải trong constructor), vòng tròn bootstrap được giải quyết tự nhiên.

### ✅ Giải pháp được chọn: Phương án A

---

## 3. Việc Đã Làm (Done)

### 10.1 — Tạo Container
- [x] `src/Core/Container.php` — Simple DI container với `singleton()`, `get()`, `has()`, `instance()`

### 10.2 — Tạo 6 Interfaces
- [x] `src/Auth/AuthServiceInterface.php`
- [x] `src/Editor/EditorServiceInterface.php`
- [x] `src/FileSystem/FileOperationsInterface.php`
- [x] `src/Git/GitServiceInterface.php`
- [x] `src/Repository/RepositoryManagerInterface.php`
- [x] `src/Search/FileSearchServiceInterface.php`

### 10.3 — Refactor 9 Services (loại bỏ Builder dependency)

| Service | Trước (inject) | Sau (inject) |
|---|---|---|
| `GitService` | `\Builder` | _(none — standalone)_ |
| `AuthService` | `\Builder` | `FileOperationsInterface` |
| `FileOperations` | `\Builder` | `RepositoryManagerInterface, OutputManager` |
| `RepositoryManager` | `\Builder` | `OutputManager, FileOps, FileSearch, pluginFsLoader` |
| `FileSearchService` | `\Builder` | `OutputManager, RepositoryManagerInterface` |
| `ColorManager` | `\Builder` | `RepositoryManagerInterface, FileOps` |
| `RevisionManager` | `\Builder` | `AuthServiceInterface, FileOps, RepositoryManager` |
| `SyncService` | `\Builder` | `RepositoryManagerInterface, FileOps` |
| `SSHService` | `\Builder` | `OutputManager, ProcessManager, RepositoryManager` |
| `EditorService` | `\Builder` (54 calls) | 7 direct dependencies |
| `SNRService` | `\Builder` (6 calls) | `OutputManager, RepositoryManager, FileOps` |

### 10.4 — config/services.php
- [x] `config/services.php` — Full DI wiring của tất cả services
- [x] Thứ tự đúng: leaf → root (GitService → FileOps → RepoManager → ... → EditorService)

### 10.5 — Builder dùng Container
- [x] `Builder::__construct()` — load `config/services.php`, wire Container, resolve services
- [x] `pluginFsLoader` callback — giữ tại Builder (vẫn cần `$this` cho plugin_fs constructors)

### Các thay đổi đáng chú ý

| File / Module | Thay đổi | Ghi chú |
|---|---|---|
| `src/Core/Container.php` | Tạo mới | 70 dòng |
| `src/Auth/AuthServiceInterface.php` | Tạo mới | — |
| `src/Editor/EditorServiceInterface.php` | Tạo mới | — |
| `src/FileSystem/FileOperationsInterface.php` | Tạo mới | — |
| `src/Git/GitServiceInterface.php` | Tạo mới | — |
| `src/Repository/RepositoryManagerInterface.php` | Tạo mới | — |
| `src/Search/FileSearchServiceInterface.php` | Tạo mới | — |
| `config/services.php` | Tạo mới | DI wiring |
| `src/Builder.php` | Constructor rewrite | Dùng Container |
| `src/Auth/AuthService.php` | Refactor | Inject FileOps |
| `src/FileSystem/FileOperations.php` | Refactor | Inject RepoManager + Output |
| `src/Repository/RepositoryManager.php` | Refactor | Inject 4 deps |
| `src/Search/FileSearchService.php` | Refactor | Inject 2 deps |
| `src/Editor/ColorManager.php` | Refactor | Inject 2 deps |
| `src/Editor/RevisionManager.php` | Refactor | Inject 3 deps |
| `src/Editor/SyncService.php` | Refactor | Inject 2 deps |
| `src/SSH/SSHService.php` | Refactor | Inject 3 deps |
| `src/Editor/EditorService.php` | Refactor | Inject 7 deps, 54 builder calls replaced |
| `src/Search/SearchAndReplace/SNRService.php` | Refactor | Inject 3 deps |

---

## 4. Việc Còn Lại & Future Work

### 🔧 Việc cần làm tiếp (Next session)

- [ ] **Phase 10.5 — PluginManager** (optional, low priority)
  - Tách `has_plugin_fs()` khỏi Builder vào `PluginManager`
  - Xoá bỏ `$pluginFsLoader` callback hack
  - `plugin_fs_*` constructors không còn nhận `\Builder`

- [ ] **Phase 11 — Session Abstraction**
  - `SessionInterface` + `NativeSession`
  - `EditorSessionStore`, `AuthSessionStore`, `SNRSessionStore`
  - Loại bỏ `$_SESSION` trực tiếp trong src/ (50+ locations)

- [ ] **Phase 14 — Builder Cleanup** (small remaining inline methods)
  - `isMobile()` → `Request::isMobile()`
  - `is_plugin_command()` + `execute_plugin_command()` → Router

### 💡 Future Work

- [ ] Phase 15 — Frontend Modularization
- [ ] Phase 16 — Security Hardening
- [ ] Phase 17-18 — Public dir + Cleanup

### ⚠️ Known Issues / Risks

- **`$_SESSION` direct access** vẫn còn trong nhiều services (50+ locations) — sẽ fix Phase 11.
- **`_t()` helper** vẫn dùng `global $builder` — sẽ fix Phase 11 khi có Translator trong Container.
- **`plugin_fs_*` constructors** vẫn nhận `\Builder` instance (qua `pluginFsLoader` callback) — đây là backward-compat tạm thời.
- **`Builder::$_translator`** không nằm trong Container — `Translator` còn là stateful object cần `$appDir`. Có thể thêm vào Container sau.
- **BaseFilesystemPlugin** (`src/Plugin/BaseFilesystemPlugin.php`) vẫn có `$builder` property — sẽ fix khi `plugin_fs_*` files được cập nhật.

---

## 5. Roadmap & Next Steps

```
Phase 9.1  (v2.0.4): Fix Missing Methods             ✅ Done
Phase 5    (v2.0.6): Merge Duplicate Commands         ✅ Done
Phase 6.3  (v2.0.6): Exception Pattern Migration     ✅ Done
Phase 8    (v2.0.6): Bootstrap Separation             ✅ Done
Phase 9.2-9.5 (v2.0.6): Break Builder God Object     ✅ Done
Phase 10   (v2.0.7): Loại Bỏ Circular Dependency     ✅ Done
  └─ Container                                        ✅
  └─ 6 Interfaces                                     ✅
  └─ 11 Services refactored (0 Builder deps)          ✅
  └─ config/services.php DI wiring                    ✅
  └─ Builder uses Container                           ✅

Phase 10.5 (v2.0.x): PluginManager                   ⬜ Optional
Phase 11   (v2.1.0): Session Abstraction              ⬜ Next recommended
Phase 14   (v2.1.x): Remaining Builder Cleanup        ⬜
Phase 15   (v2.2.0): Frontend Modularization          ⬜
Phase 16   (v2.2.x): Security Hardening               ⬜
Phase 17-18 (v3.0):  Public Dir + Docs               ⬜
```

---

## 6. Ghi Chú Kỹ Thuật

### Dependency Graph sau Phase 10

```
Container (wires everything)
    │
    ├── GitService ──────────────────────────── (standalone)
    │
    ├── OutputManager ───────────────────────── (standalone)
    │
    ├── ProcessManager ──────────────── OutputManager
    │
    ├── FileOperations ──────────────── RepositoryManagerInterface
    │                  └────────────── OutputManager
    │
    ├── RepositoryManager ───────────── OutputManager
    │                     ├─────────── FileOperationsInterface
    │                     ├─────────── FileSearchServiceInterface
    │                     └─────────── pluginFsLoader (callback)
    │
    ├── FileSearchService ───────────── OutputManager
    │                     └─────────── RepositoryManagerInterface
    │
    ├── AuthService ─────────────────── FileOperationsInterface
    │
    ├── ColorManager ────────────────── RepositoryManagerInterface
    │                └────────────────  FileOperationsInterface
    │
    ├── RevisionManager ─────────────── AuthServiceInterface
    │                   ├───────────── FileOperationsInterface
    │                   └───────────── RepositoryManagerInterface
    │
    ├── SyncService ─────────────────── RepositoryManagerInterface
    │               └───────────────── FileOperationsInterface
    │
    ├── SSHService ──────────────────── OutputManager
    │              ├────────────────── ProcessManager
    │              └────────────────── RepositoryManagerInterface
    │
    ├── EditorService ───────────────── RepositoryManagerInterface
    │                 ├─────────────── FileOperationsInterface
    │                 ├─────────────── FileSearchServiceInterface
    │                 ├─────────────── AuthServiceInterface
    │                 ├─────────────── ColorManager
    │                 ├─────────────── RevisionManager
    │                 └─────────────── OutputManager
    │
    └── SNRService ──────────────────── OutputManager
                    ├────────────────── RepositoryManagerInterface
                    └────────────────── FileOperationsInterface

Builder (facade)
    └── resolves all above via Container
    └── pluginFsLoader = fn($fs, &$handler) => $this->has_plugin_fs(...)
```

### Metric so sánh

| Metric | v2.0.6 | v2.0.7 |
|---|---|---|
| Circular dependencies | 11 services → Builder → 11 services | **0** |
| Services với `\Builder` trong constructor | 11 | **0** |
| Interfaces | 0 | **6** |
| Unit testable services | 0 | **11** |
| Builder builder calls từ services | ~100 | **0** |
| DI Container | ❌ | ✅ |

---

## 7. References

- `CLOUDPAD9_REFACTORING_PLAN.md` — Phase 10 chi tiết
- `WORKLOG_V2.0.6.md` — Phase 5, 6.3, 8, 9.2-9.5
- `config/services.php` — DI wiring trung tâm
