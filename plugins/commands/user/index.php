<?php
use CloudPad\Core\Session\NativeSession;

class plugin_command_user
{
    function login($builder)
    {
        $this->ensure_auth(false);

        if (empty($_POST)) {
            include __DIR__ . '/login.tpl';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $this->_login($username, $password, $builder);
        }
    }

    function logout($builder)
    {
        $this->ensure_auth(true);

        $session = NativeSession::getInstance();
        $session->set('authed', false);

        $builder->serializeUserSessionData();

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }

        session_destroy();

        header('Location: index.php?action=user/login');
        exit;
    }

    private function ensure_auth(bool $authed): void
    {
        $session  = NativeSession::getInstance();
        $isAuthed = $session->has('authed') && $session->get('authed') !== false;

        if ($authed && !$isAuthed) {
            header('Location: index.php');
            exit;
        }

        if (!$authed && $isAuthed) {
            header('Location: index.php');
            exit;
        }
    }

    private function _login(string $username, string $password, $builder): void
    {
        $users = $builder->getAuth()->getUsers();
        $error = '';

        if (!isset($users[$username])) {
            $error = 'Incorrect username or password';
        } else {
            $user = $users[$username];
            if (!password_verify($password, $user['password_hash'])) {
                $error = 'Incorrect username or password';
            }
        }

        if (!empty($error)) {
            include __DIR__ . '/login.tpl';
            return;
        }

        $session = NativeSession::getInstance();

        // Set username first so reloadUserSessionData() knows which user
        $session->set('builder.username', $username);

        // Reload persisted session data from disk
        $builder->reloadUserSessionData();

        // Set user config + auth flag
        $session->set('builder.user', $user);
        $session->set('authed', true);

        header('Location: index.php');
        exit;
    }
}
