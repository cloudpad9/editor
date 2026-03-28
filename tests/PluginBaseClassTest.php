<?php
namespace CloudPad\Tests;

use PHPUnit\Framework\TestCase;
use CloudPad\Plugin\BaseFilesystemPlugin;
use CloudPad\Plugin\BaseTabPlugin;

/**
 * R6: Verify plugin base classes behave correctly after R1 migration.
 */
class PluginBaseClassTest extends TestCase
{
    // ── BaseFilesystemPlugin ─────────────────────────────────────────────────

    public function test_baseFilesystemPlugin_isAccessible_default_true(): void
    {
        $plugin = new class(null) extends BaseFilesystemPlugin {};
        $this->assertTrue($plugin->isAccessible([]));
    }

    public function test_baseFilesystemPlugin_getLocalizedPath_identity(): void
    {
        $plugin = new class(null) extends BaseFilesystemPlugin {};
        $this->assertEquals('/var/www/file.php', $plugin->getLocalizedPath([], '/var/www/file.php'));
    }

    public function test_baseFilesystemPlugin_init_noop(): void
    {
        $plugin   = new class(null) extends BaseFilesystemPlugin {};
        $settings = ['key' => 'value'];
        $plugin->init($settings);
        // init() should not modify settings by default
        $this->assertEquals(['key' => 'value'], $settings);
    }

    public function test_baseFilesystemPlugin_getRepositoryOperations_empty_dir(): void
    {
        $plugin = new class(null) extends BaseFilesystemPlugin {};
        $result = $plugin->getRepositoryOperations(['dirs' => [], 'type' => 'local']);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ── BaseTabPlugin ────────────────────────────────────────────────────────

    public function test_baseTabPlugin_getPluginInfo_default_null(): void
    {
        $plugin = new class extends BaseTabPlugin {
            public function getTabTitle(): string { return 'TEST'; }
            public function render($builder): void {}
        };
        $this->assertNull($plugin->getPluginInfo());
    }

    public function test_baseTabPlugin_getTabTitle_required(): void
    {
        $plugin = new class extends BaseTabPlugin {
            public function getTabTitle(): string { return 'MYTAB'; }
            public function render($builder): void {}
        };
        $this->assertEquals('MYTAB', $plugin->getTabTitle());
    }

    // ── Concrete FS plugins extend correct base ──────────────────────────────

    public function test_plugin_fs_local_extends_base(): void
    {
        require_once __DIR__ . '/../plugins/fs/local/local.php';
        $this->assertTrue(
            is_a('plugin_fs_local', BaseFilesystemPlugin::class, true),
            'plugin_fs_local must extend BaseFilesystemPlugin'
        );
    }

    public function test_plugin_fs_git_extends_base(): void
    {
        require_once __DIR__ . '/../plugins/fs/git/git.php';
        $this->assertTrue(
            is_a('plugin_fs_git', BaseFilesystemPlugin::class, true),
            'plugin_fs_git must extend BaseFilesystemPlugin'
        );
    }

    public function test_plugin_fs_sftp_extends_base(): void
    {
        require_once __DIR__ . '/../plugins/fs/sftp/sftp.php';
        $this->assertTrue(
            is_a('plugin_fs_sftp', BaseFilesystemPlugin::class, true),
            'plugin_fs_sftp must extend BaseFilesystemPlugin'
        );
    }

    public function test_plugin_fs_svn_extends_base(): void
    {
        require_once __DIR__ . '/../plugins/fs/svn/svn.php';
        $this->assertTrue(
            is_a('plugin_fs_svn', BaseFilesystemPlugin::class, true),
            'plugin_fs_svn must extend BaseFilesystemPlugin'
        );
    }

    // ── Concrete tab plugins extend correct base ─────────────────────────────

    public function test_plugin_tab_editor_extends_base(): void
    {
        require_once __DIR__ . '/../plugins/tabs/editor/index.php';
        $this->assertTrue(
            is_a('plugin_tab_editor', BaseTabPlugin::class, true),
            'plugin_tab_editor must extend BaseTabPlugin'
        );
    }

    public function test_plugin_tab_snr_extends_base(): void
    {
        require_once __DIR__ . '/../plugins/tabs/snr/index.php';
        $this->assertTrue(
            is_a('plugin_tab_snr', BaseTabPlugin::class, true),
            'plugin_tab_snr must extend BaseTabPlugin'
        );
    }

    public function test_plugin_tab_diff_extends_base(): void
    {
        require_once __DIR__ . '/../plugins/tabs/diff/index.php';
        $this->assertTrue(
            is_a('plugin_tab_diff', BaseTabPlugin::class, true),
            'plugin_tab_diff must extend BaseTabPlugin'
        );
    }
}
