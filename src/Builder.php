<?php
/**
 * Builder — Central facade class (legacy God Object, đang được refactor dần).
 *
 * Phase 8: Tách khỏi index.php vào file riêng.
 * Phase 9: Tách OutputManager, ProcessManager, SNRService ra khỏi class này.
 * Phase 10: Loại bỏ circular dependencies → inject trực tiếp qua DI Container.
 *
 * Các class toàn cục bên dưới (plugin_fs, plugin_tab, ProfilingHelper) cũng được
 * giữ tại đây để backward-compat với plugin files cũ, cho đến khi chúng được
 * cập nhật để extend CloudPad\Plugin\BaseFilesystemPlugin / BaseTabPlugin.
 */

// ── Backward-compat global aliases ────────────────────────────────────────
// plugin_fs và plugin_tab: extend từ src/Plugin/ để tái dùng logic
// nhưng vẫn giữ tên class toàn cục cho các plugin file cũ.


class plugin_fs {
    protected $builder;

    function __construct($builder) {
        $this->builder = $builder;
    }

    function isAccessible($settings) {
        return true;
    }

    function init(&$settings) {
        return;
    }

    function getRepositoryOperations($settings) {
        $type = isset($settings['type'])? $settings['type'] : '';
        $dirs = isset($settings['dirs'])? $settings['dirs'] : '';
        $repository = isset($settings['code'])? $settings['code'] : '';

        if ($type == 'git') {
            $repodir = isset($settings['git']['dir'])? $settings['git']['dir'] : '';
        } else if ($type == 'svn') {
            $repodir = isset($settings['svn']['dir'])? $settings['svn']['dir'] : '';
        } else if ($type == 'sftp') {
            $repodir = !empty($dirs)? $dirs[0] : '';
        } else {
            $repodir = !empty($dirs)? $dirs[0] : '';
        }

        $operations = array();

        if (!empty($repodir) && !is_object($repodir) && is_dir($repodir)) {
            if (file_exists($repodir.'/composer.json')) {
                $operations['composer install'] = "cd $repodir; composer install";
                $operations['composer update'] = "cd $repodir; composer update";
            }

            if ($type != 'git' && is_dir($repodir.'/.git')) {
                $operations['git status'] = "cd $repodir; echo \# repository $repository $repodir; git status";
                $operations['git pull'] = "cd $repodir; git pull origin master";
                $operations['git commit'] = "cd $repodir; git commit -a -m 'Commit message'";
                $operations['git push'] = "cd $repodir; git push origin master";
            }

            if ($type != 'svn' && is_dir($repodir.'/.svn')) {
                $operations['svn info'] = "svn info $repodir";
                $operations['svn status'] = "svn status $repodir";
                $operations['svn update'] = "svn up $repodir";
                $operations['svn commit'] = "svn commit -m 'X' $repodir";
            }
        }

        return $operations;
    }

    function getLocalizedPath($settings, $path) {
        return $path;
    }
}

class plugin_tab {
    // To be overrided in sub-classes to return ['title' => '', 'description' => '']
    function getPluginInfo() {
        return null;
    }
}

class ProfilingHelper {
    static function track($file, $line, $desc = '') {
        static $filenames = array();

        if (!isset($filenames[$file])) {
            $filenames[$file] = basename($file);
        }

        $name = $filenames[$file];

        return self::ellapsed_time("{$name}:{$line}".(!empty($desc)? ":{$desc}" : ''));
    }

    static function ellapsed_time($desc = '', $commented = false, $returnbody = false) {
        static $latest = null;

        $enabled = \CloudPad\Core\Request::getBool('PROFILING');

        if (!$enabled) {
            return;
        }

        if ($latest === null) {
            $latest = $_SERVER['REQUEST_TIME_FLOAT'];
        }

        $time = microtime(true);

        $time_from_start = $time - $_SERVER['REQUEST_TIME_FLOAT'];
        $time_from_latest = $time - $latest;

        $latest = $time;

        $msg = "<pre>[$desc] Ellapsed time: ".self::friendly_format($time_from_start, 200).", from previous: ".self::friendly_format($time_from_latest, 5)."</pre>";

        if ($commented) {
            $msg = "<!-- $text -->";
        }

        if ($returnbody) {
            return $msg;
        } else {
            echo $msg;
        }
    }

