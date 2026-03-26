<?php
namespace CloudPad\Auth;

use CloudPad\FileSystem\FileOperationsInterface;

/**
 * AuthService — Authentication, session persistence, user identity.
 *
 * Phase 10: Loại bỏ Builder → inject FileOperationsInterface trực tiếp.
 * Implements AuthServiceInterface.
 */
class AuthService implements AuthServiceInterface
{
    private FileOperationsInterface $fileOps;
    private string $appDir;

    public function __construct(FileOperationsInterface $fileOps, string $appDir)
    {
        $this->fileOps = $fileOps;
        $this->appDir  = $appDir;
    }

    // ── Session persistence ───────────────────────────────────────────────────

    public function serializeUserSessionData(): void
    {
        if (!isset($_SESSION['builder.username'])) {
            return;
        }

        $data = [];
        foreach ($_SESSION as $key => $value) {
            if ($key !== 'SSH_PASSWORD') {
                $data[$key] = $value;
            }
        }

        $filepath = $this->getUserDataDir() . '/.session';
        $this->fileOps->filePutContents($filepath, json_encode($data, JSON_UNESCAPED_UNICODE), '', $dummy, false);
    }

    public function reloadUserSessionData(): void
    {
        if (!isset($_SESSION['builder.username'])) {
            return;
        }

        $filepath = $this->getUserDataDir() . '/.session';
        if (!file_exists($filepath)) {
            return;
        }

        $data = json_decode($this->fileOps->fileGetContents($filepath), true);
        if (!empty($data) && is_array($data)) {
            foreach ($data as $key => $value) {
                $_SESSION[$key] = $value;
            }
        }
    }

    // ── Auth checks ───────────────────────────────────────────────────────────

    public function isUserLoggedIn(): bool
    {
        return isset($_SESSION['authed']);
    }

    public function auth(): void
    {
        if (!isset($_SESSION['authed'])) {
            header('Location: index.php?action=user/login');
        }
    }

    public function ensureAuth(bool $authed): void
    {
        if ($authed) {
            if (!isset($_SESSION['authed'])) header('Location: index.php');
        } else {
            if (isset($_SESSION['authed']))  header('Location: index.php');
        }
    }

    // ── User identity ─────────────────────────────────────────────────────────

    public function getCurrentUser(): array   { return $_SESSION['builder.user']     ?? []; }
    public function getCurrentUsername(): string { return $_SESSION['builder.username'] ?? ''; }
    public function getUserSessionId(): string   { return md5($_SESSION['builder.username'] ?? ''); }
    public function getPublicUserInfo(): array    { return ['acl' => ['notepad'], 'repositories' => []]; }
    public function getUsers(): array            { return include($this->appDir . '/users.conf.php'); }

    // ── User directories ──────────────────────────────────────────────────────

    public function getUserDataDir(): string         { return $this->ensureDir('tmp/' . ($_SESSION['builder.username'] ?? 'guest')); }
    public function getUserUploadDir(): string       { return $this->ensureDir('tmp/' . ($_SESSION['builder.username'] ?? 'guest') . '/uploads'); }
    public function getUserRepositoryDir(): string   { return $this->ensureDir('tmp/' . ($_SESSION['builder.username'] ?? 'guest') . '/repo'); }
    public function getUserTempDir(): string         { return $this->ensureDir('tmp/' . ($_SESSION['builder.username'] ?? 'guest')); }
    public function getUserRevisionDir(): string     { return $this->ensureDir('tmp/' . ($_SESSION['builder.username'] ?? 'guest') . '/rev'); }
    public function getUserTempRevisionDir(): string { return $this->ensureDir('tmp/' . ($_SESSION['builder.username'] ?? 'guest') . '/temprev'); }

    private function ensureDir(string $relative): string
    {
        $dir = $this->appDir . '/' . $relative;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }
}
