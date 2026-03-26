<?php
/**
 * services.php — DI Container bindings cho CloudPad.
 *
 * Phase 10: Wire service dependencies không còn qua Builder.
 * Phase 11: Wire session stores vào services.
 */

use CloudPad\Auth\AuthService;
use CloudPad\Core\Container;
use CloudPad\FileSystem\FileOperations;
use CloudPad\Core\Output\OutputManager;
use CloudPad\Core\Process\ProcessManager;
use CloudPad\Core\Session\AuthSessionStore;
use CloudPad\Core\Session\EditorSessionStore;
use CloudPad\Core\Session\I18nSessionStore;
use CloudPad\Core\Session\NativeSession;
use CloudPad\Core\Session\SNRSessionStore;
use CloudPad\Core\Session\SSHSessionStore;
use CloudPad\Editor\ColorManager;
use CloudPad\Editor\EditorService;
use CloudPad\Editor\RevisionManager;
use CloudPad\Editor\SyncService;
use CloudPad\Git\GitService;
use CloudPad\I18n\Translator;
use CloudPad\Repository\RepositoryManager;
use CloudPad\Search\FileSearchService;
use CloudPad\Search\SearchAndReplace\SNRService;
use CloudPad\SSH\SSHService;

return function (Container $c, string $appDir, callable $pluginFsLoader): void {

    // ── Session (singleton native session) ────────────────────────────────────
    $c->instance(NativeSession::class, NativeSession::getInstance());

    $c->singleton(AuthSessionStore::class,   fn($c) => new AuthSessionStore($c->get(NativeSession::class)));
    $c->singleton(EditorSessionStore::class, fn($c) => new EditorSessionStore($c->get(NativeSession::class)));
    $c->singleton(SSHSessionStore::class,    fn($c) => new SSHSessionStore($c->get(NativeSession::class)));
    $c->singleton(SNRSessionStore::class,    fn($c) => new SNRSessionStore($c->get(NativeSession::class)));
    $c->singleton(I18nSessionStore::class,   fn($c) => new I18nSessionStore($c->get(NativeSession::class)));

    // ── Leaf services (no CloudPad deps) ─────────────────────────────────────
    $c->singleton(GitService::class, fn() => new GitService());

    $c->singleton(OutputManager::class, fn() => new OutputManager());

    $c->singleton(ProcessManager::class, fn($c) =>
        new ProcessManager($c->get(OutputManager::class), '')
    );

    // ── Translator ────────────────────────────────────────────────────────────
    $c->singleton(Translator::class, fn($c) =>
        new Translator($appDir, $c->get(I18nSessionStore::class))
    );

    // ── Core services (lazy circular bootstrap handled by Container) ──────────
    $c->singleton(FileOperations::class, fn($c) =>
        new FileOperations(
            $c->get(RepositoryManager::class),
            $c->get(OutputManager::class)
        )
    );

    // ── FileSearchService + RepositoryManager: setter injection để phá circular dep ──
    // Thứ tự: FileSearchService (không có RepoManager) → RepositoryManager (có FileSearch)
    // → set RepoManager vào FileSearch sau.
    $c->singleton(FileSearchService::class, fn($c) =>
        new FileSearchService($c->get(OutputManager::class))
        // RepoManager sẽ được set sau khi RepoManager được tạo (xem bootstrapCircular bên dưới)
    );

    $c->singleton(RepositoryManager::class, function($c) use ($appDir, $pluginFsLoader) {
        $repoManager = new RepositoryManager(
            $c->get(OutputManager::class),
            $c->get(FileSearchService::class),
            $appDir,
            $pluginFsLoader,
            $c->get(AuthSessionStore::class),
            $c->get(EditorSessionStore::class)
        );
        // Phá circular: set RepositoryManager vào FileSearchService sau khi tạo
        $c->get(FileSearchService::class)->setRepositoryManager($repoManager);
        return $repoManager;
    });

    // ── Auth ──────────────────────────────────────────────────────────────────
    $c->singleton(AuthService::class, fn($c) =>
        new AuthService(
            $c->get(FileOperations::class),
            $c->get(AuthSessionStore::class),
            $appDir
        )
    );

    // ── Editor sub-services ───────────────────────────────────────────────────
    $c->singleton(ColorManager::class, fn($c) =>
        new ColorManager(
            $c->get(RepositoryManager::class),
            $c->get(FileOperations::class),
            $appDir,
            $c->get(AuthSessionStore::class)
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
            $c->get(RepositoryManager::class),
            $c->get(SSHSessionStore::class)
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
            $c->get(OutputManager::class),
            $c->get(EditorSessionStore::class)
        )
    );

    $c->singleton(SNRService::class, fn($c) =>
        new SNRService(
            $c->get(OutputManager::class),
            $c->get(RepositoryManager::class),
            $c->get(FileOperations::class),
            $c->get(SNRSessionStore::class)
        )
    );
};
