<?php
namespace CloudPad\Plugin;

use CloudPad\Auth\AuthServiceInterface;
use CloudPad\Core\Output\OutputManager;
use CloudPad\Core\Request;
use CloudPad\Core\Session\NativeSession;
use CloudPad\Repository\RepositoryManagerInterface;

/**
 * PluginManager — Owns all plugin discovery, tab loading, FS loading,
 * and frequent-command resolution.
 *
 * Extracted from Builder (Phase 10.5 / R5 2026-03).
 * Builder delegates all plugin-related calls here.
 *
 * FS plugins receive $this (PluginManager) as their "$builder" argument.
 * They only call getUserRepositoryDir() on it, which this class proxies
 * from AuthService.
 */
class PluginManager
{
    private string                     $appDir;
    private OutputManager              $output;
    private RepositoryManagerInterface $repoManager;
    private AuthServiceInterface       $auth;

    public function __construct(
        string                     $appDir,
        OutputManager              $output,
        RepositoryManagerInterface $repoManager,
        AuthServiceInterface       $auth
    ) {
        $this->appDir      = $appDir;
        $this->output      = $output;
        $this->repoManager = $repoManager;
        $this->auth        = $auth;
    }

    // ── FS plugin discovery ───────────────────────────────────────────────────

    /**
     * Load filesystem plugin handler by type (local | git | sftp | svn).
     * Populates $handler with a plugin_fs_* instance on success.
     * Called by RepositoryManager via the pluginFsLoader callback.
     */
    public function loadFsPlugin(string $fs, mixed &$handler): bool
    {
        $handler  = null;
        $filepath = $this->appDir . "/plugins/fs/$fs/$fs.php";

        if (!file_exists($filepath)) {
            $this->output->verbose("[ERROR] Plugin file '$filepath' is not found");
            return false;
        }

        require_once $filepath;

        $classname = 'plugin_fs_' . $fs;

        if (!class_exists($classname)) {
            $this->output->verbose("[ERROR] Class '$classname' is not found in file '$fs.php'");
            return false;
        }

        // FS plugins receive $this so they can call getUserRepositoryDir()
        $handler = new $classname($this);

        $required = ['init', 'getRepositoryOperations', 'getLocalizedPath'];
        foreach ($required as $method) {
            if (!method_exists($handler, $method)) {
                $handler = null;
                $this->output->verbose(
                    "[ERROR] Class '$classname' must declare: " . implode(', ', $required)
                );
                return false;
            }
        }

        return true;
    }

    // ── Tab plugin discovery ──────────────────────────────────────────────────

    /**
     * Load tab plugin handler by slug.
     * Called by getUserTabs().
     */
    public function loadTabPlugin(string $tab, mixed &$handler): bool
    {
        $handler  = null;
        $filepath = $this->appDir . "/plugins/tabs/$tab/index.php";

        if (!file_exists($filepath)) {
            return false;
        }

        require_once $filepath;

        $classname = 'plugin_tab_' . str_replace('-', '_', $tab);

        if (!class_exists($classname)) {
            $this->output->verbose("[ERROR] Class '$classname' is not found in tabs/$tab/index.php");
            return false;
        }

        $handler  = new $classname();
        $required = ['getTabTitle', 'getPluginInfo', 'render'];

        foreach ($required as $method) {
            if (!method_exists($handler, $method)) {
                $handler = null;
                $this->output->verbose(
                    "[ERROR] Class '$classname' must declare: " . implode(', ', $required)
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Return all loaded tab handlers for the current user.
     * Called by Builder::getUserTabs() and tpl/index.tpl.
     */
    public function getUserTabs(): array
    {
        static $handlers = null;

        if ($handlers === null) {
            $handlers = [];
            foreach (self::getEnabledPluginsOfCurrentUser() as $tab) {
                if ($this->loadTabPlugin($tab, $handler)) {
                    $handlers[$tab] = $handler;
                }
            }
        }

        return $handlers;
    }

    // ── Shell command resolution ──────────────────────────────────────────────

    /**
     * Return the frequent-used shell commands for the current repository.
     * Reads repository from the current request; used by SSHService::sshExec().
     */
    public function getFrequentUsedShellCommands(): array
    {
        $repository = Request::getString('repository');
        if (empty($repository)) return [];

        $settings = $this->repoManager->getRepositorySettings($repository);
        if (empty($settings) || empty($settings['handler'])) return [];

        return $settings['handler']->getRepositoryOperations($settings);
    }

    // ── User / permission helpers (static — no instance state needed) ─────────

    public static function getAvailablePluginsOfCurrentUser(): array
    {
        $user    = NativeSession::getInstance()->get('builder.user', []);
        $plugins = (array) ($user['plugins'] ?? []);

        if (!empty($user['repositories'])) {
            $plugins[] = 'editor';
        }

        asort($plugins);
        return $plugins;
    }

    public static function getEnabledPluginsOfCurrentUser(): array
    {
        $user = NativeSession::getInstance()->get('builder.user', []);
        return (array) ($user['plugins'] ?? []);
    }

    public static function getCurrentUserPermission(): array
    {
        return ['editor', 'snr'];
    }

    public static function hasPermission(string $key): bool
    {
        $perms = self::getCurrentUserPermission();
        return in_array($key, $perms, true) || in_array('all', $perms, true);
    }

    // ── Proxy for FS plugins ──────────────────────────────────────────────────
    // git.php and svn.php call $this->builder->getUserRepositoryDir().
    // PluginManager acts as the "builder" passed to FS plugin constructors.

    public function getUserRepositoryDir(): string
    {
        return $this->auth->getUserRepositoryDir();
    }
}