    static function friendly_format($time, $ms_threshold = 0, $color = 'red') {
        if ($time > 1) {
            $out = $time.'s';
        } else {
            $out = ($time*1000).'ms';
        }

        if ($ms_threshold && $time*1000 > $ms_threshold) {
            $out = '<span style="color:'.$color.'">'.$out.'</span>';
        }

        return $out;
    }
}

// ── Builder class ────────────────────────────────────────────────────────────

class Builder
{
    // ── Service instances ─────────────────────────────────────────────────────
    private \CloudPad\I18n\Translator                          $_translator;
    private \CloudPad\Auth\AuthService                         $_auth;
    private \CloudPad\Editor\ColorManager                      $_colorManager;
    private \CloudPad\Editor\RevisionManager                   $_revisionManager;
    private \CloudPad\Editor\SyncService                       $_syncService;
    private \CloudPad\SSH\SSHService                           $_sshService;
    private \CloudPad\Repository\RepositoryManager             $_repositoryManager;
    private \CloudPad\Search\FileSearchService                 $_fileSearch;
    private \CloudPad\Git\GitService                           $_gitService;
    private \CloudPad\FileSystem\FileOperations                $_fileOps;
    private \CloudPad\Editor\EditorService                     $_editorService;
    // Phase 9.2-9.4: new extracted services
    private \CloudPad\Core\Output\OutputManager                $_output;
    private \CloudPad\Core\Process\ProcessManager              $_process;
    private \CloudPad\Search\SearchAndReplace\SNRService       $_snr;

    function __construct()
    {
        $appDir = defined('BUILDER_DIR') ? BUILDER_DIR : __DIR__;

        // ── Phase 10: Bootstrap via DI Container ─────────────────────────────
        // Tất cả service-to-service dependencies đi qua Container.
        // Builder chỉ còn là thin facade/service-locator.

        $container = new \CloudPad\Core\Container();

        // pluginFsLoader callback — giữ tại Builder vì has_plugin_fs() cần $this
        // để truyền Builder instance vào plugin_fs_* constructors.
        // Sẽ được tách hoàn toàn ở Phase 10.5 (PluginManager).
        $pluginFsLoader = function (string $fs, &$handler) {
            return $this->has_plugin_fs($fs, $handler);
        };

        // Wire tất cả services
        $wireServices = include($appDir . '/config/services.php');
        $wireServices($container, $appDir, $pluginFsLoader);

        // Resolve services từ Container
        $this->_output            = $container->get(\CloudPad\Core\Output\OutputManager::class);
        $this->_process           = $container->get(\CloudPad\Core\Process\ProcessManager::class);
        $this->_gitService        = $container->get(\CloudPad\Git\GitService::class);
        $this->_fileOps           = $container->get(\CloudPad\FileSystem\FileOperations::class);
        $this->_repositoryManager = $container->get(\CloudPad\Repository\RepositoryManager::class);
        $this->_fileSearch        = $container->get(\CloudPad\Search\FileSearchService::class);
        $this->_auth              = $container->get(\CloudPad\Auth\AuthService::class);
        $this->_colorManager      = $container->get(\CloudPad\Editor\ColorManager::class);
        $this->_revisionManager   = $container->get(\CloudPad\Editor\RevisionManager::class);
        $this->_syncService       = $container->get(\CloudPad\Editor\SyncService::class);
        $this->_sshService        = $container->get(\CloudPad\SSH\SSHService::class);
        $this->_editorService     = $container->get(\CloudPad\Editor\EditorService::class);
        $this->_snr               = $container->get(\CloudPad\Search\SearchAndReplace\SNRService::class);

        // Translator — Phase 11: now in Container (has I18nSessionStore)
        $this->_translator = $container->get(\CloudPad\I18n\Translator::class);
    }

