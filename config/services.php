<?php
/**
 * services.php — DI Container bindings cho CloudPad.
 *
 * Phase 10: Wire tất cả service dependencies không còn qua Builder.
 * Builder nhận Container và dùng nó để resolve services.
 *
 * Thứ tự đăng ký phải bottom-up (leaf services trước):
 *   GitService (0 deps)
 *   OutputManager (0 deps)
 *   ProcessManager (OutputManager)
 *   FileOperations (RepositoryManager, OutputManager)   ← circular bootstrap issue
 *   RepositoryManager (OutputManager, FileOps, FileSearch, pluginFsLoader)
 *   FileSearchService (OutputManager, RepositoryManager)
 *   AuthService (FileOps)
 *   ColorManager (RepositoryManager, FileOps)
 *   RevisionManager (Auth, FileOps, RepositoryManager)
 *   SyncService (RepositoryManager, FileOps)
 *   SSHService (OutputManager, ProcessManager, RepositoryManager)
 *   EditorService (all the above)
 *   SNRService (OutputManager, RepositoryManager, FileOps)
 *
 * NOTE: FileOperations ↔ RepositoryManager có bootstrapping dependency:
 * FileOps cần RepoManager để getLocalizedPath(), nhưng RepoManager cần FileOps
 * để tryExec() khi rebuilding index.
 * Giải pháp: RepositoryManager nhận FileOps qua lazy callback ở Phase 10.5.
 * Hiện tại: giải quyết bằng cách Builder tự new() trực tiếp, Container chỉ
 * dùng cho services không có circular bootstrap issue.
 */

use CloudPad\Auth\AuthService;
use CloudPad\Core\Container;
use CloudPad\Core\Output\OutputManager;
use CloudPad\Core\Process\ProcessManager;
use CloudPad\Editor\ColorManager;
use CloudPad\Editor\EditorService;
use CloudPad\Editor\RevisionManager;
use CloudPad\Editor\SyncService;
use CloudPad\FileSystem\FileOperations;
use CloudPad\Git\GitService;
use CloudPad\Repository\RepositoryManager;
use CloudPad\Search\FileSearchService;
use CloudPad\Search\SearchAndReplace\SNRService;
use CloudPad\SSH\SSHService;

return function (Container $c, string $appDir, callable $pluginFsLoader): void {

    // ── Leaf services (no CloudPad deps) ─────────────────────────────────────
    $c->singleton(GitService::class, fn() => new GitService());

    $c->singleton(OutputManager::class, fn() => new OutputManager());

    // ── Process ───────────────────────────────────────────────────────────────
    // userDataDir будет заполнен позже через setUserDataDir() после auth()
    $c->singleton(ProcessManager::class, fn($c) =>
        new ProcessManager($c->get(OutputManager::class), '')
    );

    // ── FileOperations + RepositoryManager (bootstrapped together) ────────────
    // FileOps нужен RepoManager для getLocalizedPath,
    // RepoManager нужен FileOps для tryExec.
    // Решение: создаём их в правильном порядке через две фазы:
    // 1. FileOps получает временный "stub" RepoManager-proxy
    // 2. После создания RepoManager, внедряем его в FileOps

    // FileSearchService нужен до RepositoryManager (RepositoryManager зависит от него)
    // но FileSearchService нужен RepositoryManager → bootstrap через lazy proxy.
    // Простое решение: все три создаются вместе в Builder::__construct через
    // прямое new(), минуя Container для этой группы.
    // Container используется для остальных services.

    $c->singleton(FileOperations::class, fn($c) =>
        new FileOperations(
            $c->get(RepositoryManager::class),
            $c->get(OutputManager::class)
        )
    );

    $c->singleton(FileSearchService::class, fn($c) =>
        new FileSearchService(
            $c->get(OutputManager::class),
            $c->get(RepositoryManager::class)
        )
    );

    $c->singleton(RepositoryManager::class, fn($c) =>
        new RepositoryManager(
            $c->get(OutputManager::class),
            $c->get(FileOperations::class),
            $c->get(FileSearchService::class),
            $appDir,
            $pluginFsLoader
        )
    );

    // ── Auth ──────────────────────────────────────────────────────────────────
    $c->singleton(AuthService::class, fn($c) =>
        new AuthService($c->get(FileOperations::class), $appDir)
    );

    // ── Editor sub-services ───────────────────────────────────────────────────
    $c->singleton(ColorManager::class, fn($c) =>
        new ColorManager(
            $c->get(RepositoryManager::class),
            $c->get(FileOperations::class),
            $appDir
        )
    );

    $c->singleton(RevisionManager::class, fn($c) =>
        new RevisionManager(
            $c->get(AuthService::class),
            $c->get(FileOperations::class),
            $c->get(RepositoryManager::class)
        )
    );

    $c->singleton(SyncService::class, fn($c) =>
        new SyncService(
            $c->get(RepositoryManager::class),
            $c->get(FileOperations::class)
        )
    );

    $c->singleton(SSHService::class, fn($c) =>
        new SSHService(
            $c->get(OutputManager::class),
            $c->get(ProcessManager::class),
            $c->get(RepositoryManager::class)
        )
    );

    $c->singleton(EditorService::class, fn($c) =>
        new EditorService(
            $c->get(RepositoryManager::class),
            $c->get(FileOperations::class),
            $c->get(FileSearchService::class),
            $c->get(AuthService::class),
            $c->get(ColorManager::class),
            $c->get(RevisionManager::class),
            $c->get(OutputManager::class)
        )
    );

    $c->singleton(SNRService::class, fn($c) =>
        new SNRService(
            $c->get(OutputManager::class),
            $c->get(RepositoryManager::class),
            $c->get(FileOperations::class)
        )
    );
};
