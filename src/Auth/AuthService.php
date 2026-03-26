<?php
namespace CloudPad\Auth;

use CloudPad\FileSystem\FileOperationsInterface;
use CloudPad\Core\Session\AuthSessionStore;

/**
 * AuthService — Authentication, session persistence, user identity.
 *
 * Phase 10: Loại bỏ Builder → inject FileOperationsInterface trực tiếp.
 * Phase 11: Loại bỏ $_SESSION trực tiếp → inject AuthSessionStore.
 * Implements AuthServiceInterface.
 */
class AuthService implements AuthServiceInterface
{
    private FileOperationsInterface $fileOps;
    private AuthSessionStore $authSession;
    private string $appDir;

    public function __construct(
        FileOperationsInterface $fileOps,
        AuthSessionStore $authSession,
        string $appDir
    ) {
        $this->fileOps      = $fileOps;
        $this->authSession  = $authSession;
        $this->appDir       = $appDir;
    }

    // ── Session persistence ───────────────────────────────────────────────────

    public function serializeUserSessionData(): void
    {
        if (!$this->authSession->hasUsername()) {
            return;
        }

        $data = array_filter(
            $this->authSession->all(),
            fn($key) => $key !== 'SSH_PASSWORD',
            ARRAY_FILTER_USE_KEY
        );

        $filepath = $this->getUserDataDir() . '/.session';
        $this->fileOps->filePutContents($filepath, json_encode($data, JSON_UNESCAPED_UNICODE), '', $dummy, false);
    }

    public function reloadUserSessionData(): void
    {
        if (!$this->authSession->hasUsername()) {
            return;
        }

        $filepath = $this->getUserDataDir() . '/.session';
        if (!file_exists($filepath)) {
            return;
        }

        $data = json_decode($this->fileOps->fileGetContents($filepath), true);
        if (!empty($data) && is_array($data)) {
            $this->authSession->setAll($data);
        }
    }

    // ── Auth checks ───────────────────────────────────────────────────────────

    public function isUserLoggedIn(): bool
    {
        return $this->authSession->isAuthed();
    }

    public function auth(): void
    {
        if (!$this->authSession->isAuthed()) {
            header('Location: index.php?action=user/login');
        }
    }

    public function ensureAuth(bool $authed): void
    {
        if ($authed) {
            if (!$this->authSession->isAuthed()) header('Location: index.php');
        } else {
            if ($this->authSession->isAuthed())  header('Location: index.php');
        }
    }

    // ── User identity ─────────────────────────────────────────────────────────

    public function getCurrentUser(): array      { return $this->authSession->getUser(); }
    public function getCurrentUsername(): string { return $this->authSession->getUsername(); }
    public function getUserSessionId(): string   { return md5($this->authSession->getUsername()); }
    public function getPublicUserInfo(): array   { return ['acl' => ['notepad'], 'repositories' => []]; }
    public function getUsers(): array           { return include($this->appDir . '/users.conf.php'); }

    // ── User directories ──────────────────────────────────────────────────────

    public function getUserDataDir(): string         { return $this->ensureDir('tmp/' . ($this->authSession->getUsername() ?: 'guest')); }
    public function getUserUploadDir(): string       { return $this->ensureDir('tmp/' . ($this->authSession->getUsername() ?: 'guest') . '/uploads'); }
    public function getUserRepositoryDir(): string   { return $this->ensureDir('tmp/' . ($this->authSession->getUsername() ?: 'guest') . '/repo'); }
    public function getUserTempDir(): string         { return $this->ensureDir('tmp/' . ($this->authSession->getUsername() ?: 'guest')); }
    public function getUserRevisionDir(): string     { return $this->ensureDir('tmp/' . ($this->authSession->getUsername() ?: 'guest') . '/rev'); }
    public function getUserTempRevisionDir(): string { return $this->ensureDir('tmp/' . ($this->authSession->getUsername() ?: 'guest') . '/temprev'); }

    private function ensureDir(string $relative): string
    {
        $dir = $this->appDir . '/' . $relative;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