    /**
     * Cập nhật userDataDir cho ProcessManager sau khi user đăng nhập.
     * Gọi ngay sau auth() khi session đã available.
     */
    public function initProcessManager(): void
    {
        $this->_process->setUserDataDir($this->getUserDataDir());
    }

    function json_response($arr) {
        \CloudPad\Core\Response::json((array)$arr);
    }

    function error($message) {
        \CloudPad\Core\Response::fail((string)$message);
    }

    // Phase 14: isMobile() → Request::isMobile()
    function isMobile() { return \CloudPad\Core\Request::isMobile(); }

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

    function execute_linux($cmd, $check_cmd = true) { return $this->_sshService->executeLinux($cmd, $check_cmd); }

    // ── Phase 9.3 wrapper: exec → ProcessManager ─────────────────────────
    function exec($cmd, $cwd = null, $return_output = false, &$output = '')
    {
        return $this->_process->exec($cmd, $cwd, (bool)$return_output, $output);
    }

    // ── Phase 9.2 wrappers: flush* → OutputManager ───────────────────────
    function flush($s)                                                          { $this->_output->flush($s); }
    function flush_line($s, $flush_js_message = false, $js_message = null, $modal = true) { $this->_output->flushLine($s, (bool)$flush_js_message, $js_message, (bool)$modal); }
    function flush_block($block)                                                { $this->_output->flushBlock($block); }
    function flush_js_message($message, $type = 'info')                        { $this->_output->flushJsMessage($message, $type); }
    function flush_js_notification($message)                                   { $this->_output->flushJsNotification($message); }

    function is_empty_dir($dir) { return $this->_fileOps->isEmptyDir($dir); }

    function getLocalizedPath($file, $repository) { return $this->_fileOps->getLocalizedPath($file, $repository); }

    function getRelPath($filepath, $repository) { return $this->_fileOps->getRelPath($filepath, $repository); }

    function file_exists($file, $repository = '') { return $this->_fileOps->fileExists($file, $repository); }

    function rename($file, $newfile, $repository = '') { return $this->_fileOps->rename($file, $newfile, $repository); }

    function file_get_contents($file, $repository = '') { return $this->_fileOps->fileGetContents($file, $repository); }

    function file_put_contents($file, $content, $repository = '', &$message = null, $verbose = true) { return $this->_fileOps->filePutContents($file, $content, $repository, $message, $verbose); }

    function try_chmod($mode, $filepath) { return $this->_fileOps->tryChmod((string)$mode, $filepath); }

    function try_exec($command, &$error = null) { return $this->_fileOps->tryExec($command, $error); }

    function open_file_by_name($filename, $fromcache, $repository) { $this->_editorService->openFileByName($filename, (bool)$fromcache, $repository); }

    function get_directory_children($repository, $path) { $this->_editorService->getDirectoryChildren($repository, $path); }

    function get_directory_structure($repository) { $this->_editorService->getDirectoryStructure($repository); }

    function getDirectoryStructure($filepaths) { return $this->_editorService->buildDirectoryStructure($filepaths); }

    function addToDirectoryStructure(&$structure, $parts, $basePath) { $this->_editorService->addToDirectoryStructure($structure, $parts, $basePath); }

    function file_live_search($repository, $filename) { $this->_editorService->fileLiveSearch($repository, $filename); }

    function get_file_content($filename, $repository = '') { $this->_editorService->getFileContent($filename, $repository); }

    function close_file($filename, $repository, $standalone = false) { $this->_editorService->closeFile($filename, $repository, (bool)$standalone); }

    function getUserUploadFiles() { return $this->_editorService->getUserUploadFiles(); }

