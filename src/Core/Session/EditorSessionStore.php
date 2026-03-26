<?php
namespace CloudPad\Core\Session;

/**
 * EditorSessionStore — Typed accessor cho editor session data.
 *
 * Phase 11: Thay thế $_SESSION['filepaths'], ['openfilepaths'],
 * ['recentfilepaths'], ['quick-access'], ['files.upload.directory']
 * rải rác trong EditorService, RepositoryManager.
 *
 * Key structure:
 *   filepaths[$repo][$filename]       → string  absolute path (search cache)
 *   openfilepaths[$repo][$filename]   → string  absolute path (open tab)
 *   recentfilepaths[$repo][$filename] → string  absolute path (recent history)
 *   quick-access                      → array   [{name, path, repository}]
 *   files.upload.directory            → string  last upload dir
 */
class EditorSessionStore
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    // ── filepaths (search/lookup cache) ───────────────────────────────────────

    public function getFilePath(string $repository, string $filename): string
    {
        $all = (array) $this->session->get('filepaths', []);
        return (string) ($all[$repository][$filename] ?? '');
    }

    public function setFilePath(string $repository, string $filename, string $filepath): void
    {
        $all = (array) $this->session->get('filepaths', []);
        $all[$repository][$filename] = $filepath;
        $this->session->set('filepaths', $all);
    }

    public function getAllFilePaths(): array
    {
        return (array) $this->session->get('filepaths', []);
    }

    // ── openfilepaths (open editor tabs) ─────────────────────────────────────

    public function getOpenFilePath(string $repository, string $filename): string
    {
        $all = (array) $this->session->get('openfilepaths', []);
        return (string) ($all[$repository][$filename] ?? '');
    }

    public function setOpenFilePath(string $repository, string $filename, string $filepath): void
    {
        $all = (array) $this->session->get('openfilepaths', []);
        $all[$repository][$filename] = $filepath;
        $this->session->set('openfilepaths', $all);
    }

    public function removeOpenFilePath(string $repository, string $filename): void
    {
        $all = (array) $this->session->get('openfilepaths', []);
        unset($all[$repository][$filename]);
        $this->session->set('openfilepaths', $all);
    }

    public function getAllOpenFilePaths(): array
    {
        return (array) $this->session->get('openfilepaths', []);
    }

    public function setAllOpenFilePaths(array $paths): void
    {
        $this->session->set('openfilepaths', $paths);
    }

    // ── recentfilepaths ───────────────────────────────────────────────────────

    public function getRecentFilePaths(string $repository): array
    {
        $all = (array) $this->session->get('recentfilepaths', []);
        return (array) ($all[$repository] ?? []);
    }

    public function setRecentFilePath(string $repository, string $filename, string $filepath): void
    {
        $all = (array) $this->session->get('recentfilepaths', []);
        $all[$repository][$filename] = $filepath;
        $this->session->set('recentfilepaths', $all);
    }

    // ── quick-access ──────────────────────────────────────────────────────────

    public function getQuickAccess(): array
    {
        return (array) $this->session->get('quick-access', []);
    }

    public function setQuickAccess(array $items): void
    {
        $this->session->set('quick-access', $items);
    }

    // ── upload directory ──────────────────────────────────────────────────────

    public function getUploadDirectory(): string
    {
        return (string) $this->session->get('files.upload.directory', '');
    }

    public function setUploadDirectory(string $dir): void
    {
        $this->session->set('files.upload.directory', $dir);
    }
}
