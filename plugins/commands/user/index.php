<?php
class plugin_command_user {
    function login($builder) {
        $this->ensure_auth(false);

        $error = '';

        $username = '';
        $password = '';

        if (empty($_POST)) {
            include __DIR__.'/login.tpl';
        } else {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);

            $this->_login($username, $password, $builder);
        }
    }

    function logout($builder) {
        $this->ensure_auth(true);

        $_SESSION['authed'] = false;

        // Write user data to disk
        $builder->serializeUserSessionData();

        if (isset($_COOKIE[session_name()])) {
           setcookie(session_name(), '', time()-42000, '/');
        }

        // Finally, destroy the session.
        session_destroy();

        header('Location: index.php?action=user/login');
    }

    private function ensure_auth($authed) {
        if ($authed) {
            if (!isset($_SESSION['authed'])) {
                header('Location: index.php');
                exit;
            }
        } else {
            if (isset($_SESSION['authed'])) {
                header('Location: index.php');
                exit;
            }
        }
    }

    private function _login($username, $password, $builder) {
        $users = $builder->getUsers(); // Get users from the configuration file

        $error = '';

        if (!isset($users[$username])) {
            $error = 'Incorrect username or password';
        } else {
            $user = $users[$username];

            if (!password_verify($password, $user['password_hash'])) {
                $error = 'Incorrect username or password';
            }
            // Tùy thuộc vào cấu hình, bạn có thể cần kiểm tra trạng thái kích hoạt tài khoản
        }

        if (!empty($error)) {
            include __DIR__.'/login.tpl';
            return;
        }

        // Old public session id
        $public_uid = $_SESSION['builder.username'] ?? null;

        // First
        $_SESSION['builder.username'] = $username;

        // Then
        $builder->reloadUserSessionData();

        // Later
        $_SESSION['builder.user'] = $user;
        $_SESSION['authed'] = true;

        header('Location: index.php');
        exit;
    }
}
