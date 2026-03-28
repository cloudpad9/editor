<?php
namespace CloudPad\Tests;

use PHPUnit\Framework\TestCase;
use CloudPad\Editor\EditorService;

/**
 * R6: Unit tests for EditorService pure utility methods.
 * These methods have no I/O and are fully deterministic — no mocks needed
 * for the tested methods themselves.
 */
class EditorServiceTest extends TestCase
{
    private EditorService $service;

    protected function setUp(): void
    {
        $this->service = new EditorService(
            $this->createMock(\CloudPad\Repository\RepositoryManagerInterface::class),
            $this->createMock(\CloudPad\FileSystem\FileOperationsInterface::class),
            $this->createMock(\CloudPad\Search\FileSearchServiceInterface::class),
            $this->createMock(\CloudPad\Auth\AuthServiceInterface::class),
            $this->createMock(\CloudPad\Editor\ColorManager::class),
            $this->createMock(\CloudPad\Editor\RevisionManager::class),
            $this->createMock(\CloudPad\Core\Output\OutputManager::class),
            $this->createMock(\CloudPad\Core\Session\EditorSessionStore::class)
        );
    }

    // ── convertTabsToWhitespaces ─────────────────────────────────────────────

    public function test_convertTabsToWhitespaces_single(): void
    {
        $this->assertEquals('    x', $this->service->convertTabsToWhitespaces("\tx"));
    }

    public function test_convertTabsToWhitespaces_multiple(): void
    {
        $this->assertEquals('        x', $this->service->convertTabsToWhitespaces("\t\tx"));
    }

    public function test_convertTabsToWhitespaces_no_tabs(): void
    {
        $this->assertEquals('hello', $this->service->convertTabsToWhitespaces('hello'));
    }

    // ── trimTrailingWhitespaces ──────────────────────────────────────────────

    public function test_trimTrailingWhitespaces(): void
    {
        $input    = "hello   \nworld  \n  foo  ";
        $expected = "hello\nworld\n  foo";
        $this->assertEquals($expected, $this->service->trimTrailingWhitespaces($input));
    }

    public function test_trimTrailingWhitespaces_preserves_indent(): void
    {
        $this->assertEquals("    code", $this->service->trimTrailingWhitespaces("    code   "));
    }

    // ── trimTrailingCommas ───────────────────────────────────────────────────

    public function test_trimTrailingCommas(): void
    {
        $input    = "[\n    'a',\n    'b',\n]";
        $expected = "[\n    'a',\n    'b'\n]";
        $this->assertEquals($expected, $this->service->trimTrailingCommas($input));
    }

    public function test_trimTrailingCommas_no_trailing(): void
    {
        $input = "[\n    'a',\n    'b'\n]";
        $this->assertEquals($input, $this->service->trimTrailingCommas($input));
    }

    // ── isSameContent ────────────────────────────────────────────────────────

    public function test_isSameContent_identical(): void
    {
        $this->assertTrue($this->service->isSameContent('hello', 'hello'));
    }

    public function test_isSameContent_whitespace_normalised(): void
    {
        // Leading/trailing whitespace and newlines are stripped before comparison
        $this->assertTrue($this->service->isSameContent("  hello\n", "hello"));
    }

    public function test_isSameContent_different(): void
    {
        $this->assertFalse($this->service->isSameContent('hello', 'world'));
    }

    public function test_isSameContent_no_debug_output(): void
    {
        // R4 fix: isSameContent must NOT emit any output when content differs
        ob_start();
        $this->service->isSameContent('aaa', 'bbb');
        $output = ob_get_clean();
        $this->assertEmpty($output, 'isSameContent should not echo debug output');
    }

    // ── buildDirectoryStructure ──────────────────────────────────────────────

    public function test_buildDirectoryStructure_flat_file(): void
    {
        $result = $this->service->buildDirectoryStructure(['/README.md']);
        $this->assertCount(1, $result);
        $this->assertEquals('README.md', $result[0]['name']);
        $this->assertFalse($result[0]['isDir']);
    }

    public function test_buildDirectoryStructure_nested(): void
    {
        $paths  = ['/src/App.php', '/src/Core/Router.php', '/README.md'];
        $result = $this->service->buildDirectoryStructure($paths);

        // Should produce: [src/, README.md]
        $this->assertCount(2, $result);

        $src = $result[0];
        $this->assertEquals('src', $src['name']);
        $this->assertTrue($src['isDir']);
        // src/ contains App.php + Core/
        $this->assertCount(2, $src['children']);
    }

    // ── onBeforeSavingFile ───────────────────────────────────────────────────

    public function test_onBeforeSavingFile_php_converts_tabs_and_trims(): void
    {
        $content = "\tclass Foo {\n\t\treturn true;  \n}  \n";
        $this->service->onBeforeSavingFile($content, 'php');

        $this->assertStringNotContainsString("\t", $content, 'tabs should be converted to spaces');
        $this->assertStringNotContainsString("  \n", $content, 'trailing whitespace should be trimmed');
        $this->assertStringEndsWith("\n", $content, 'file should end with newline');
    }

    public function test_onBeforeSavingFile_nonweb_preserves_tabs(): void
    {
        $content = "\tdata  \n";
        $this->service->onBeforeSavingFile($content, 'txt');

        $this->assertStringContainsString("\t", $content, 'tabs should be preserved for non-web files');
        $this->assertStringNotContainsString("  \n", $content, 'trailing whitespace still trimmed');
    }

    public function test_onBeforeSavingFile_adds_trailing_newline(): void
    {
        $content = 'no newline at end';
        $this->service->onBeforeSavingFile($content, 'php');
        $this->assertStringEndsWith("\n", $content);
    }
}
