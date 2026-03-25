<?php
require_once __DIR__ . '/vendor/autoload.php';

use \phpseclib\Net\SSH2;
use \phpseclib\Crypt\RSA;
use Dotenv\Dotenv;

// Tải các biến môi trường từ file .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Định nghĩa các hằng số sử dụng biến môi trường
define('SSH_PORT', $_ENV['SSH_PORT']);
define('SSH_RSA_PRIVATE_FILE', $_ENV['SSH_RSA_PRIVATE_FILE']);
define('SSH_RSA_USERNAME', $_ENV['SSH_RSA_USERNAME']);
define('SSH_RSA_PASSPHRASE', $_ENV['SSH_RSA_PASSPHRASE']);

$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'? 'https:' : 'http:';
$server = $_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443? ':'.$_SERVER['SERVER_PORT'] : '');

define('BUILDER_ABSOLUTE_URL' , $scheme.'//'.$server);
define('BUILDER_DIR' , __DIR__);

date_default_timezone_set('Asia/Ho_Chi_Minh');

error_reporting(E_ALL);
header("X-XSS-Protection: 0");

define('PHP_PATH', $_ENV['PHP_PATH'] ?? '/usr/bin/php');

function _t($key, $escape = false) {
    global $_L;
    global $builder;

    if (empty($key)) {
        return;
    }

    if (!isset($_L[$key])) {
        $lang = $builder->get_lang();
        $langfile = __DIR__."/locales/{$lang}.php";

        if (file_exists($langfile)) {
            $key = addslashes($key);

            $content = file_get_contents($langfile);

            $content .= "\$_L['$key'] = '$key';\n";

            file_put_contents($langfile, $content);

            $_L[$key] = $key;
        }

        $text = $key;
    } else {
        $text = $_L[$key];
    }

    if ($escape) {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8', true);
    }

    return $text;
}

// ── Global response bridge functions ────────────────────────────────────────
// Backward-compat wrappers — plugin commands cũ vẫn dùng được.
// Code mới nên gọi trực tiếp \CloudPad\Core\Response::ok() / ::fail().

function json_ok($payload = null, ?string $message = null): void {
    \CloudPad\Core\Response::ok($payload, $message);
}

function json_fail(string $message, array $extra = []): void {
    \CloudPad\Core\Response::fail($message, $extra);
}

// json_response() global — một số plugin command cũ gọi trực tiếp
function json_response(array $arr): void {
    \CloudPad\Core\Response::json($arr);
}

// json_success() — alias của json_ok(), dùng trong master branch
function json_success($payload = null, ?string $message = null): void {
    \CloudPad\Core\Response::ok($payload, $message);
}

// ────────────────────────────────────────────────────────────────────────────

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

class Builder
{
    // ── Service instances ─────────────────────────────────────────────────────
    private \CloudPad\I18n\Translator              $_translator;
    private \CloudPad\Auth\AuthService             $_auth;
    private \CloudPad\Editor\ColorManager          $_colorManager;
    private \CloudPad\Editor\RevisionManager       $_revisionManager;
    private \CloudPad\Editor\SyncService           $_syncService;
    private \CloudPad\SSH\SSHService               $_sshService;
    private \CloudPad\Repository\RepositoryManager $_repositoryManager;
    private \CloudPad\Search\FileSearchService     $_fileSearch;
    // Phase 3 gap-fill services
    private \CloudPad\Git\GitService               $_gitService;
    private \CloudPad\FileSystem\FileOperations    $_fileOps;
    private \CloudPad\Editor\EditorService         $_editorService;

    function __construct()
    {
        $appDir = __DIR__;
        $this->_translator        = new \CloudPad\I18n\Translator($appDir);
        $this->_auth              = new \CloudPad\Auth\AuthService($this, $appDir);
        $this->_colorManager      = new \CloudPad\Editor\ColorManager($this, $appDir);
        $this->_revisionManager   = new \CloudPad\Editor\RevisionManager($this);
        $this->_syncService       = new \CloudPad\Editor\SyncService($this);
        $this->_sshService        = new \CloudPad\SSH\SSHService($this);
        $this->_repositoryManager = new \CloudPad\Repository\RepositoryManager($this, $appDir);
        $this->_fileSearch        = new \CloudPad\Search\FileSearchService($this);
        // Phase 3 gap-fill
        $this->_gitService        = new \CloudPad\Git\GitService($this);
        $this->_fileOps           = new \CloudPad\FileSystem\FileOperations($this);
        $this->_editorService     = new \CloudPad\Editor\EditorService($this);
    }

