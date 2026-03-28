<?php
namespace CloudPad;

/**
 * Builder — Container facade + plugin orchestration + template helpers.
 *
 * R1 (2026-03): Xoá 3 global classes duplicate (plugin_fs, plugin_tab, ProfilingHelper).
 * R5 (2026-03): Moved into CloudPad namespace. PluginManager extracted.
 */

use CloudPad\Auth\AuthService;
use CloudPad\Core\Container;
use CloudPad\Core\Output\OutputManager;
use CloudPad\Core\Process\ProcessManager;
use CloudPad\Core\Request;
use CloudPad\Core\Response;
use CloudPad\Core\Session\NativeSession;
use CloudPad\Editor\EditorService;
use CloudPad\FileSystem\FileOperations;
use CloudPad\Git\GitService;
use CloudPad\I18n\Translator;
use CloudPad\Plugin\PluginManager;
use CloudPad\Repository\RepositoryManager;
use CloudPad\SSH\SSHService;

// ── Builder class ────────────────────────────────────────────────────────────

class Builder
{
    // ── App root directory ───────────────────────────────────────────────────────
    private string $_appDir;

    // ── DI Container ─────────────────────────────────────────────────────────
    private Container $_container;

    // ── Service instances ─────────────────────────────────────────────────────
    private Translator        $_translator;
    private AuthService       $_auth;
    private SSHService        $_sshService;
    private RepositoryManager $_repositoryManager;
    private GitService        $_gitService;
    private FileOperations    $_fileOps;
    private EditorService     $_editorService;
    private OutputManager     $_output;
    private ProcessManager    $_process;
    private PluginManager     $_pluginManager;

    function __construct()
    {
        $appDir = defined('BUILDER_DIR') ? BUILDER_DIR : dirname(__DIR__);
        $this->_appDir = $appDir;

        // Bootstrap via DI Container — all service dependencies go through Container.
        $container    = new Container();
        $wireServices = include($appDir . '/config/services.php');
        $wireServices($container, $appDir);

        // Store container for generic get()
        $this->_container = $container;

        // Resolve services from Container
        $this->_output            = $container->get(OutputManager::class);
        $this->_process           = $container->get(ProcessManager::class);
        $this->_gitService        = $container->get(GitService::class);
        $this->_fileOps           = $container->get(FileOperations::class);
        $this->_repositoryManager = $container->get(RepositoryManager::class);
        $this->_auth              = $container->get(AuthService::class);
        $this->_sshService        = $container->get(SSHService::class);
        $this->_editorService     = $container->get(EditorService::class);
        $this->_pluginManager     = $container->get(PluginManager::class);
        $this->_translator        = $container->get(Translator::class);
    }

    /**
     * Cập nhật userDataDir cho ProcessManager sau khi user đăng nhập.
     * Gọi ngay sau auth() khi session đã available.
     */
    public function initProcessManager(): void
    {
        $this->_process->setUserDataDir($this->getUserDataDir());
    }

    // ── Service access ────────────────────────────────────────────────────────

    /**
     * Generic service getter — dùng cho commands cần service ít phổ biến.
     * Usage: $builder->get(EditorService::class)->saveCurrentFile(...)
     */
    public function get(string $class): object
    {
        return $this->_container->get($class);
    }

    // Typed getters cho 5 services dùng nhiều nhất trong templates và commands:
    public function getAuth(): AuthService                 { return $this->_auth; }
    public function getRepoManager(): RepositoryManager    { return $this->_repositoryManager; }
    public function getFileOps(): FileOperations           { return $this->_fileOps; }
    public function getOutput(): OutputManager             { return $this->_output; }
    public function getEditorService(): EditorService      { return $this->_editorService; }
    public function getPluginManager(): PluginManager      { return $this->_pluginManager; }

    function json_response($arr) {
        Response::json((array)$arr);
    }

    function error($message) {
        Response::fail((string)$message);
    }

    // Phase 14: isMobile() → Request::isMobile()
    function isMobile() { return Request::isMobile(); }

    function __destruct() {
        $this->serializeUserSessionData();
    }

    function serializeUserSessionData() { $this->_auth->serializeUserSessionData(); }

    function reloadUserSessionData() { $this->_auth->reloadUserSessionData(); }

    function isUserLoggedIn() { return $this->_auth->isUserLoggedIn(); }

    function getUserSessionId() { return $this->_auth->getUserSessionId(); }

    function getUserDataDir() { return $this->_auth->getUserDataDir(); }

    function auth() { $this->_auth->auth(); }

    function ensure_auth($authed) { $this->_auth->ensureAuth($authed); }

    function get_lang() { return $this->_translator->getLang(); }

    function load_language_file() { $this->_translator->loadLanguageFile(); }

    function get_user_language() { return $this->_translator->getUserLanguage(); }

    function get_browser_language() { return $this->_translator->getBrowserLanguage(); }

