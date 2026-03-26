<?php
namespace CloudPad\Core\Session;

/**
 * AuthSessionStore — Typed accessor cho auth/user session data.
 *
 * Phase 11: Thay thế $_SESSION['builder.username'], $_SESSION['builder.user'],
 * $_SESSION['authed'] rải rác trong AuthService, RepositoryManager, ColorManager, Builder.
 *
 * Keys managed:
 *   builder.username  → string
 *   builder.user      → array  (user config: plugins, repositories, ...)
 *   authed            → bool (isset = authed)
 */
class AuthSessionStore
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    // ── Auth state ────────────────────────────────────────────────────────────

    public function isAuthed(): bool
    {
        return $this->session->has('authed');
    }

    public function setAuthed(bool $authed): void
    {
        if ($authed) {
            $this->session->set('authed', true);
        } else {
            $this->session->remove('authed');
        }
    }

    // ── Username ──────────────────────────────────────────────────────────────

    public function getUsername(): string
    {
        return (string) $this->session->get('builder.username', '');
    }

    public function setUsername(string $username): void
    {
        $this->session->set('builder.username', $username);
    }

    public function hasUsername(): bool
    {
        return $this->session->has('builder.username');
    }

    // ── User data (plugins, repositories, settings) ───────────────────────────

    public function getUser(): array
    {
        return (array) $this->session->get('builder.user', []);
    }

    public function setUser(array $user): void
    {
        $this->session->set('builder.user', $user);
    }

    public function getRepositories(): array
    {
        $user = $this->getUser();
        return (array) ($user['repositories'] ?? []);
    }

    public function getPlugins(): array
    {
        $user = $this->getUser();
        return (array) ($user['plugins'] ?? []);
    }

    // ── Full session dump (for serialize/reload) ──────────────────────────────

    public function all(): array
    {
        return $this->session->all();
    }

    public function setAll(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->session->set($key, $value);
        }
    }
}
