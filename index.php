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

    function serializeUserSessionData() {
        if (!isset($_SESSION['builder.username'])) {
            return;
        }

        $data = [];

        foreach ($_SESSION as $key => $value) {
            // Không lưu SSH_PASSWORD vào disk
            if (!in_array($key, ['SSH_PASSWORD'])) {
                $data[$key] = $_SESSION[$key];
            }
        }

        $filepath = $this->getUserDataDir().'/.session';

        // FIX: Dùng json thay serialize() để tránh Object Injection
        $this->file_put_contents($filepath, json_encode($data, JSON_UNESCAPED_UNICODE), '', $dummy, false);
    }

    function reloadUserSessionData() {
        if (!isset($_SESSION['builder.username'])) {
            return;
        }

        $filepath = $this->getUserDataDir().'/.session';

        if (!file_exists($filepath)) {
            return;
        }

        // FIX: Dùng json_decode thay unserialize() để tránh Object Injection
        $data = json_decode($this->file_get_contents($filepath), true);

        if (!empty($data) && is_array($data)) {
            foreach ($data as $key => $value) {
                $_SESSION[$key] = $value;
            }
        }
    }

    function isUserLoggedIn() {
        return isset($_SESSION['authed']);
    }

    function getUserSessionId() {
        return md5($_SESSION['builder.username']);
    }

    function getUserDataDir() {
        $dir = __DIR__.'/tmp/'.$_SESSION['builder.username'];

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    function auth() {
        if (!isset($_SESSION['authed'])) {
            header('Location: index.php?action=user/login');
        }
    }

    function ensure_auth($authed) {
        if ($authed) {
            if (!isset($_SESSION['authed'])) {
                header('Location: index.php');
            }
        } else {
            if (isset($_SESSION['authed'])) {
                header('Location: index.php');
            }
        }
    }

    function get_lang() {
        $lang = \CloudPad\Core\Request::getString('lang');

        if (!empty($lang)) {
            // sanitize: chỉ cho phép 2-5 ký tự alphanumeric/dash
            $lang = preg_replace('/[^a-zA-Z0-9\-]/', '', $lang);
        } else if (!empty($_SESSION['lang'])) {
            $lang = $_SESSION['lang'];
        } else if (!empty($_COOKIE['lang'])) {
            $lang = $_COOKIE['lang'];
        } else {
            $lang = $this->get_user_language();
        }

        return $lang;
    }

    function load_language_file() {
        $lang = $this->get_lang();

        setcookie('lang', $lang, time() + 86400, '/'); // 10 days for the entire domain
        $_SESSION['lang'] = $lang;

        $langfile = __DIR__."/locales/{$lang}.php";

        if (file_exists($langfile)) {
            require_once($langfile);
        }
    }

    function get_user_language() {
        return $this->get_browser_language();
    }

    function get_browser_language() {
        return isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])? substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2) : 'en';
    }

    function get_public_user_info() {
        $info = array(
            'acl' => ['notepad'],
            'repositories' => []
        );

        return $info;
    }

    function execute_linux($cmd, $check_cmd = true)
    {
        if ($check_cmd && preg_match('/(delete|del|rm)\s/is', $cmd)) {
            $this->flush_line("[ERROR] Command not allowed.\n", true);

            return false;
        }

        $cwd = isset($_SESSION['cwd']) ? $_SESSION['cwd'] : '';

        $res = $this->exec($cmd, $cwd);

        if (preg_match('/^cd (.+)/is', $cmd, $match)) {
            if (!empty($cwd)) {
                if ($match[1][0] != DIRECTORY_SEPARATOR) {
                    $cwd = realpath($cwd . DIRECTORY_SEPARATOR . $match[1]);
                } else {
                    $cwd = $match[1];
                }
            } else {
                $cwd = $match[1];
            }
            $_SESSION['cwd'] = $cwd;
        }

        echo "[$cwd]#<br/>";

        return $res;
    }

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

    function is_empty_dir($dir)
    {
        if (!is_readable($dir))
            return NULL;

        return count(scandir($dir)) == 2;
    }

    function getLocalizedPath($file, $repository) {
        if (!empty($repository)) {
            $settings = $this->getRepositorySettings($repository);

            $handler = isset($settings['handler'])? $settings['handler'] : null;

            if (!empty($handler)) {
                $file = $handler->getLocalizedPath($settings, $file);
            }
        }

        return $file;
    }

    function getRelPath($filepath, $repository) {
        $settings = $this->getRepositorySettings($repository);
        $dirs = $settings['dirs'];

        foreach ($dirs as $dir) {
            if (stripos($filepath, $dir) === 0) {
                return substr($filepath, strlen($dir));
            }
        }

        return $filepath;
    }

    function file_exists($file, $repository = '') {
        $file = $this->getLocalizedPath($file, $repository);

        return file_exists($file);
    }

    function rename($file, $newfile, $repository = '') {
        $file = $this->getLocalizedPath($file, $repository);
        $newfile = $this->getLocalizedPath($newfile, $repository);

        return rename($file, $newfile);
    }

    function file_get_contents($file, $repository = '') {
        if (empty($file)) {
            $this->error('Empty file path');
        }

        $file = $this->getLocalizedPath($file, $repository);

        if (!is_readable($file)) {
            $this->error("File unreadable : $file");
        } else {
            return file_get_contents($file);
        }
    }

    function file_put_contents($file, $content, $repository = '', &$message = null, $verbose = true) {
        $message = null;

        $file = $this->getLocalizedPath($file, $repository);

        if (file_exists($file) && !is_writable($file)) {
        $this->try_chmod(777, $file);
        }

        if (file_exists($file) && !is_writable($file)) {
            $message = "File unwritable : $file<br/>&nbsp;<br/>HINTS:<br/>- chmod 777 $file<br/>- chcon -Rt httpd_sys_rw_content_t $file";

            $this->flush_line("[ERROR] $message", true);

            return false;
        } else {
            // Ensure directory
            $dir = dirname($file);

            if (!empty($dir) && !is_dir($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    $message = "Cannot create directory : $dir";

                    $this->flush_line("[ERROR] $message\n", true);

                    return false;
                }
            }

            if (is_dir($dir) && !is_writable($dir)) {
                $this->try_chmod(777, $dir);
            }

            if (!file_put_contents($file, $content)) {
                if (!file_exists($file) && !is_writable($dir)) {
                    $message = "Cannot write to directory : $dir<br/>&nbsp;<br/>HINTS:<br/>- chmod 777 $dir<br/>- chcon -Rt httpd_sys_rw_content_t $dir";
                } else {
                    $message = "Cannot write to file : $file<br/>&nbsp;<br/>HINTS:<br/>- chmod 777 $file<br/>- chcon -Rt httpd_sys_rw_content_t $file";
                }

                $this->flush_line("[ERROR] $message\n", true);

                return false;
            }

            if ($verbose) {
                $this->flush_line("[NOTICE] File '" . ($file) . "' saved.\n", true, "File $file saved.", false);
            }
        }

        return true;
    }

    function try_chmod($mode, $filepath) {
        // Validate mode — chỉ cho phép octal format
        if (!preg_match('/^[0-7]{3,4}$/', $mode)) {
            return false;
        }
        return $this->try_exec('chmod ' . escapeshellarg($mode) . ' ' . escapeshellarg($filepath));
    }

    function try_exec($command, &$error = null) {
        // Đảm bảo $error được khởi tạo là null mỗi lần hàm được gọi
        $error = null;

        $output = [];
        $return_var = 0;

        $cmd = "/usr/local/bin/execute.sh $command 2>&1";
        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            // Lệnh gặp lỗi, lưu thông báo lỗi vào biến tham chiếu $error
            $error = $cmd."\n".implode("\n", $output);
            return false;
        } else {
            // Lệnh thành công
            return true;
        }
    }

    function open_file_by_name($filename, $fromcache, $repository)
    {
        global $ajax;

        $filepath = $this->searchForFile($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => "File not found : $filename"));

            return;
        }

        $this->setFilePath($filename, $filepath, $repository);

        if ($ajax) {
            $content = $this->file_get_contents($filepath, $repository);
            $color = $this->get_color($filepath);

            $rpath = $this->getRepositoryWisePath($filepath, $repository, $filename);

            \CloudPad\Core\Response::json(array('success' => true, 'content' => $content, 'filename' => $rpath, 'repository' => $repository, 'color' => $color));
        }
    }

    function get_directory_children($repository, $path) {
        $children = array();

        $settings = $this->getRepositorySettings($repository);
        $dirs = $settings['dirs'];

        // Extract branch, path
        $branch = '';

        if (preg_match('/^(.+)\:\/\/(.*)/', trim($path), $match)) {
            $branch = $match[1];
            $path = $match[2];
        }

        // Nếu path branch & path thì coi như là lấy các thư mục gốc trong repository
        if (empty($branch) && empty($path)) {
            foreach ($dirs as $index => $dir) {
                $children[] = array('name' => basename($dir), 'repository' => $repository, 'path' => ($index+1).'://', 'isDir' => is_dir($dir), 'children' => []);
            }
        }

        // Nếu là quick-access
        else if ($branch == 'quick-access'){
            $items = isset($_SESSION['quick-access']) ? $_SESSION['quick-access'] : array();

            foreach ($items as $item) {
                if (isset($item['repository'])) {
                    $children[] = array('name' => $item['name'], 'repository' => $item['repository'], 'path' => $item['path'], 'isDir' => true, 'quickAccess' => true, 'children' => []);
                }
            }
        }

        // Nếu là 1 brand cụ thể nào đó
        else if (is_numeric($branch)) {
            // Lấy branch dir
            $branchDir = isset($dirs[$branch - 1])? $dirs[$branch - 1] : '';

            if (!empty($branchDir) && is_dir($branchDir)) {
                $branchDir = rtrim($branchDir, '/');

                $dir = $branchDir.'/'.$path;

                if (is_dir($dir)) {
                    $iterator = new DirectoryIterator($dir);

                    foreach ($iterator as $entry) {
                        $name = $entry->getFilename();
                        $path = $entry->getPathname();

                        $path = str_replace($branchDir.'/', $branch.'://', $path);

                        if ($entry->isFile()) {
                            $children[] = array('name' => $name, 'repository' => $repository, 'path' => $path, 'isFile' => true, 'children' => []);
                        } else if ($entry->isDir() && !$entry->isDot()) {
                            $children[] = array('name' => $name, 'repository' => $repository, 'path' => $path, 'isDir' => true, 'children' => []);
                        }
                    }
                }
            }
        }

        else {
            $this->json_response(array('success' => false, 'message' => "Unknown path"));
        }

        $this->json_response(array('success' => true, 'children' => $children));
    }

    function get_directory_structure($repository) {
        $settings = $this->getRepositorySettings($repository);
        $dirs = $settings['dirs'];

        $filepaths = $this->getRepositoryFilePaths($repository, false);

        foreach ($dirs as $dir) {
            foreach ($filepaths as &$filepath) {
                if (stripos($filepath, $dir) === 0) {
                    $filepath = str_replace($dir, '', $filepath);
                }
            }
        }

        $directoryStructure = $this->getDirectoryStructure($filepaths);

        \CloudPad\Core\Response::json(array('success' => true, 'directoryStructure' => $directoryStructure));
    }

    function getDirectoryStructure($filepaths) {
        // Khởi tạo một mảng rỗng để lưu kết quả
        $result = [];

        // Duyệt qua mỗi đường dẫn tệp trong mảng đầu vào
        foreach ($filepaths as $filepath) {
            // Tách đường dẫn tệp thành các phần tử bằng dấu gạch chéo
            $parts = explode('/', trim($filepath, '/'));

            // Gọi hàm phụ để thêm các phần tử vào cấu trúc thư mục
            $this->addToDirectoryStructure($result, $parts, '');
        }

        // Trả về kết quả
        return $result;
    }

    // Hàm phụ để thêm các phần tử vào cấu trúc thư mục
    function addToDirectoryStructure(&$structure, $parts, $basePath) {
        // Nếu mảng các phần tử là rỗng, không làm gì cả
        if (empty($parts)) {
            return;
        }

        // Lấy phần tử đầu tiên của mảng
        $part = array_shift($parts);

        // Kiểm tra xem phần tử này đã tồn tại trong cấu trúc thư mục chưa
        $found = false;

        foreach ($structure as &$item) {
            // Nếu có, cập nhật biến cờ và gọi đệ quy hàm phụ với phần còn lại của mảng
            if ($item['name'] == $part) {
                $found = true;
                $this->addToDirectoryStructure($item['children'], $parts, $item['path']);
                break;
            }
        }

        // Nếu không, tạo một mục mới với tên là phần tử hiện tại và mảng con rỗng
        // Sau đó gọi đệ quy hàm phụ với phần còn lại của mảng
        if (!$found) {
            $itemPath = $basePath.'/'.$part;

            $newItem = ['name' => $part, 'children' => [], 'path' => $itemPath];

            // Nếu đó là tệp cuối cùng trong đường dẫn
            if (empty($parts)) {
                $newItem['isFile'] = true;
            } else {
                $this->addToDirectoryStructure($newItem['children'], $parts, $itemPath);
            }

            // Thêm mục mới vào cấu trúc thư mục
            $structure[] = $newItem;
        }
    }

    function file_live_search($repository, $filename)
    {
        $settings = $this->getRepositorySettings($repository);
        $dirs      = $settings['dirs'];

        if (!empty($filename) && $filename[0] == '/' && file_exists($filename)) {
            $filepaths = [$filename];
        } else {
            $recentfilepaths = isset($_SESSION['recentfilepaths'][$repository]) ? $_SESSION['recentfilepaths'][$repository] : array();

            $filepaths_1 = !empty($recentfilepaths) ? $this->searchForFilesInArray($filename, $recentfilepaths, 20) : array();

            if (strlen($filename) < 3 && !empty($filepaths_1)) {
                $filepaths = $filepaths_1;
            } else {
                $filepaths_2 = $this->searchForFiles($filename, $repository, 20);

                $filepaths = array_merge($filepaths_1, $filepaths_2);
            }
        }

        $relpaths = array();

        foreach ($filepaths as $filepath) {
            $relpath = $filepath;

            foreach ($dirs as $dir) {
                if (stripos($filepath, $dir) === 0) {
                    $relpath = str_replace($dir, '', $filepath);
                    break;
                }
            }

            $relpaths[$filepath] = $relpath;
        }

        // Sắp xếp mảng theo độ dài của chuỗi
        uasort($relpaths, function($a, $b) {
            return strlen($a) - strlen($b);
        });

        $html = '<ul class="live-search-results">';

        foreach ($relpaths as $filepath => $relpath) {
            $html .= '<li><span>' . $relpath . '</span></li>';
        }

        $html .= '</ul>';

        echo $html;
    }

    function get_file_content($filename, $repository = '')
    {
        if (!$this->hasRepositoryPermission($repository)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => "File not found : $filename"));
            return;
        }

        $filepath = $this->getAbsoluteFilePath($filename, $repository);

        if (!empty($filepath)) {
            $this->setFilePath($filename, $filepath, $repository);

            $content = $this->file_get_contents($filepath, $repository);
            $color = $this->get_color($filepath);

            $rpath = $this->getRepositoryWisePath($filepath, $repository, $filename);

            // Content may contains non-utf8 characters, so use utf8_encode to enforce utf8 content
            // Otherwise json_encode will return empty
            // \CloudPad\Core\Response::json(array('success' => true, 'content' => utf8_encode($content), 'filename' => $rpath, 'repository' => $repository, 'color' => $color));
            \CloudPad\Core\Response::json(array('success' => true, 'content' => $content, 'filename' => $rpath, 'repository' => $repository, 'color' => $color));
        } else {
            if (!empty($repository)) {
                $this->open_file_by_name($filename, false, $repository);
            }
        }
    }

    function close_file($filename, $repository, $standalone = false) {
        if ($standalone) {
            return;
        }

        if (isset($_SESSION['openfilepaths'][$repository][$filename])) {
            $filepath = $_SESSION['openfilepaths'][$repository][$filename];
            $is_temp_file = ($filename[0] == '*');

            if ($is_temp_file) {
                unlink($filepath);
            }

            unset($_SESSION['openfilepaths'][$repository][$filename]);
        }
    }

    function getUserUploadFiles() {
        $dir = $this->getUserUploadDir();

        $paths = $this->rsearch($dir);

        foreach($paths as $i => $path) {
            $paths[$i] = basename($path);
        }

        return $paths;
    }

    function upload_file() {
        $uploaddir = \CloudPad\Core\Request::getString('directory');

        $is_custom_dir = !empty($uploaddir) && is_dir($uploaddir);

        if (!$is_custom_dir) {
            $uploaddir = $this->getUserUploadDir();
        } else {
            $_SESSION['files.upload.directory'] = $uploaddir;
        }

        if (!is_writable($uploaddir)) {
            $this->error("Upload directory is not writable");
        }

        foreach($_FILES as $file) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

            if (!preg_match('/^(rar|zip|exe|pdf|doc|docx|html|xml|xls|xlsx|csv|svg|png|gif|jpg|json|mp4|webp)$/is', $ext)) {
                $this->error("Uploading `.$ext' files is not allowed");
            }

            if (move_uploaded_file($file['tmp_name'], $uploaddir.'/'.basename($file['name']))) {
                $files[] = $uploaddir.'/'.$file['name'];
            } else {
                \CloudPad\Core\Response::json(array('success' => false, 'message' => "Upload failed"));
                return;
            }
        }

        \CloudPad\Core\Response::json(array('success' => true));
    }

    function download_user_file($filename) {
        // FIX: Luôn resolve trong user upload dir, không cho phép path tuyệt đối
        $filename = basename(ltrim($filename, '/'));

        if (empty($filename)) {
            $this->error('Invalid file name.');
        }

        $dir  = $this->getUserUploadDir();
        $file = $dir . '/' . $filename;

        // FIX: realpath() để chặn path traversal (../../etc/passwd)
        $realFile = realpath($file);
        $realDir  = realpath($dir);

        if ($realFile === false || strncmp($realFile, $realDir . '/', strlen($realDir) + 1) !== 0) {
            $this->error("File unreadable : $filename");
        }

        if (!is_file($realFile)) {
            $this->error("File not found : $filename");
        }

        $ext = pathinfo($realFile, PATHINFO_EXTENSION);

        if (!preg_match('/^(rar|zip|exe|pdf|doc|docx|xml|xls|xlsx|html|csv|svg|png|gif|jpg|gz|sql|mp4|webp)$/is', $ext)) {
            $this->error("Downloading `.$ext' files is not allowed");
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($realFile));

        readfile($realFile);
        exit;
    }

    function delete_user_file($filename) {
        // FIX: Chặn path traversal, chỉ cho phép xoá file trong upload dir
        $filename = basename(ltrim($filename, '/'));

        if (empty($filename)) {
            $this->error('Invalid file name.');
        }

        $dir      = $this->getUserUploadDir();
        $file     = $dir . '/' . $filename;
        $realFile = realpath($file);
        $realDir  = realpath($dir);

        if ($realFile === false || strncmp($realFile, $realDir . '/', strlen($realDir) + 1) !== 0) {
            $this->error("Access denied: $filename");
        }

        if (!is_file($realFile)) {
            $this->error("File not found: $filename");
        }

        unlink($realFile);

        \CloudPad\Core\Response::ok();
    }

    function getUserUploadDir() {
        $dir = __DIR__.'/tmp/'.$_SESSION['builder.username'].'/uploads';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    function getUserRepositoryDir() {
        $dir = __DIR__.'/tmp/'.$_SESSION['builder.username'].'/repo';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    function getUserTempDir() {
        $dir = __DIR__.'/tmp/'.$_SESSION['builder.username'];

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    function getUserRevisionDir() {
        $dir = __DIR__.'/tmp/'.$_SESSION['builder.username'].'/rev';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    function getUserTempRevisionDir() {
        $dir = __DIR__.'/tmp/'.$_SESSION['builder.username'].'/temprev';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    function getUserTempFilePaths() {
        $dir = $this->getUserTempDir();

        $filepaths = glob($dir."/new *");

        $tmp = array();

        foreach ($filepaths as $filepath) {
            if (preg_match('/new ([0-9]+)/', $filepath, $match)) {
                $tmp[$filepath] = $match[1];
            }
        }

        asort($tmp);

        return array_keys($tmp);
    }

    function getNewFilePath() {
        $paths = $this->getUserTempFilePaths();

        $files = array();

        foreach ($paths as $path) {
            $files[] = basename($path);
        }

        $len = count($files);
        $name = '';

        for ($i = 1; $i <= $len*2; $i++) {
            $name = 'new '.$i;

            if (!in_array($name, $files)) {
                break;
            }
        }

        if (empty($name)) {
            $name = 'new '.$i;
        }

        $dir = $this->getUserTempDir();

        return $dir.'/'.$name;
    }

    function new_temp_file() {
        $newfilepath = $this->getNewFilePath();

        $this->file_put_contents($newfilepath, '');

        if (!file_exists($newfilepath)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => "Unable to create $newfilepath."));

            return;
        }

        $filename = basename($newfilepath);

        $rpath = '*'.$filename;
        $_SESSION['filepaths']['*'][$rpath] = $newfilepath;
        $_SESSION['openfilepaths']['*'][$rpath] = $newfilepath;

        \CloudPad\Core\Response::json(array('success' => true, 'content' => '', 'filename' => $rpath, 'repository' => '*'));
    }

    function set_color($filename, $repository, $color) {
        $filepath = $this->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

            return;
        }

        $colorfile = $this->get_color_file();

        if (file_exists($colorfile)) {
            $colors = json_decode($this->file_get_contents($colorfile), true) ?: [];
        } else {
            $colors = [];
        }

        if (!empty($color)) {
            $colors[$filepath] = $color;
        } else {
            if (isset($colors[$filepath])) {
                unset($colors[$filepath]);
            }
        }

        $this->file_put_contents($colorfile, json_encode($colors, JSON_UNESCAPED_UNICODE));

        \CloudPad\Core\Response::json(array('success' => true));
    }

    function get_color($filepath) {
        $colorfile = $this->get_color_file();

        if (file_exists($colorfile)) {
            $colors = json_decode($this->file_get_contents($colorfile), true) ?: [];

            return isset($colors[$filepath]) ? $colors[$filepath] : '';
        }
    }

    function get_color_file() {
        $dir = __DIR__.'/tmp/'.$_SESSION['builder.username'];

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir.'/.color';
    }

    function clone_file($filename, $repository, $newname) {
        $filepath = $this->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

            return;
        }

        $newfilepath = dirname($filepath).'/'.$newname;

        if ($this->file_exists($newfilepath, $repository)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => "Destination file '$newname' already exists."));

            return;
        }

        $content = $this->file_get_contents($filepath, $repository);

        $this->file_put_contents($newfilepath, $content, $repository);

        if (!$this->file_exists($newfilepath, $repository)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => "Unable to create $newfilepath."));

            return;
        }

        $rpath = $this->getRepositoryWisePath($newfilepath, $repository, $newname);
        $this->setFilePath($rpath, $newfilepath, $repository);
        $this->addToRepositoryFilePaths($newfilepath, $repository);

        \CloudPad\Core\Response::json(array('success' => true, 'content' => $content, 'filename' => $rpath, 'repository' => $repository));
    }

    function save_file_revision($filepath, $content) {
        $dir = $this->getUserRevisionDir();

        $md5 = $this->get_revision_prefix($filepath);

        $revcount = $this->get_revision_count($dir, $md5);

        $revfile = $dir.'/'.$md5.'.'.($revcount);
        $newrevfile = $dir.'/'.$md5.'.'.($revcount+1);

        if (!file_exists($revfile) || $content != $this->file_get_contents($revfile)) {
            $this->file_put_contents($newrevfile, $content);
        }
    }

    function create_temp_revision($filepath, $content) {
        $dir = $this->getUserTempRevisionDir();

        $md5 = $this->get_revision_prefix($filepath);

        $revcount = $this->get_revision_count($dir, $md5);

        $revfile = $dir.'/'.$md5.'.'.($revcount);
        $newrevfile = $dir.'/'.$md5.'.'.($revcount+1);

        if (!file_exists($revfile) || $content != $this->file_get_contents($revfile)) {
            $this->file_put_contents($newrevfile, $content);
        }
    }

    function get_revision_count($revdir, $filename) {
        $filepaths = glob("$revdir/$filename.*");

        $maxcount = 10;

        $file2suffix = array();

        foreach ($filepaths as $filepath) {
            if (preg_match('/\.([0-9]+)$/is', $filepath, $match)) {
                $file2suffix[$filepath] = $match[1];
            }
        }

        arsort($file2suffix);

        $count = count($file2suffix);
        $i = 0;
        $maxsuffix = 0;

        foreach ($file2suffix as $filepath => $suffix) {
            $i += 1;

            if ($i == 1) {
                $maxsuffix = $suffix;
            }

            if ($i > $maxcount) {
                unlink($filepath);
            }
        }

        return $maxsuffix;
    }

    function get_revision_prefix($filepath) {
        return basename($filepath).'.'.substr(md5($filepath), 0, 6);
    }

    function get_latest_revision_content($filepath) {
        $dir = $this->getUserRevisionDir();

        $md5 = $this->get_revision_prefix($filepath);

        $revcount = $this->get_revision_count($dir, $md5);

        if (!$revcount) {
            return;
        }

        $revfile = $dir.'/'.$md5.'.'.($revcount);

        if (!file_exists($revfile)) {
            return;
        }

        $content = $this->file_get_contents($revfile);
        unlink ($revfile);

        return $content;
    }

    function get_latest_temp_revision_content($filepath) {
        $dir = $this->getUserTempRevisionDir();

        $md5 = $this->get_revision_prefix($filepath);

        $revcount = $this->get_revision_count($dir, $md5);

        if (!$revcount) {
            return;
        }

        $revfile = $dir.'/'.$md5.'.'.($revcount);

        if (!file_exists($revfile)) {
            return;
        }

        $content = $this->file_get_contents($revfile);

        return $content;
    }

    function revert_file($filename, $repository) {
        $filepath = $this->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

            return;
        }

        $content = $this->get_latest_revision_content($filepath);

        if (empty($content)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'File revisions not found.'));

            return;
        }

        $this->file_put_contents($filepath, $content);

        \CloudPad\Core\Response::json(array('success' => true, 'content' => $content, 'filename' => $filename, 'repository' => $repository));
    }

    function recover_file($filename, $repository) {
        $filepath = $this->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

            return;
        }

        $content = $this->get_latest_temp_revision_content($filepath);

        if (empty($content)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'File revisions not found.'));

            return;
        }

        $this->file_put_contents($filepath, $content);

        \CloudPad\Core\Response::json(array('success' => true, 'content' => $content, 'filename' => $filename, 'repository' => $repository));
    }

    function reload_file($filename, $repository) {
        $filepath = $this->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

            return;
        }

        $content = $this->file_get_contents($filepath, $repository);

        \CloudPad\Core\Response::json(array('success' => true, 'content' => $content, 'filename' => $filename, 'repository' => $repository));
    }

    ////////////////////////////////////////////////////////////////////////////
    // NOTE: $path có dạng `<index>://<relpath>`
    ////////////////////////////////////////////////////////////////////////////
    function getAbsolutePath($path, $repository) {
        $settings = $this->getRepositorySettings($repository);
        $dirs = $settings['dirs'];

        // Xác định brand và relpath
        $branch = '';

        if (preg_match('/^([0-9]+)\:\/\/(.*)/', trim($path), $match)) {
            $branch = $match[1];
            $path = $match[2];
        }

        $path = ltrim($path, '/');

        if (!empty($branch)) {
            $branchDir = isset($dirs[$branch - 1])? $dirs[$branch - 1] : '';

            if (!empty($branchDir) && is_dir($branchDir)) {
                $branchDir = rtrim($branchDir, '/');

                $path = $branchDir.'/'.$path;
            }
        } else {
            foreach ($dirs as $dir) {
                $dir = rtrim($dir, '/');

                if (file_exists($dir.'/'.$path)) {
                    $path = $dir.'/'.$path;

                    break;
                }
            }
        }

        return $path;
    }

    function getAbsoluteFilePath($filename, $repository) {
        $filepath = isset($_SESSION['filepaths'][$repository][$filename]) ? $_SESSION['filepaths'][$repository][$filename] : '';

        $is_temp_file = ($filename[0] == '*');

        if (!empty($filepath) && !$is_temp_file && basename($filename) !== basename($filepath)) {
            $filepath = null;
        }

        // Xác định brand và relpath
        $branch = '';

        if (preg_match('/^([0-9]+)\:\/\/(.*)/', trim($filename), $match)) {
            $branch = $match[1];
            $filename = $match[2];
        }

        if (!empty($branch)) {
            $branchDir = isset($dirs[$branch - 1])? $dirs[$branch - 1] : '';

            if (!empty($branchDir) && is_dir($branchDir)) {
                $branchDir = rtrim($branchDir, '/');

                $filepath = $branchDir.'/'.$filename;
            }
        }

        if (empty($filepath)) {
            $filepath = $this->searchForFile($filename, $repository);
        }

        if (empty($filepath)) {
            $settings = $this->getRepositorySettings($repository);
            $dirs = $settings['dirs'];

            foreach ($dirs as $dir) {
                if (file_exists($dir.'/'.$filename)) {
                    return $dir.'/'.$filename;
                }
            }
        }

        return $filepath;
    }

    function sync_file($filename, $repository, $revert = false) {
        $filepath = $this->getAbsoluteFilePath($filename, $repository);

        if (!file_exists($filepath)) {
            return;
        }

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

            return;
        }

        $sync_dest = $this->get_sync_dest($filepath);

        if (!empty($sync_dest)) {
            // Ensure directory
            $dir = dirname($sync_dest);

            if (!empty($dir) && !is_dir($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    \CloudPad\Core\Response::json(array('success' => false, 'message' => "[ERROR] Cannot create directory : $dir"));

                    return false;
                }
            }

            // Revert
            if ($revert) {
                $tmp = $filepath;

                $filepath = $sync_dest;
                $sync_dest = $tmp;
            }

            // Sync
            $content = $this->file_get_contents($filepath, $repository);

            if ($this->file_put_contents($sync_dest, $content, '', $message)) {
                $message = "File synced.";
            } else {
                $message = "Sync failed. $message";
            }
        } else {
            $message = "Sync is not enabled for this file.";
        }

        \CloudPad\Core\Response::json(array('success' => true, 'message' => $message));
    }

    function get_sync_dest($filepath) {
        $repository = $this->getFileRepository($filepath);

        if (!empty($repository)) {
            $settings = $this->getRepositorySettings($repository);

            if (isset($settings['sync']) && !empty($settings['sync'])) {
                  $sync_routes += $settings['sync'];
            }
        }

        foreach ($sync_routes as $s => $d) {
            if (stripos($filepath, $s) === 0) {
                return str_replace($s, $d, $filepath);
            }
        }
    }

    function rebuild_sub_indexes($filename, $repository, $revert = false) {
        $filepath = $this->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath) || !file_exists($filepath))  {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

            return;
        }

        $dir = dirname($filepath);

        $cachefile = $this->getRepositoryCacheFile($repository);

        $filepaths = json_decode($this->file_get_contents($cachefile), true) ?: [];

        $paths = $this->rsearch($dir, $excludes, $includes);

        if (!empty($paths)) {
            $filepaths = array_merge($filepaths, $paths);
        }

        $this->file_put_contents($cachefile, json_encode($filepaths));

        \CloudPad\Core\Response::json(array('success' => true, 'message' => 'Indexing done'));
    }

    function save_current_file($filename, $repository, $content, $creat_temp_revision_only) {
        $filepath = $this->getAbsoluteFilePath($filename, $repository);

        if (empty($filepath)) {
            \CloudPad\Core\Response::json(array('success' => false, 'message' => 'Source file not found.'));

            return;
        }

        $old_content = $this->file_get_contents($filepath, $repository);

        // Safety check
        $segmentsize = 500;

        $content_segment = substr($content, 0, $segmentsize);
        $file_segment = substr($old_content, 0, $segmentsize);

        $is_temp_file = ($filename[0] == '*');
        $not_temp_content = stripos($content, '<?php') !== false || stripos($content, '<{$smarty') !== false;

        if ($is_temp_file && $not_temp_content) {
            $this->flush_line("[ERROR] Saving a wrong content to '" . $filename . "', possibly", true);
            return;
        }

        $protection = stripos($filepath, '/builder/') === 0 && !preg_match('/^\/builder\/(config|apps|tmp)/is', $filepath);

        if ($protection && !$this->is_same_content($content_segment, $file_segment)) {
            $this->flush_line("[ERROR] $filepath Save to a wrong file '" . basename($filepath) . "'???", true);
            return;
        }

        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        if (trim($content) != '') {
            if ($content != $old_content) {
                if ($creat_temp_revision_only) {
                    $this->create_temp_revision($filepath, $content);
                } else {

                    $this->save_file_revision($filepath, $old_content, false);

                    $this->onBeforeSavingFile($content, $extension);

                    $this->file_put_contents($filepath, $content, $repository);

                    $_SESSION['openfilepaths'][$repository][$filename] = $filepath;
                }
            }
        } else {
            $this->flush_line("[ERROR] Cannot save an empty content to '" . basename($filepath) . "'", true);
        }
    }

    function convert_tabs_to_whitespaces($content) {
        return str_replace("\t", '    ', $content);
    }

    function trim_trailing_whitespaces($content) {
        $lines = explode("\n", $content);

        foreach ($lines as $i => $line) {
            $lines[$i] = rtrim($line);
        }

        return trim(implode("\n", $lines));
    }

    function trim_trailing_commas($content) {
        return preg_replace_callback(
            '/,(\s+)(\]|\}|\))/s', // IMPORTANT: \s+
            function ($matches) {
                return $matches[1].$matches[2];
            },
            $content
        );
    }

    function onBeforeSavingFile(&$content, $extension) {
        $is_web_file = in_array($extension, array('tpl', 'php', 'js', 'vue', 'html', 'css'));

        if ($is_web_file) {
            $content = $this->convert_tabs_to_whitespaces($content);
        }

        $content = $this->trim_trailing_whitespaces($content);

        if ($is_web_file) {
            $content = $this->trim_trailing_commas($content);

            $content = $content."\n";
        }
    }

    function is_same_content($content1, $content2) {
        $content1 = preg_replace('/^\s+|\n|\r|\s+$/m', '', $content1);
        $content2 = preg_replace('/^\s+|\n|\r|\s+$/m', '', $content2);

        $size = min(strlen($content1), strlen($content2));

        $content1 = substr($content1, 0, $size);
        $content2 = substr($content2, 0, $size);

        if ($content1 != $content2) {
            echo "content1 = $content1<br/>";
            echo "content2 = $content2<br/>";
        }

        return $content1 == $content2;
    }

    function setFilePath($filename, $filepath, $repository)
    {
        $_SESSION['filepaths'][$repository][$filename] = $filepath;
        $_SESSION['openfilepaths'][$repository][$filename] = $filepath;
        $_SESSION['recentfilepaths'][$repository][$filename] = $filepath;
    }

    function addToRepositoryFilePaths($filepath, $repository)
    {
        $cachefile = __DIR__ . '/cache/' . $repository;

        if (file_exists($cachefile)) {
            $filepaths = json_decode($this->file_get_contents($cachefile), true) ?: [];

            $filepaths[] = $filepath;

            $this->file_put_contents($cachefile, json_encode($filepaths));
        }
    }

    function getOpenFiles($temp_files_only) {
        $paths = array();

        $tmp_paths = $this->getUserTempFilePaths();

        foreach ($tmp_paths as $path) {
            $name = '*'.basename($path);

            $paths['*'][$name] = $path;

            $_SESSION['filepaths']['*'][$name] = $path;
        }

        if (!$temp_files_only) {
            $open_paths = isset($_SESSION['openfilepaths']) && is_array($_SESSION['openfilepaths'])? $_SESSION['openfilepaths'] : array();

            foreach ($open_paths as $repository => $repository_files) {
                foreach ($repository_files as $name => $path) {
                    $paths[$repository][$name] = $path;
                }
            }

            $_SESSION['openfilepaths'] = $paths;
        }

        return $paths;
    }

    function getEditorOpenFiles($temp_files_only = false) {
        $files = $this->getOpenFiles($temp_files_only);

        foreach ($files as $repo => $repo_files) {
            $files[$repo] = array_keys($repo_files);
        }

        return $files;
    }

    function searchForFile($filename, $repository) {
        $files = $this->searchForFiles($filename, $repository, 1, true);

        return !empty($files) ? $files[0] : '';
    }

    function searchForFiles($filename, $repository, $limit = 0, $exact = false)
    {
        $filepaths = $this->getRepositoryFilePaths($repository, false);

        return $this->searchForFilesInArray($filename, $filepaths, $limit, $exact);
    }

    function searchForFilesInArray($filename, $filepaths, $limit = 0, $exact = false)
    {
        $nameonly  = stripos($filename, '/') === false;
        $withpath  = !$nameonly;
        $withregex = stripos($filename, '*') !== false;

        if ($withregex) {
            $filename_regex = $filename;
            $filename_regex = str_replace('.', '\.', $filename_regex);
            $filename_regex = str_replace('/', '\/', $filename_regex);
            $filename_regex = str_replace('-', '\-', $filename_regex);
            $filename_regex = str_replace('*', '.*', $filename_regex);

            $filename_regex = '/' . $filename_regex . '/is';
        }

        $results = array();
        $count   = 0;

        $substrLength = strlen($filename);

        foreach ($filepaths as $path) {
            if ((!$exact && stripos($path, $filename) !== false) || ($exact && substr_compare($path, $filename, -$substrLength) === 0) || $withregex && preg_match($filename_regex, $path)) {
                $results[] = $path;
                $count += 1;

                if ($limit && $count >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    function verbose($content)
    {
        $verbose = \CloudPad\Core\Request::getBool('verbose', true);

        if ($verbose) {
            $this->flush_line($content);
        }
    }

    function is_path_matched($path, $pattern) {
        if (strpos($pattern, '*') === false) {
            return stripos($path, $pattern) !== false;
        }

        $pattern = str_replace(['/', '.', '*'], ['\/', '\.', '.*'], $pattern);

        return preg_match('/'.$pattern.'/i', $path);
    }

    function is_excluded_path($file, $excludes, $includes)
    {
        foreach ($includes as $include) {
            if ($this->is_path_matched($file, $include)) {
                return false;
            }
        }

        foreach ($excludes as $exclude) {
            if ($this->is_path_matched($file, $exclude)) {
                return true;
            }
        }

        return false;
    }

    function glob($dir) {
        $tree = array();

        if (is_dir($dir)) {
            $iterator = new DirectoryIterator($dir);

            foreach ($iterator as $entry) {
                if ($entry->isFile() || ($entry->isDir() && !$entry->isDot() && $entry->getFilename() !== '.svn')) {
                    $tree[] = $entry->getPathname();
                }
            }
        } else {
            $this->verbose('[ERROR] Directory does not exist <-- ' . $dir);
        }

        return $tree;
    }

    function rsearch($dir, $excludes = array(), $includes = array(), $fs_prefix = '') {
        $dirs = array(rtrim($dir, '/'));
        $filepaths = array();

        while($dirs) {
            $dir = array_pop($dirs);
            $tree = $this->glob($dir);

            if (is_array($tree)) {
                foreach ($tree as $file) {
                    if ($this->is_excluded_path($file, $excludes, $includes)) {
                        continue;
                    }

                    if (is_dir($file) && basename($file) != 'cache') {
                        $dirs[] = $file;
                    } elseif (is_file($file)) {
                        $filepaths[] = str_replace($fs_prefix, '', $file);
                    }
                }
            }
        }

        return $filepaths;
    }

    function getRepositoryCacheFile($repository) {
        $settings = $this->getRepositorySettings($repository);

        $dirs      = $settings['dirs'];

        $signature = md5($repository.implode(',', $dirs));

        $cachefile = __DIR__ . '/cache/' . $signature;

        return $cachefile;
    }

    // IMPORTANT: SFTP resource should not be closed  before accessing files.
    // So, a return variable &$sftp should be used to keep this resource alive
    // after function exiting
    function get_sftp_prefix($host, $port, $username, $password, &$sftp) {
        $connection = ssh2_connect($host, $port);

        if (!$connection) {
            $this->verbose('[ERROR] Cannot connect to the SFTP server --> '.$host.':'.$port);
            return;
        }

        // Authentication using a public key
        $authenticated = ssh2_auth_password ($connection, $username, $password);

        if (!$authenticated) {
            $this->verbose('[ERROR] Cannot authenticate with the SFTP server using username/password');
            return;
        }

        // Initialize SFTP subsystem
        $sftp = ssh2_sftp($connection);
        $sftp_fd = intval($sftp);

        return 'ssh2.sftp://'.$sftp_fd;
    }

    function getRepositoryFilePaths($repository, $force_rebuild = false) {
        if (!$this->hasRepositoryPermission($repository)) {
            return array();
        }

        $settings = $this->getRepositorySettings($repository);

        $dirs = $settings['dirs'];
        $excludes = isset($settings['excludes']) ? $settings['excludes'] : array();
        $includes = isset($settings['includes']) ? $settings['includes'] : array();

        $excludes[] = '/node_modules/';
        $excludes[] = '/vendor/';
        $excludes[] = '/.git/';
        $excludes[] = '/.svn/';

        $signature = md5($repository.implode(',', $dirs));

        $cachefile = __DIR__ . '/cache/' . $signature;

        if (!file_exists($cachefile) || $force_rebuild) {
            $is_sftp = isset($settings['sftp'])
                && isset($settings['sftp']['host'])
                && isset($settings['sftp']['port'])
                && isset($settings['sftp']['username'])
                && isset($settings['sftp']['password']);

            if ($is_sftp) {
                $fs_prefix = $this->get_sftp_prefix($settings['sftp']['host'], $settings['sftp']['port'], $settings['sftp']['username'], $settings['sftp']['password'], $sftp);

                if (empty($fs_prefix)) {
                    $this->verbose("[ERROR] Cannot connect to remote file system via SFTP\n");

                    return array();
                }
            } else {
                $fs_prefix = '';
            }

            $this->rebuildIndexesUsingRust($dirs, $cachefile);
        }

        $filepaths = include $cachefile;

        return $filepaths;
    }

    function getProjectFilePaths($repository, $force_rebuild = false) {
        if (!$this->hasRepositoryPermission($repository)) {
            return array();
        }

        $settings = $this->getRepositorySettings($repository);

        $dirs = $settings['dirs'];
        $excludes = isset($settings['excludes']) ? $settings['excludes'] : array();
        $includes = isset($settings['includes']) ? $settings['includes'] : array();

        $excludes[] = '/node_modules/';
        $excludes[] = '/vendor/';
        $excludes[] = '/.git/';
        $excludes[] = '/.svn/';

        $signature = md5($repository.implode(',', $dirs));

        $cachefile = dirname(__FILE__) . '/cache/' . $signature;

        if (!file_exists($cachefile) || $force_rebuild) {
            $is_sftp = isset($settings['sftp'])
                && isset($settings['sftp']['host'])
                && isset($settings['sftp']['port'])
                && isset($settings['sftp']['username'])
                && isset($settings['sftp']['password']);

            if ($is_sftp) {
                $fs_prefix = $this->get_sftp_prefix($settings['sftp']['host'], $settings['sftp']['port'], $settings['sftp']['username'], $settings['sftp']['password'], $sftp);

                if (empty($fs_prefix)) {
                    $this->verbose("[ERROR] Cannot connect to remote file system via SFTP\n");

                    return array();
                }
            } else {
                $fs_prefix = '';
            }

            // $filepaths = array();

            // foreach ($dirs as $dir) {
            //     $paths = $this->rsearch($fs_prefix.$dir, $excludes, $includes, $fs_prefix);

            //     if (!empty($paths)) {
            //         $filepaths = array_merge($filepaths, $paths);
            //     }
            // }

            $this->rebuildIndexesUsingRust($dirs, $cachefile);
        }

        $filepaths = include $cachefile;

        return $filepaths;
    }

    private function rebuildIndexesUsingRust($dirs, $outputFile) {
        $rustBinary = __DIR__.'/bin/rust/rebuild-indexes/target/release/rebuild-indexes';

        if (!is_file($rustBinary)) {
            $this->error('Rebuild binary not found. Please build the Rust binary first.');
        }

        // FIX: escapeshellarg() cho từng dir và outputFile
        $escapedDirs = array_map('escapeshellarg', $dirs);
        $command = escapeshellarg($rustBinary) . ' ' . implode(' ', $escapedDirs) . ' ' . escapeshellarg($outputFile);

        $this->try_exec($command, $error);

        if (!empty($error)) {
            $this->error($error);
        }
    }

    function getFileRepository($filepath) {
        $repositories = $this->getRepositories();

        foreach ($repositories as $repository => $settings) {
            $dirs = $settings['dirs'];

            foreach ($dirs as $dir) {
                if (stripos($filepath, $dir) !== 0) {
                    continue;
                }

                $filepaths = $this->getRepositoryFilePaths($repository, false);

                if (in_array($filepath, $filepaths)) {
                    return $repository;
                }
            }
        }
    }

    function getRepositoryWisePath($filepath, $repository, $filename) {
        if (empty($repository)) {
            return $filename;
        }

        $settings = $this->getRepositorySettings($repository);

        $dirs = $settings['dirs'] ?? [];

        foreach ($dirs as $index => $dir) {
            if (stripos($filepath, $dir) !== 0) {
                continue;
            }

            $filepath = str_replace(rtrim($dir, '/').'/', ($index + 1).'://', $filepath);
            break;
        }

        return $filepath;
    }

    function get_enabled_plugins() {
        return $this->getEnabledPluginsOfCurrentUser();
    }

    function getAppModules() {
        $subdirs = $this->getAvailablePluginsOfCurrentUser();

        $modules = array();

        foreach ($subdirs as $name) {
            if ($this->has_plugin_tab($name, $handler)) {
                $info = $handler->getPluginInfo();

                if (!empty($info)) {
                    $cat = isset($info['category'])? $info['category'] : '';

                    $modules[$cat][$name] = $info;
                }
            }
        }

        return $modules;
    }

    function getRepositoriesFromFile() {
        return  include(__DIR__.'/repositories.conf.php');
    }

    function getRepositories() {
        static $repositories = null;

        if ($repositories === null) {
            $all = $this->getRepositoriesFromFile();

            $repos = $_SESSION['builder.user']['repositories'];

            $repositories = array();

            foreach ($repos as $repo) {
                if (!isset($all[$repo])) {
                    continue;
                }

                $settings = $all[$repo];

                $handler = $this->getRepositoryHandler($settings);

                if (empty($handler)) {
                    $this->error("Cannot get handler of repository '".$settings['name']."'");

                    continue;
                }

                if (!$handler->isAccessible($settings)) {
                    continue;
                }

                $handler->init($settings);

                $settings['handler'] = $handler;

                if (isset($settings['code'])) {
                    $realrepo = $settings['code'];
                } else {
                    $realrepo = $repo;
                }

                $repositories[$realrepo] = $settings;
            }
        }

        return $repositories;
    }

    function getRepositoryHandler($settings) {
        $fs = isset($settings['type'])? $settings['type'] : 'local';

        if ($this->has_plugin_fs($fs, $handler)) {
            return $handler;
        }

        return null;
    }

    function has_plugin_fs($fs, &$handler) {
        $handler = null;

        $dir = __DIR__.'/plugins/fs';

        $filepath = $dir."/$fs/$fs.php";

        if (!file_exists($filepath)) {
            $this->verbose("[ERROR] Plugin file '$filepath' is not found");

            return false;
        }

        require_once($filepath);

        $filename = basename($filepath);
        $classname = 'plugin_fs_'.$fs;

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

    function getCurrentUser() {
        return $_SESSION['builder.user'];
    }

    static function getAvailablePluginsOfCurrentUser() {
        $plugins = isset($_SESSION['builder.user']['plugins'])? $_SESSION['builder.user']['plugins'] : array();

        if (!empty($_SESSION['builder.user']['repositories'])) {
            $plugins[] = 'editor';
        }

        asort($plugins);

        return $plugins;
    }

    static function getEnabledPluginsOfCurrentUser() {
        $plugins = $_SESSION['builder.user']['plugins'] ?? [];

        return $plugins;
    }

    static function getCurrentUserPermission() {
        return ['editor', 'snr'];
    }

    function getCurrentUsername() {
        return $_SESSION['builder.username'];
    }

    function hasRepositoryPermission($repository) {
        if (empty($repository)) {
            return true;
        }

        $repositories = $this->getRepositories();

        return isset($repositories[$repository]);
    }

    function getUsers() {
        return  include(__DIR__.'/users.conf.php');
    }

    static function hasPermission($key) {
        $perms = self::getCurrentUserPermission();

        return in_array($key, $perms) || in_array('all', $perms);
    }

    function get_sub_dirs($dir) {
        $paths = glob("$dir/*");

        $sub_dirs = array();

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $sub_dirs[] = basename($path);
            }
        }

        return $sub_dirs;
    }

    function has_plugin_tab($tab, &$handler) {
        $handler = null;

        $dir = __DIR__.'/plugins/tabs';

        $filepath = $dir."/$tab/index.php";

        if (!file_exists($filepath)) {
            return false;
        }

        require_once($filepath);

        $filename = basename($filepath);
        $classname = 'plugin_tab_'.str_replace('-', '_', $tab);

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

    function getUserTabs() {
        static $handlers = null;

        if ($handlers === null) {
            $tabs = Builder::getEnabledPluginsOfCurrentUser();

            $handlers = array();

            foreach ($tabs as $tab) {
                if (Builder::has_plugin_tab($tab, $handler)) {
                    $handlers[$tab] = $handler;
                }
            }
        }

        return $handlers;
    }

    function getRepositorySettings($repository) {
        static $cache = [];

        if ($repository == '*') {
            return array();
        }

        if (isset($cache[$repository])) {
            return $cache[$repository];
        }

        $repositories = $this->getRepositories();

        if (!isset($repositories[$repository])) {
            $this->verbose("[ERROR] Repository '$repository' is not found");
            return;
        }

        $cache[$repository] = $repositories[$repository];

        return $cache[$repository];
    }

    function file_mask_matched($pattern, $path) {
        $patterns = preg_split('/[,;\s]+/', $pattern, -1, PREG_SPLIT_NO_EMPTY);
        $include_patterns = [];
        $exclude_patterns = [];

        foreach ($patterns as $pat) {
            $pat = trim($pat);
            if (empty($pat)) {
                continue;
            }

            if ($pat[0] === '-') {
                $pat = substr($pat, 1);
                $exclude_patterns[] = $pat;
            } else {
                $include_patterns[] = $pat;
            }
        }

        // Check exclude patterns first
        foreach ($exclude_patterns as $pat) {
            $regex = str_replace(['/', '.', '*'], ['\/', '\.', '.+'], $pat);
            $regex = '/' . $regex . '/is';

            if (preg_match($regex, $path)) {
                return false;
            }
        }

        // Check include patterns
        foreach ($include_patterns as $pat) {
            $regex = str_replace(['/', '.', '*'], ['\/', '\.', '.+'], $pat);
            $regex = '/' . $regex . '/is';

            if (preg_match($regex, $path)) {
                return true;
            }
        }

        return false;
    }

    function snr_search($repository, $search, $caseinsensitive = true, $file_mask = '', $max_found_files = 20, $search_by_filename = false, $force_replace = false, $replace = '', $force_delete = false)
    {
        $filepaths = $this->getRepositoryFilePaths($repository, false);

        $search_file = 0;
        $found_file  = 0;
        $found_occ   = 0;

        $manual_commands = array();

        foreach ($filepaths as $path) {
            if (!$this->file_mask_matched($file_mask, $path)) {
                continue;
            }

            $relpath = $this->getRelPath($path, $repository);

            if ($search_by_filename) {
                if ($this->file_mask_matched($search, $path)) {
                    $found_file += 1;

                    if ($force_delete) {
                        $manual_commands[] = "svn delete $path";
                    } else {
                        $this->flush("\nFound file: <span data-url=\"index.php?action=open-inline-file&repository=$repository&file=$relpath\" class=\"snr-file js-snr-file\">$path</span>\n");
                    }

                    if ($found_file >= $max_found_files) {
                        break;
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }
            }

            $search_file += 1;

            $handle = fopen($this->getLocalizedPath($path, $repository), "r");
            $found  = false;
            $cnt    = 0;
            $occ    = 0;

            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);

                    $cnt += 1;

                    if ($caseinsensitive) {
                        $pos = $this->snr_get_pos($line, $search, true, $full_matched_segment);
                    } else {
                        $pos = $this->snr_get_pos($line, $search, false, $full_matched_segment);
                    }

                    if ($pos !== false) {
                        if (!$found) {
                            $this->flush("<div class=\"snr-item\" data-repository=\"$repository\" data-file=\"$relpath\"><span class=\"snr-item-header\">Processing file: <span class=\"snr-file\">$relpath</span></span>\n");
                            $this->flush("<div class=\"snr-item-body\">");
                        }
                        $found = true;

                        if ($caseinsensitive) {
                            $occ += substr_count(strtoupper($line), strtoupper($full_matched_segment));
                        } else {
                            $occ += substr_count($line, $full_matched_segment);
                        }

                        $segment = substr($line, max(0, $pos - 50), strlen($full_matched_segment) + 50);

                        $segment = htmlentities($segment, ENT_QUOTES);
                        $_search  = htmlentities($full_matched_segment, ENT_QUOTES);

                        $segment = preg_replace('/(' . preg_quote($_search, '/') . ')/s' . ($caseinsensitive ? 'i' : ''), '<span class="snr-match">\\1</span>', $segment);
                        $segment = trim($segment);

                        $this->flush("<span data-line=\"$cnt\" data-url=\"index.php?action=open-inline-file&repository=$repository&file=$relpath&line=$cnt\" class=\"snr-line js-snr-file\">- Line $cnt -&nbsp;&nbsp;&nbsp;&nbsp; $segment</span>\n");

                        // x. Replace
                        if ($force_replace && !empty($replace)) {
                            $segment = substr($line, max(0, $pos - 50), strlen($full_matched_segment) + 50);
                            $segment = str_replace($full_matched_segment, $replace, $segment);

                            $segment = htmlentities($segment, ENT_QUOTES);
                            $_replace  = htmlentities($replace, ENT_QUOTES);

                            $segment = preg_replace('/(' . preg_quote($_replace, '/') . ')/s' . ($caseinsensitive ? 'i' : ''), '<span class="snr-replacement">\\1</span>', $segment);
                            $segment = trim($segment);

                            $this->flush("<span data-line=\"$cnt\" data-url=\"index.php?action=open-inline-file&repository=$repository&file=$relpath&line=$cnt\" class=\"snr-line js-snr-file\">- Replaced by -&nbsp;&nbsp;&nbsp;&nbsp; $segment</span>\n");
                        }
                    }
                }

                fclose($handle);
            } else {
                // error opening the file.
            }

            if ($found) {
                // x. Replace
                if ($force_replace) {
                    $this->snr_replace($path, $search, $caseinsensitive, $replace, $repository);
                }

                // x. Delete
                if ($force_delete) {
                    unlink($path);

                    $this->flush("<div class=\"snr-item\"><span class=\"snr-item-header\">Deleting file: <span class=\"snr-file\">$path</span></span>\n");
                }

                // x. xxx
                $found_file += 1;
                $found_occ += $occ;

                $this->flush("  <span>Found $occ occurrences.</span>");
                $this->flush("</div>");
                $this->flush("</div>");
            }

            if ($found_file >= $max_found_files) {
                break;
            }
        }

        if (!empty($manual_commands)) {
            $this->flush(implode("\n", $manual_commands));
        }

        if (!$force_delete) {
            $this->flush("  <span class=\"snr-file\">Searched $search_file file(s), found $found_occ occurrences in $found_file file(s).</span>\n");
        } else {
            $this->flush("  <span class=\"snr-file\">Searched $search_file file(s), deleted $found_file file(s).</span>\n");
        }

        if (!$found_occ) {
            $this->flush("  <span style=\"color:red\">HINTS: Rebuild the repository's indexes and try again.</span>\n");
        }
    }

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

    function ssh_ensure_safe_command($command) {
        if (preg_match('/(rm|rmdir)\s+/i', $command) && !preg_match('/(svn delete)\s+/i', $command)) {
            $this->flush_line("[ERROR] Unsafe commands are not allowed. Please check again.", true);

            exit(-1);
        }
    }

    function ssh_exec($exec_builtin = true, $exec_custom = true) {
        $requires = ['SSH_HOST', 'SSH_PORT', 'SSH_USERNAME'];

        foreach ($requires as $name) {
            if (empty(\CloudPad\Core\Request::getString($name))) {
                $this->flush_line("[ERROR] $name is required\n", true);
                return;
            }
        }

        $ssh_command       = \CloudPad\Core\Request::getString('SSH_COMMAND');
        $ssh_commands      = \CloudPad\Core\Request::getString('SSH_COMMANDS');
        $ssh_command_names = \CloudPad\Core\Request::getArray('SSH_COMMAND_NAMES');

        if (empty($ssh_command_names) && empty($ssh_command) && empty($ssh_commands)) {
            $this->flush_line("[ERROR] Please specify a command\n", true);
            return;
        }

        $host          = \CloudPad\Core\Request::getString('SSH_HOST');
        $port          = \CloudPad\Core\Request::getInt('SSH_PORT', (int) SSH_PORT);
        $username      = \CloudPad\Core\Request::getString('SSH_USERNAME');
        $password      = \CloudPad\Core\Request::getString('SSH_PASSWORD');
        $command       = $ssh_command;
        $commands      = $ssh_commands;
        $command_names = $ssh_command_names;

        $actual_commands = array();

        $shell_commands = $this->getFrequentUsedShellCommands();

        if ($exec_builtin && !empty($command_names)) {
            foreach ($command_names as $name) {
                $command = isset($shell_commands[$name])? $shell_commands[$name] : '';

                if (!empty($command)) {
                    $actual_commands[] = $command;
                }
            }
        } else if ($exec_custom) {
            if (!empty($commands)) {
                $actual_commands = explode("\n", $commands);
            } else {
                $actual_commands = array($command);
            }
        }

        if (empty($password) && isset($_SESSION['SSH_PASSWORD'])) {
            $password = $_SESSION['SSH_PASSWORD'];
        }

        $ssh = new SSH2($host, $port);

        if (true) {
            $ssh = new SSH2('localhost', SSH_PORT);

            $rsaPrivateKey = file_get_contents(SSH_RSA_PRIVATE_FILE);

            if (empty($rsaPrivateKey)) {
                exit("Cannot read private key file");
            }

            $key = new RSA();
            $key->setPassword(SSH_RSA_PASSPHRASE);
            $key->loadKey($rsaPrivateKey);

            if (!$ssh->login(SSH_RSA_USERNAME, $key)) {
                echo $ssh->getLastError();
                exit('SSH login failed');
            }
        } else {
            if (!$ssh->login($username, $password)) {
                echo $ssh->getLastError();
                exit('SSH login failed');
            }
        }

        $ssh->setWindowColumns(160);

        $pty_required_commands = array('top', 'sudo', 'svn');

        foreach ($actual_commands as $command) {
            $command = trim($command);

            if (empty($command) || $command[0] == '#') {
                continue;
            }

            $this->ssh_ensure_safe_command($command);

            list($_command) = explode(' ', $command);

            $require_pty = in_array($_command, $pty_required_commands);

            if ($require_pty) {
                $ssh->enablePTY();

                $ssh->exec($command);

                $ssh->setTimeout(100);

                $output = $ssh->read();
            } else {
                $output = $ssh->exec($command);
            }

            $output = $this->ssh_getAugmentedOutput($output, $command);

            echo $output;
        }

        $_SESSION['SSH_HOST'] = $host;
        $_SESSION['SSH_PORT'] = $port;
        $_SESSION['SSH_USERNAME'] = $username;
        $_SESSION['SSH_PASSWORD'] = $password;
    }

    function private_ssh_exec($commands) {
        if (is_string($commands)) {
            $commands = explode("\n", $commands);
        }

        $ssh = new SSH2('localhost', SSH_PORT);

        $rsaPrivateKey = file_get_contents(SSH_RSA_PRIVATE_FILE);

        if (empty($rsaPrivateKey)) {
            exit("Cannot read private key file");
        }

        $key = new RSA();
        $key->setPassword(SSH_RSA_PASSPHRASE);
        $key->loadKey($rsaPrivateKey);

        if (!$ssh->login(SSH_RSA_USERNAME, $key)) {
            exit('SSH login failed');
        }

        $ssh->setWindowColumns(160);

        $pty_required_commands = array('top', 'sudo', 'svn');

        foreach ($commands as $command) {
            $command = trim($command);

            if (empty($command) || $command[0] == '#') {
                continue;
            }

            $this->ssh_ensure_safe_command($command);

            list($_command) = explode(' ', $command);

            $require_pty = in_array($_command, $pty_required_commands);

            if ($require_pty) {
                $ssh->enablePTY();

                $ssh->exec($command);

                $ssh->setTimeout(100);

                $output = $ssh->read();
            } else {
                $output = $ssh->exec($command);
            }

            $output = $this->ssh_getAugmentedOutput($output, $command);

            echo $output;
        }
    }

    function ssh_getAugmentedOutput($output, $command) {
        return $output;
    }

    function ssh_exec_2($commands, $verbose = true) {
        static $ssh = null;

        if ($ssh === null) {
            $requires = array(
                'SSH_HOST',
                'SSH_PORT',
                'SSH_USERNAME',
                'SSH_PASSWORD'
            );

            foreach ($requires as $name) {
                if (empty($_SESSION[$name])) {
                    $this->flush_line("[ERROR] $name is required\n", true);

                    return;
                }
            }

            $host = $_SESSION['SSH_HOST'];
            $port = $_SESSION['SSH_PORT'];
            $username = $_SESSION['SSH_USERNAME'];
            $password = $_SESSION['SSH_PASSWORD'];

            $ssh = new SSH2($host, $port);

            if (!$ssh->login($username, $password)) {
                exit('SSH login failed');
            }

            $ssh->setWindowColumns(160);
        }

        $command = is_array($commands)? implode(';', $commands) : $commands;

        $pty_required_commands = array('top', 'sudo', 'svn');

        list($_command) = explode(' ', $command);

        $this->ssh_ensure_safe_command($command);

        $require_pty = in_array($_command, $pty_required_commands);

        if ($require_pty) {
            $ssh->enablePTY();

            $ssh->exec($command);

            $ssh->setTimeout(100);

            $output = $ssh->read();
        } else {
            $output = $ssh->exec($command);
        }

        $output = $this->ssh_getAugmentedOutput($output, $command);

        if ($verbose) {
            echo $output;
        }

        return $output;
    }

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