    function upload_file() { $this->_editorService->uploadFile(); }

    function download_user_file($filename) { $this->_editorService->downloadUserFile($filename); }

    function delete_user_file($filename) { $this->_editorService->deleteUserFile($filename); }

    function getUserUploadDir() { return $this->_auth->getUserUploadDir(); }

    function getUserRepositoryDir() { return $this->_auth->getUserRepositoryDir(); }

    function getUserTempDir() { return $this->_auth->getUserTempDir(); }

    function getUserRevisionDir() { return $this->_auth->getUserRevisionDir(); }

    function getUserTempRevisionDir() { return $this->_auth->getUserTempRevisionDir(); }

    function getUserTempFilePaths() { return $this->_editorService->getUserTempFilePaths(); }

    function getNewFilePath() { return $this->_editorService->getNewFilePath(); }

    function new_temp_file() { $this->_editorService->newTempFile(); }

    function set_color($filename, $repository, $color) { $this->_colorManager->setColor($filename, $repository, $color); }

    function get_color($filepath) { return $this->_colorManager->getColor($filepath); }

    function get_color_file() { return $this->_colorManager->getColorFile(); }

    function clone_file($filename, $repository, $newname) { $this->_editorService->cloneFile($filename, $repository, $newname); }

    function save_file_revision($filepath, $content) { $this->_revisionManager->saveFileRevision($filepath, $content); }

    function create_temp_revision($filepath, $content) { $this->_revisionManager->createTempRevision($filepath, $content); }

    function get_revision_count($revdir, $filename) { return $this->_revisionManager->getRevisionCount($revdir, $filename); }

    function get_revision_prefix($filepath) { return $this->_revisionManager->getRevisionPrefix($filepath); }

    function get_latest_revision_content($filepath) { return $this->_revisionManager->getLatestRevisionContent($filepath); }

    function get_latest_temp_revision_content($filepath) { return $this->_revisionManager->getLatestTempRevisionContent($filepath); }

    function revert_file($filename, $repository) { $this->_revisionManager->revertFile($filename, $repository); }

    function recover_file($filename, $repository) { $this->_revisionManager->recoverFile($filename, $repository); }

    function reload_file($filename, $repository) { $this->_revisionManager->reloadFile($filename, $repository); }

    ////////////////////////////////////////////////////////////////////////////
    // NOTE: $path có dạng `<index>://<relpath>`
    ////////////////////////////////////////////////////////////////////////////
    function getAbsolutePath($path, $repository) { return $this->_repositoryManager->getAbsolutePath($path, $repository); }

    function getAbsoluteFilePath($filename, $repository) { return $this->_repositoryManager->getAbsoluteFilePath($filename, $repository); }

    function sync_file($filename, $repository, $revert = false) { $this->_syncService->syncFile($filename, $repository, $revert); }

    function get_sync_dest($filepath) { return $this->_syncService->getSyncDest($filepath); }

    function rebuild_sub_indexes($filename, $repository, $revert = false) { $this->_editorService->rebuildSubIndexes($filename, $repository); }

    function save_current_file($filename, $repository, $content, $creat_temp_revision_only) { $this->_editorService->saveCurrentFile($filename, $repository, $content, (bool)$creat_temp_revision_only); }

    // ── Phase 9.4 wrappers: snr_* → SNRService ──────────────────────────
    function snr_get_regex($search, $caseinsensitive)                              { return $this->_snr->getRegex($search, (bool)$caseinsensitive); }
    function snr_get_pos($string, $search, $caseinsensitive, &$full_matched_segment = '') { return $this->_snr->getPos($string, $search, (bool)$caseinsensitive, $full_matched_segment); }
    function snr_get_pos_with_regex($string, $regex, &$full_matched_segment = '')  { return $this->_snr->getPosWithRegex($string, $regex, $full_matched_segment); }
    function snr_replace($filepath, $search, $caseinsensitive, $replace, $repository = '') { $this->_snr->replace($filepath, $search, (bool)$caseinsensitive, $replace, $repository); }
    function snr_revert()                                                           { $this->_snr->revert(); }

