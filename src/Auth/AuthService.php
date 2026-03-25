<?php
namespace CloudPad\Auth;

/**
 * Handles authentication, session persistence, and user identity.
 *
 * Injected into Builder; Builder methods delegate here.
 */
class AuthService
{
    private \Builder $builder;
    private string $appDir;

    public function __construct(\Builder $builder, string $appDir)
    {
        $this->builder = $builder;
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
        $this->builder->file_put_contents($filepath, json_encode($data, JSON_UNESCAPED_UNICODE), '', $dummy, false);
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

        $data = json_decode($this->builder->file_get_contents($filepath), true);

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
            if (!isset($_SESSION['authed'])) {
                header('Location: index.php');
            }
        } else {
            if (isset($_SESSION['authed'])) {
                header('Location: index.php');
            }
        }
    }

    // ── User identity ─────────────────────────────────────────────────────────

    public function getCurrentUser(): array
    {
        return $_SESSION['builder.user'];
    }

    public function getCurrentUsername(): string
    {
        return $_SESSION['builder.username'];
    }

    public function getUserSessionId(): string
    {
        return md5($_SESSION['builder.username']);
    }

    public function getPublicUserInfo(): array
    {
        return [
            'acl'          => ['notepad'],
            'repositories' => [],
        ];
    }

    public function getUsers(): array
    {
        return include($this->appDir . '/users.conf.php');
    }

    // ── User directories ──────────────────────────────────────────────────────

    public function getUserDataDir(): string
    {
        $dir = $this->appDir . '/tmp/' . $_SESSION['builder.username'];

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public function getUserUploadDir(): string
    {
        $dir = $this->appDir . '/tmp/' . $_SESSION['builder.username'] . '/uploads';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public function getUserRepositoryDir(): string
    {
        $dir = $this->appDir . '/tmp/' . $_SESSION['builder.username'] . '/repo';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public function getUserTempDir(): string
    {
        $dir = $this->appDir . '/tmp/' . $_SESSION['builder.username'];

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public function getUserRevisionDir(): string
    {
        $dir = $this->appDir . '/tmp/' . $_SESSION['builder.username'] . '/rev';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public function getUserTempRevisionDir(): string
    {
        $dir = $this->appDir . '/tmp/' . $_SESSION['builder.username'] . '/temprev';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }
}