    // Phase 14: get_public_user_info() → AuthService::getPublicUserInfo()
    function get_public_user_info() { return $this->_auth->getPublicUserInfo(); }

    // ── Phase 9.3 wrapper: exec → ProcessManager ─────────────────────────
    function exec($cmd, $cwd = null, $return_output = false, &$output = '')
    {
        return $this->_process->exec($cmd, $cwd, (bool)$return_output, $output);
    }

    // ── Retained wrappers (high-frequency, called by 4+ files) ──────────────

    // OutputManager — used throughout lifecycle and service classes
    function flush_line($s, $flush_js_message = false, $js_message = null, $modal = true) { $this->_output->flushLine($s, (bool)$flush_js_message, $js_message, (bool)$modal); }
    function verbose($content)  { $this->_output->verbose((string)$content); }

    // FileOperations — called by many command files
    function try_exec($command, &$error = null) { return $this->_fileOps->tryExec($command, $error); }
    function file_get_contents($file, $repository = '') { return $this->_fileOps->fileGetContents($file, $repository); }

    // RepositoryManager — called by 4–15 command files
    function getRepositorySettings(string $repository) { return $this->_repositoryManager->getRepositorySettings($repository); }
    function getAbsoluteFilePath($filename, $repository) { return $this->_repositoryManager->getAbsoluteFilePath($filename, $repository); }
    function getRepositories(): array { return $this->_repositoryManager->getRepositories(); }

    // GitService — called by 7 git command files (13 total call-sites)
    function execGitCommand(string $repoDir, string $subCmd, string &$output = ''): bool { return $this->_gitService->execGitCommand($repoDir, $subCmd, $output); }
    function get_git_info(string $filepath): array { return $this->_gitService->getGitInfo($filepath); }

    // AuthService — auth dir helpers used in FS plugins and git commands
    function getUserRepositoryDir() { return $this->_auth->getUserRepositoryDir(); }

    // SSHService — terminal operations
    function ssh_ensure_safe_command($command) { $this->_sshService->ensureSafeCommand($command); }
    function ssh_exec($exec_builtin = true, $exec_custom = true) { $this->_sshService->sshExec($exec_builtin, $exec_custom); }
    function private_ssh_exec($commands) { $this->_sshService->privateSshExec($commands); }
    function ssh_getAugmentedOutput($output, $command) { return $this->_sshService->getAugmentedOutput($output, $command); }
    function ssh_exec_2($commands, $verbose = true) { return $this->_sshService->sshExec2($commands, $verbose); }

    // ProcessManager — lifecycle
    function save_pid(int $pid): void    { $this->_process->savePid($pid); }
    function is_stop_pending(): bool     { return $this->_process->isStopPending(); }

    // ── Plugin Filesystem ────────────────────────────────────────────────────

    /**
     * Load filesystem plugin handler theo type (local, git, sftp, svn).
     * Gọi bởi: RepositoryManager::getRepositoryHandler().
     *
     * @param string  $fs       FS type identifier (local|git|sftp|svn)
     * @param object  &$handler Populated with plugin_fs_* instance on success
     * @return bool
     */


    // ── Plugin orchestration — delegates to PluginManager ────────────────────

    /** @deprecated Prefer $builder->getPluginManager()->loadFsPlugin() */
    function has_plugin_fs($fs, &$handler): bool
    {
        return $this->_pluginManager->loadFsPlugin($fs, $handler);
    }

    /** @deprecated Prefer $builder->getPluginManager()->loadTabPlugin() */
    function has_plugin_tab($tab, &$handler): bool
    {
        return $this->_pluginManager->loadTabPlugin($tab, $handler);
    }

    /** Called by tpl/index.tpl */
    function getUserTabs(): array
    {
        return $this->_pluginManager->getUserTabs();
    }

    static function getAvailablePluginsOfCurrentUser(): array
    {
        return PluginManager::getAvailablePluginsOfCurrentUser();
    }

    static function getEnabledPluginsOfCurrentUser(): array
    {
        return PluginManager::getEnabledPluginsOfCurrentUser();
    }

    static function getCurrentUserPermission(): array
    {
        return PluginManager::getCurrentUserPermission();
    }

    /** Called by tab templates (editor/index.tpl, snr/index.tpl) */
    static function hasPermission($key): bool
    {
        return PluginManager::hasPermission($key);
    }

    function getCurrentUser()     { return $this->_auth->getCurrentUser(); }
    function getCurrentUsername() { return $this->_auth->getCurrentUsername(); }

    /** @deprecated SSHService uses PluginManager::getFrequentUsedShellCommands() directly */
    function getFrequentUsedShellCommands(): array
    {
        return $this->_pluginManager->getFrequentUsedShellCommands();
    }

    /**
     * Trả danh sách files đang mở (formatted cho frontend).
     * Gọi bởi: tabs/editor/index.tpl.
     */
    function getEditorOpenFiles(bool $tempOnly = false): array
    {
        return $this->_editorService->getEditorOpenFiles($tempOnly);
    }

}
