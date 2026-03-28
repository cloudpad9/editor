<?php
namespace CloudPad\Core\Session;

/**
 * SNRSessionStore — Typed accessor cho Search & Replace session data.
 *
 * Phase 11: Thay thế $_SESSION['snr-backup'] trong SNRService.
 * R5: Added saveSearchState() + getters for search UI state.
 */
class SNRSessionStore
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    // ── SNR backup (before replace) ───────────────────────────────────────────

    public function getBackup(): array
    {
        return (array) $this->session->get('snr-backup', []);
    }

    public function setBackupEntry(string $filepath, string $originalContent): void
    {
        $backup            = $this->getBackup();
        $backup[$filepath] = $originalContent;
        $this->session->set('snr-backup', $backup);
    }

    public function clearBackup(): void
    {
        $this->session->remove('snr-backup');
    }

    // ── Inline file (open-inline-file plugin) ─────────────────────────────────

    public function getInlineFile(): string
    {
        return (string) $this->session->get('inline-file', '');
    }

    public function setInlineFile(string $filepath): void
    {
        $this->session->set('inline-file', $filepath);
    }

    // ── Search UI state (persisted so the SNR tab restores correctly) ─────────

    public function saveSearchState(
        string $repository,
        string $search,
        string $fileMask,
        int    $maxFilesReturned,
        string $replace,
        string $batchSnr,
        bool   $batchMode
    ): void {
        $this->session->set('snr-repository',     $repository);
        $this->session->set('search',             $search);
        $this->session->set('file-mask',          $fileMask);
        $this->session->set('max-files-returned', $maxFilesReturned);
        $this->session->set('replace',            $replace);
        $this->session->set('batch-snr',          $batchSnr);
        $this->session->set('snr-batch-mode',     $batchMode);
    }

    public function getRepository(): string { return (string) $this->session->get('snr-repository',     ''); }
    public function getSearch(): string     { return (string) $this->session->get('search',             ''); }
    public function getFileMask(): string   { return (string) $this->session->get('file-mask',          ''); }
    public function getMaxFiles(): int      { return (int)    $this->session->get('max-files-returned', 20); }
    public function getReplace(): string    { return (string) $this->session->get('replace',            ''); }
    public function getBatchSnr(): string   { return (string) $this->session->get('batch-snr',          ''); }
    public function isBatchMode(): bool     { return (bool)   $this->session->get('snr-batch-mode',     false); }
}