    function diff()
    {
        $from_text = \CloudPad\Core\Request::getString('DIFF_FROM');
        $to_text   = \CloudPad\Core\Request::getString('DIFF_TO');

        if (empty($from_text) || empty($to_text)) {
            $this->flush_line("[ERROR] Please specify both texts to compare\n", true);
            return;
        }

        \CloudPad\Core\Session\NativeSession::getInstance()->set('DIFF_FROM', $from_text);
        \CloudPad\Core\Session\NativeSession::getInstance()->set('DIFF_TO', $to_text);

        $from_text = mb_convert_encoding($from_text, 'HTML-ENTITIES', 'UTF-8');
        $to_text   = mb_convert_encoding($to_text,   'HTML-ENTITIES', 'UTF-8');

        include 'finediff.php';

        $opcodes       = FineDiff::getDiffOpcodes($from_text, $to_text);
        $rendered_diff = FineDiff::renderDiffToHTMLFromOpcodes($from_text, $opcodes);

        echo '<div class="diff-response">' . $rendered_diff . '</div>';
    }


    function ssh_ensure_safe_command($command) { $this->_sshService->ensureSafeCommand($command); }

    function ssh_exec($exec_builtin = true, $exec_custom = true) { $this->_sshService->sshExec($exec_builtin, $exec_custom); }

    function private_ssh_exec($commands) { $this->_sshService->privateSshExec($commands); }

    function ssh_getAugmentedOutput($output, $command) { return $this->_sshService->getAugmentedOutput($output, $command); }

    function ssh_exec_2($commands, $verbose = true) { return $this->_sshService->sshExec2($commands, $verbose); }

    // Phase 14: standalone_editor → Router::renderStandaloneEditor (called directly by Router now)

    // Phase 14: is_plugin_command/execute_plugin_command → Router::resolvePluginCommand()
    // Kept as backward-compat wrappers; Router now owns this logic.
    function is_plugin_command($command_path, &$handler, &$methodname)
    {
        $router   = new \CloudPad\Core\Router($this);
        $resolved = $router->resolvePluginCommand($command_path);

        if ($resolved === null) {
            $handler    = null;
            $methodname = null;
            return false;
        }

        [$handler, $methodname] = $resolved;
        return true;
    }