    function json_response($arr) {
        \CloudPad\Core\Response::json((array)$arr);
    }

    function error($message) {
        \CloudPad\Core\Response::fail((string)$message);
    }

    function isMobile() {
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $host = $_SERVER['HTTP_HOST'];

        $is_mobile = preg_match('/(iphone|ipad|android)/i', $ua)
            || preg_match('/^m\./i', $host);

        return $is_mobile;
    }

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

    function get_public_user_info() {
        $info = array(
            'acl' => ['notepad'],
            'repositories' => []
        );

        return $info;
    }

    function execute_linux($cmd, $check_cmd = true) { return $this->_sshService->executeLinux($cmd, $check_cmd); }

    function exec($cmd, $cwd = null, $return_output = false, &$output = '')
    {
        $descriptorspec = array(
            0 => array(
                "pipe",
                "r"
            ), // stdin is a pipe that the child will read from
            1 => array(
                "pipe",
                "w"
            ), // stdout is a pipe that the child will write to
            2 => array(
                "pipe",
                "w"
            ) // stderr is a pipe that the child will write to
        );

        flush();
        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, null);

        if (is_resource($process)) {
            $status = proc_get_status($process);
            $this->save_pid($status['pid']);

            stream_set_blocking($pipes[1], 0);
            stream_set_blocking($pipes[2], 0);

            $output_readable = true;
            $error_readable  = true;

            $time = time();

            while (!feof($pipes[1]) || !feof($pipes[2])) {
                if (!feof($pipes[1])) {
                    if (($s = fgets($pipes[1], 4096)) !== false) {
                        if ($return_output) {
                            $output .= $s;
                        } else {
                            $this->flush_line($s);
                        }
                    }
                }

                if (!feof($pipes[2])) {
                    if (($s = fgets($pipes[2], 4096)) !== false) {
                        if ($return_output) {
                            $output .= $s;
                        } else {
                            $this->flush('<span class="error">' . $s . '</span>');
                        }
                    }
                }

                if (time() - $time > 1) {
                    if ($this->is_stop_pending()) {
                        $this->flush('<span class="error">[NOTICE] Stop as requested</span>');

                        break;
                    }

                    $time = time();
                }
            }
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    function flush($s)
    {
        print $s;
        flush();
        ob_flush();
    }

    function flush_line($s, $flush_js_message = false, $js_message = null, $modal = true)
    {
        $verbose = \CloudPad\Core\Request::getBool('verbose', true);
        $quiet   = \CloudPad\Core\Request::getBool('quiet', false);

        if (!$verbose) {
            return;
        }

        if (preg_match('/\[(error|warning|notice)/is', $s) || preg_match('/(unknown|error|warning|notice)/is', $s)) {
            $this->flush('<span class="error">' . $s . '</span>');
        } else {
            $this->flush($s);
        }

        if ($flush_js_message && !$quiet) {
            $type = 'info';

            if (empty($js_message)) {
                if (preg_match('/^\s*\[(.*)\]\s*(.*)/is', $s, $match)) {
                    $type       = $match[1];
                    $js_message = $match[2];
                }
            }

            if ($modal) {
                $this->flush_js_message(trim($js_message), $type);
            } else {
                $this->flush_js_notification(trim($js_message), $type);
            }
        }
    }

    function flush_block($block)
    {
        $lines = explode(PHP_EOL, $block);

        foreach ($lines as $line) {
            $this->flush_line($line . PHP_EOL);
        }
    }

    // $type: info|success|warning|danger
    function flush_js_message($message, $type = 'info')
    {
        $this->flush('<script type="text/javascript">showMessage("' . $message . '");</script>');
    }

    function flush_js_notification($message)
    {
        $this->flush('<script type="text/javascript">showNotification("' . $message . '");</script>');
    }

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

    function snr_get_regex($search, $caseinsensitive) {
        $is_full_regex = preg_match('/^\/.+\/[a-z]*$/is', $search);

        if ($is_full_regex) {
            return $search;
        } else {
            $is_simple_regex = stripos($search, '*') !== false;

            if ($is_simple_regex) {
                $search = str_replace(array('.*', '*'), '.+?', $search);
                $search = '/' . $search . '/';

                if ($caseinsensitive) {
                    $search .= 'is';
                } else {
                    $search .= 's';
                }

                return $search;
            }
        }

        return null;
    }

    function snr_get_pos($string, $search, $caseinsensitive, &$full_matched_segment = '') {
        $pos = false;

        $regex = $this->snr_get_regex($search, $caseinsensitive);

        if (!empty($regex)) {
            $pos = $this->snr_get_pos_with_regex($string, $regex, $full_matched_segment);
        } else {
            if ($caseinsensitive) {
                $pos = stripos($string, $search);
            } else {
                $pos = strpos($string, $search);
            }

            if ($pos !== false) {
                $full_matched_segment = $search;
            }
        }

        return $pos;
    }

    function snr_get_pos_with_regex($string, $regex, &$full_matched_segment = '') {
        // TODO: hình như hàm này không đúng, phải trả về pos mới đúng
        if (preg_match($regex, $string, $match, PREG_OFFSET_CAPTURE)) {
            $full_matched_segment = $match[0][0];

            return $match[0][1];
        }

        return false;
    }

    function snr_replace($filepath, $search, $caseinsensitive, $replace, $repository = '') {
        $content = $this->file_get_contents($filepath, $repository);

        $regex = $this->snr_get_regex($search, $caseinsensitive);

        if (!empty($regex)) {
            $pattern = '/<\{foreach\s+from=(.+)\s+key=(.+)\s+item=(.+)\s*}>/';
            $replacement = '<{foreach $1 as \$$2 => \$$3}>';
            $content2 = preg_replace($pattern, $replacement, $content);

            $pattern = '/<\{foreach\s+from=(.+)\s+item=(.+)\s*}>/';
            $replacement = '<{foreach $1 as \$$2}>';
            $content2 = preg_replace($pattern, $replacement, $content2);

            // die('xxx'.$content2);
            // $content2 = preg_replace($regex, $replace, $content);
        } else {
            $content2 = str_replace($search, $replace, $content);
        }

        if ($content2 != $content) {
            $_SESSION['snr-backup'][$filepath] = $content;

            $this->file_put_contents($filepath, $content2, $repository, $ignored, false);
        }
    }

    function snr_revert() {
        foreach ($_SESSION['snr-backup'] as $filepath => $content) {
            $this->file_put_contents($filepath, $content);

            $this->flush_line("Restore $filepath\n");
        }
    }

    function diff() {
        $from_text = \CloudPad\Core\Request::getString('DIFF_FROM');
        $to_text   = \CloudPad\Core\Request::getString('DIFF_TO');

        if (empty($from_text) || empty($to_text)) {
            $this->flush_line("[ERROR] Please specify both texts to compare\n", true);

            return;
        }

        // Limit input
        // $from_text = substr($from_text, 0, 1024*100);
        // $to_text = substr($to_text, 0, 1024*100);

        $_SESSION['DIFF_FROM'] = $from_text;
        $_SESSION['DIFF_TO'] = $to_text;

        // Ensure input is suitable for diff
        $from_text = mb_convert_encoding($from_text, 'HTML-ENTITIES', 'UTF-8');
        $to_text = mb_convert_encoding($to_text, 'HTML-ENTITIES', 'UTF-8');

        // Diff
        include 'finediff.php';

        $opcodes = FineDiff::getDiffOpcodes($from_text, $to_text);
        $rendered_diff = FineDiff::renderDiffToHTMLFromOpcodes($from_text, $opcodes);

        echo '<div class="diff-response">'.$rendered_diff.'</div>';
    }

    function ssh_ensure_safe_command($command) { $this->_sshService->ensureSafeCommand($command); }

    function ssh_exec($exec_builtin = true, $exec_custom = true) { $this->_sshService->sshExec($exec_builtin, $exec_custom); }

    function private_ssh_exec($commands) { $this->_sshService->privateSshExec($commands); }

    function ssh_getAugmentedOutput($output, $command) { return $this->_sshService->getAugmentedOutput($output, $command); }

    function ssh_exec_2($commands, $verbose = true) { return $this->_sshService->sshExec2($commands, $verbose); }

    function standalone_editor() {
        $filename   = \CloudPad\Core\Request::getString('filename');
        $repository = \CloudPad\Core\Request::getString('repository');

        $builder = $this;
        include __DIR__ . '/tpl/standalone_editor.tpl';
    }

    function is_plugin_command($command_path, &$handler, &$methodname) {
        $handler = '';

        if (!preg_match('/^[a-z0-9_\-\.\/]+$/is', $command_path)) {
            return false;
        }

        $parts = explode('/', str_replace('-', '_', $command_path));

        $command = array_pop($parts);
        $command_dir = implode('/', $parts);

        $dir = __DIR__.'/plugins/commands';

        if (!empty($command_dir)) {
            $filepath = $dir."/$command_dir/$command.php";
        } else {
            $filepath = $dir."/$command.php";
        }

        $use_index_file = false;

        if (!file_exists($filepath)) {
            if (!empty($command_dir)) {
                $filepath = $dir."/$command_dir/index.php";
            } else {
                $command_dir = $command;
                $command = 'index';

                $filepath = $dir."/$command_dir/index.php";
            }

            if (!file_exists($filepath)) {
                return false;
            }

            $use_index_file = true;
        }

        require_once($filepath);

        $filename = basename($filepath);
        $funcname = str_replace(array('-', '.', '/'), '_', $command_path);

        if ($use_index_file) {
            $classname = 'plugin_command_'.str_replace(array('-', '.', '/'), '_', $command_dir);
            $methodname = $command;
        } else {
            $classname = 'plugin_command_'.str_replace(array('-', '.', '/'), '_', $command_path);
            $methodname = 'execute';
        }

        if (class_exists($classname)) {
            $handler = new $classname();

            if (!method_exists($handler, $methodname)) {
                $this->verbose("[ERROR] Class '$classname' should declare method `$methodname()`");

                $handler = null;
                $methodname = null;

                return false;
            }

            return true;
        } else if (function_exists($funcname)) {
            $handler = $funcname;

            return true;
        } else {
            $this->verbose("[ERROR] $filename should declare a class '$classname' or a function '$funcname'");
        }

        return false;
    }

    function execute_plugin_command($command_path) {
        if ($this->is_plugin_command($command_path, $handler, $methodname)) {
            if (is_object($handler)) {
                return $handler->$methodname($this);
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

}

global $ajax, $verbose;

$standalone  = \CloudPad\Core\Request::getString('standalone');
$action      = \CloudPad\Core\Request::getAction();
$ajax        = \CloudPad\Core\Request::isAjax();
$verbose     = \CloudPad\Core\Request::getBool('verbose');
$skip_timing = \CloudPad\Core\Request::getBool('skip-timing');
$xxx_actions = array(
    'about',
    'user/register',
    'user/login',
    'user/logout',
    'user/forgot',
    'user/reset_password',
    'user/activate_account',
    'user/googleLogin',
    'user/facebookLogin'
);

$renderable = !$ajax && !in_array($action, array(
    'download-user-file'
)) && !in_array($action, $xxx_actions);

set_time_limit(0);
ob_implicit_flush(true);

session_start();

global $builder;

$builder = new Builder();

$builder->load_language_file();

if (!in_array($action, $xxx_actions)) {
    $builder->auth();
}

// ── Dispatch ────────────────────────────────────────────────────────────────
$router = new \CloudPad\Core\Router($builder);
$router->dispatch($action, $standalone);
// ────────────────────────────────────────────────────────────────────────────

if ($renderable) {
    include __DIR__ . '/tpl/index.tpl';
}
