<?php
namespace CloudPad\Core\Session;

/**
 * SNRSessionStore — Typed accessor cho Search & Replace session data.
 *
 * Phase 11: Thay thế $_SESSION['snr-backup'] trong SNRService.
 * Cũng quản lý $_SESSION['inline-file'] dùng bởi open_inline_file plugin.
 */
class SNRSessionStore
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    // ── SNR backup (trước khi replace) ────────────────────────────────────────

    public function getBackup(): array
    {
        return (array) $this->session->get('snr-backup', []);
    }

    public function setBackupEntry(string $filepath, string $originalContent): void
    {
        $backup             = $this->getBackup();
        $backup[$filepath]  = $originalContent;
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
}