    function execute_plugin_command($command_path)
    {
        $router   = new \CloudPad\Core\Router($this);
        $resolved = $router->resolvePluginCommand($command_path);

        if ($resolved === null) {
            $this->error("`$command_path` is not a valid plugin command");
            return;
        }

        [$handler, $methodname] = $resolved;
        if (is_object($handler)) {
            return $handler->$methodname($this);
        }
        return $handler($this);
    } else {
                return $handler($this);
            }
        } else {
            $this->error("`$command_path` is not a valid plugin command");
        }
    }

    /**
     * Run a git subcommand inside a given repository directory.
     *
     * @param  string  $repoDir  Absolute path to the git repo root (toplevel).
     * @param  string  $subCmd   Git subcommand string (caller must escapeshellarg individual args).
     * @param  string  &$output  Combined stdout+stderr output.
     * @return bool              true on exit code 0, false otherwise.
     */
    function execGitCommand(string $repoDir, string $subCmd, string &$output = ''): bool { return $this->_gitService->execGitCommand($repoDir, $subCmd, $output); }

    function get_git_info(string $filepath): array { return $this->_gitService->getGitInfo($filepath); }

    // ══════════════════════════════════════════════════════════════════════════
    // Phase 9.1 — Restored missing methods (wrappers & implementations)
    // Nguyên nhân thiếu: khi refactor v2.0.1→v2.0.4, methods được di chuyển
    // sang service classes nhưng thin wrappers trong Builder không được tạo.
    // ══════════════════════════════════════════════════════════════════════════

    // ── Phase 9.2 wrapper: verbose → OutputManager ───────────────────────
    function verbose($content)  { $this->_output->verbose((string)$content); }

    // ── Phase 9.3 wrappers: pid/stop → ProcessManager ────────────────────
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
    function has_plugin_fs($fs, &$handler)
    {
        $handler = null;

        $dir = __DIR__ . '/plugins/fs';
        $filepath = $dir . "/$fs/$fs.php";

        if (!file_exists($filepath)) {
            $this->verbose("[ERROR] Plugin file '$filepath' is not found");
            return false;
        }

        require_once($filepath);

        $filename = basename($filepath);
        $classname = 'plugin_fs_' . $fs;

        if (!class_exists($classname)) {
            $this->verbose("[ERROR] Class '$classname' is not found in file '$filename'");
            return false;
        }

        $handler = new $classname($this);

        if (!method_exists($handler, 'init') || !method_exists($handler, 'getRepositoryOperations') || !method_exists($handler, 'getLocalizedPath')) {
            $handler = null;
            $this->verbose("[ERROR] Class '$classname' should declare methods `init()`, `getRepositoryOperations()` and `getLocalizedPath()`");
            return false;
        }

        return true;
    }

    // ── Plugin Tabs ──────────────────────────────────────────────────────────

    /**
     * Load tab plugin handler.
     * Gọi bởi: getUserTabs().
     */
    function has_plugin_tab($tab, &$handler)
    {
        $handler = null;

        $dir = __DIR__ . '/plugins/tabs';
        $filepath = $dir . "/$tab/index.php";

        if (!file_exists($filepath)) {
            return false;
        }

        require_once($filepath);

        $filename = basename($filepath);
        $classname = 'plugin_tab_' . str_replace('-', '_', $tab);

        if (!class_exists($classname)) {
            $this->verbose("[ERROR] Class '$classname' is not found in file '$filename'");
            return false;
        }

        $handler = new $classname();

        if (!method_exists($handler, 'getTabTitle') || !method_exists($handler, 'getPluginInfo') || !method_exists($handler, 'render')) {
            $handler = null;
            $this->verbose("[ERROR] Class '$classname' should declare methods `getTabTitle()`, `getPluginInfo()` and `render()`");
            return false;
        }

        return true;
    }

    /**
     * Trả danh sách tab handlers cho user hiện tại.
     * Gọi bởi: tpl/index.tpl.
     */
    function getUserTabs()
    {
        static $handlers = null;

        if ($handlers === null) {
            $tabs = self::getEnabledPluginsOfCurrentUser();
            $handlers = array();

            foreach ($tabs as $tab) {
                if ($this->has_plugin_tab($tab, $handler)) {
                    $handlers[$tab] = $handler;
                }
            }
        }

        return $handlers;
    }

    // ── User / Permission (static) ───────────────────────────────────────────

    /**
     * Danh sách plugins có sẵn cho user hiện tại.
     */
    static function getAvailablePluginsOfCurrentUser()
    {
        $user    = \CloudPad\Core\Session\NativeSession::getInstance()->get('builder.user', []);
        $plugins = (array) ($user['plugins'] ?? []);

        if (!empty($user['repositories'])) {
            $plugins[] = 'editor';
        }

        asort($plugins);

        return $plugins;
    }

    /**
     * Danh sách plugins đã bật cho user hiện tại.
     * Gọi bởi: getUserTabs().
     */
    static function getEnabledPluginsOfCurrentUser()
    {
        $user    = \CloudPad\Core\Session\NativeSession::getInstance()->get('builder.user', []);
        $plugins = (array) ($user['plugins'] ?? []);
        return $plugins;
    }

    /**
     * Permissions của user hiện tại.
     */
    static function getCurrentUserPermission()
    {
        return ['editor', 'snr'];
    }

    /**
     * Kiểm tra user có permission cụ thể.
     * Gọi bởi: tab templates (tabs/editor/index.tpl, tabs/snr/index.tpl).
     */
    static function hasPermission($key)
    {
        $perms = self::getCurrentUserPermission();
        return in_array($key, $perms) || in_array('all', $perms);
    }

    // Phase 14: delegate identity to AuthService
    function getCurrentUser()     { return $this->_auth->getCurrentUser(); }
    function getCurrentUsername() { return $this->_auth->getCurrentUsername(); }

    /**
     * Load danh sách users từ config.
     * Gọi bởi: plugins/commands/user/index.php (login).
     */
    function getUsers()
    {
        return include(__DIR__ . '/users.conf.php');
    }

    // ── Repository Manager wrappers ──────────────────────────────────────────

    /**
     * Trả tất cả repositories đã khởi tạo cho user.
     * Gọi bởi: tabs/editor/index.tpl, tabs/snr/index.tpl, hasRepositoryPermission().
     */
    function getRepositories(): array
    {
        return $this->_repositoryManager->getRepositories();
    }

    /**
     * Trả settings của một repository cụ thể.
     * Gọi bởi: EditorService, FileOperations, SyncService, nhiều plugin commands.
     */
    function getRepositorySettings(string $repository)
    {
        return $this->_repositoryManager->getRepositorySettings($repository);
    }

    /**
     * Kiểm tra user có quyền access repository.
     * Gọi bởi: EditorService, copy_files, move_files plugins.
     */
    function hasRepositoryPermission(string $repository): bool
    {
        return $this->_repositoryManager->hasRepositoryPermission($repository);
    }

    /**
     * Trả danh sách file paths (cached) trong repository.
     * Gọi bởi: FileSearchService, EditorService.
     */
    function getRepositoryFilePaths(string $repository, bool $forceRebuild = false): array
    {
        return $this->_repositoryManager->getRepositoryFilePaths($repository, $forceRebuild);
    }

    /**
     * Trả path dạng branch://relpath cho hiển thị.
     * Gọi bởi: EditorService.
     */
    function getRepositoryWisePath(string $filepath, string $repository, ?string $filename = null): string
    {
        return $this->_repositoryManager->getRepositoryWisePath($filepath, $repository, $filename ?? basename($filepath));
    }

    /**
     * Tìm repository chứa filepath.
     * Gọi bởi: SyncService.
     */
    function getFileRepository(string $filepath): string
    {
        return $this->_repositoryManager->getFileRepository($filepath);
    }

    /**
     * Trả đường dẫn file cache cho repository.
     * Gọi bởi: EditorService.
     */
    function getRepositoryCacheFile(string $repository): string
    {
        return $this->_repositoryManager->getRepositoryCacheFile($repository);
    }

    /**
     * Trả danh sách file paths (rebuild từ disk, không cache).
     * Gọi bởi: plugins/commands/rebuild_filepaths_indexes.php.
     */
    function getProjectFilePaths(string $repository, bool $forceRebuild = false): array
    {
        return $this->_repositoryManager->getProjectFilePaths($repository, $forceRebuild);
    }

    // ── File Search wrappers ─────────────────────────────────────────────────

    /**
     * Tìm chính xác 1 file trong repository.
     * Gọi bởi: EditorService, RepositoryManager.
     */
    function searchForFile(string $filename, string $repository): string
    {
        return $this->_fileSearch->searchForFile($filename, $repository);
    }

    /**
     * Tìm nhiều files matching trong repository.
     * Gọi bởi: EditorService.
     */
    function searchForFiles(string $filename, string $repository, int $limit = 0, bool $exact = false): array
    {
        return $this->_fileSearch->searchForFiles($filename, $repository, $limit, $exact);
    }

    /**
     * Tìm files matching trong array file paths cho trước.
     * Gọi bởi: EditorService.
     */
    function searchForFilesInArray(string $filename, array $filepaths, int $limit = 0, bool $exact = false): array
    {
        return $this->_fileSearch->searchForFilesInArray($filename, $filepaths, $limit, $exact);
    }

    /**
     * Recursive search files trong directory, loại trừ excludes.
     * Gọi bởi: EditorService, RepositoryManager.
     */
    function rsearch(string $dir, array $excludes = [], array $includes = []): array
    {
        return $this->_fileSearch->rsearch($dir, $excludes, $includes);
    }

    /**
     * Liệt kê entries trong directory (non-recursive).
     * Gọi bởi: FileSearchService (internal), RepositoryManager.
     */
    function glob(string $dir): array
    {
        return $this->_fileSearch->glob($dir);
    }

    /**
     * Kiểm tra path có bị excluded không.
     */
    function isExcludedPath(string $file, array $excludes, array $includes): bool
    {
        return $this->_fileSearch->isExcludedPath($file, $excludes, $includes);
    }

    // ── SSH Shell Commands ───────────────────────────────────────────────────

    /**
     * Trả danh sách shell commands thường dùng cho repository hiện tại.
     * Lấy từ repository handler (plugin_fs_local, plugin_fs_git, etc.)
     * Gọi bởi: SSHService::sshExec().
     */
    function getFrequentUsedShellCommands(): array
    {
        $repository = \CloudPad\Core\Request::getString('repository');

        if (empty($repository)) {
            return [];
        }

        $settings = $this->getRepositorySettings($repository);

        if (empty($settings) || empty($settings['handler'])) {
            return [];
        }

        return $settings['handler']->getRepositoryOperations($settings);
    }

    // ── Editor Service wrappers ──────────────────────────────────────────────

    /**
     * Cập nhật session filepath cho file đang mở.
     * Gọi bởi: EditorService (internal callback).
     */
    function setFilePath(string $filename, string $filepath, string $repository): void
    {
        $this->_editorService->setFilePath($filename, $filepath, $repository);
    }

    /**
     * Thêm filepath vào cache index của repository.
     * Gọi bởi: plugins/commands/rename_file.php, rename_directory_of_file.php.
     */
    function addToRepositoryFilePaths(string $filepath, string $repository): void
    {
        $this->_editorService->addToRepositoryFilePaths($filepath, $repository);
    }

    /**
     * Trả danh sách files đang mở trong editor.
     * Gọi bởi: getEditorOpenFiles(), tpl logic.
     */
    function getOpenFiles(bool $tempOnly = false): array
    {
        return $this->_editorService->getOpenFiles($tempOnly);
    }

    /**
     * Trả danh sách files đang mở (formatted cho frontend).
     * Gọi bởi: tabs/editor/index.tpl.
     */
    function getEditorOpenFiles(bool $tempOnly = false): array
    {
        return $this->_editorService->getEditorOpenFiles($tempOnly);
    }

    // ── Phase 9.4 wrappers: file_mask_matched + snr_search → SNRService ──
    function file_mask_matched($pattern, $path)
    {
        return $this->_snr->fileMaskMatched($pattern, $path);
    }

    function snr_search(
        $repository,
        $search,
        $caseinsensitive  = true,
        $file_mask        = '',
        $max_found_files  = 20,
        $search_by_filename = false,
        $force_replace    = false,
        $replace          = '',
        $force_delete     = false
    ) {
        $this->_snr->search(
            $repository,
            $search,
            (bool)$caseinsensitive,
            $file_mask,
            (int)$max_found_files,
            (bool)$search_by_filename,
            (bool)$force_replace,
            $replace,
            (bool)$force_delete
        );
    }

    // ── Misc helpers ─────────────────────────────────────────────────────────

    function get_sub_dirs($dir)
    {
        $paths = glob("$dir/*");
        $sub_dirs = array();

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $sub_dirs[] = basename($path);
            }
        }

        return $sub_dirs;
    }

}
