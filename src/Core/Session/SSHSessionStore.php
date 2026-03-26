<?php
namespace CloudPad\Core\Session;

/**
 * SSHSessionStore — Typed accessor cho SSH connection state.
 *
 * Phase 11: Thay thế $_SESSION['SSH_HOST/PORT/USERNAME/PASSWORD/cwd']
 * trong SSHService.
 */
class SSHSessionStore
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function getHost(): string    { return (string) $this->session->get('SSH_HOST', ''); }
    public function getPort(): int       { return (int)    $this->session->get('SSH_PORT', 22); }
    public function getUsername(): string { return (string) $this->session->get('SSH_USERNAME', ''); }
    public function getPassword(): string { return (string) $this->session->get('SSH_PASSWORD', ''); }
    public function getCwd(): string     { return (string) $this->session->get('cwd', ''); }

    public function setHost(string $v): void     { $this->session->set('SSH_HOST', $v); }
    public function setPort(int $v): void        { $this->session->set('SSH_PORT', $v); }
    public function setUsername(string $v): void { $this->session->set('SSH_USERNAME', $v); }
    public function setPassword(string $v): void { $this->session->set('SSH_PASSWORD', $v); }
    public function setCwd(string $v): void      { $this->session->set('cwd', $v); }

    public function hasPassword(): bool  { return $this->session->has('SSH_PASSWORD'); }

    public function hasParam(string $name): bool { return $this->session->has($name); }
    public function getParam(string $name): string { return (string) $this->session->get($name, ''); }
}
