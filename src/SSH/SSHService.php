<?php
namespace CloudPad\SSH;

class SSHService
{
    private \Builder $builder;

    public function __construct(\Builder $builder)
    {
        $this->builder = $builder;
    }

    public function ensureSafeCommand(string $command): void
    {
        if (preg_match('/(rm|rmdir)\s+/i', $command) && !preg_match('/(svn delete)\s+/i', $command)) {
            $this->builder->flush_line('[ERROR] Unsafe commands are not allowed. Please check again.', true);
            exit(-1);
        }
    }

    public function getAugmentedOutput(string $output, string $command): string
    {
        return $output;
    }

    public function sshExec(bool $execBuiltin = true, bool $execCustom = true): void
    {
        $requires = ['SSH_HOST', 'SSH_PORT', 'SSH_USERNAME'];

        foreach ($requires as $name) {
            if (empty(\CloudPad\Core\Request::getString($name))) {
                $this->builder->flush_line("[ERROR] $name is required\n", true);
                return;
            }
        }

        $sshCommand      = \CloudPad\Core\Request::getString('SSH_COMMAND');
        $sshCommands     = \CloudPad\Core\Request::getString('SSH_COMMANDS');
        $sshCommandNames = \CloudPad\Core\Request::getArray('SSH_COMMAND_NAMES');

        if (empty($sshCommandNames) && empty($sshCommand) && empty($sshCommands)) {
            $this->builder->flush_line("[ERROR] Please specify a command\n", true);
            return;
        }

        $host     = \CloudPad\Core\Request::getString('SSH_HOST');
        $port     = \CloudPad\Core\Request::getInt('SSH_PORT', (int) SSH_PORT);
        $username = \CloudPad\Core\Request::getString('SSH_USERNAME');
        $password = \CloudPad\Core\Request::getString('SSH_PASSWORD');

        $actualCommands = [];
        $shellCommands  = $this->builder->getFrequentUsedShellCommands();

        if ($execBuiltin && !empty($sshCommandNames)) {
            foreach ($sshCommandNames as $name) {
                $cmd = $shellCommands[$name] ?? '';
                if (!empty($cmd)) {
                    $actualCommands[] = $cmd;
                }
            }
        } elseif ($execCustom) {
            if (!empty($sshCommands)) {
                $actualCommands = explode("\n", $sshCommands);
            } else {
                $actualCommands = [$sshCommand];
            }
        }

        if (empty($password) && isset($_SESSION['SSH_PASSWORD'])) {
            $password = $_SESSION['SSH_PASSWORD'];
        }

        $ssh = new \phpseclib3\Net\SSH2('localhost', SSH_PORT);

        $rsaPrivateKey = file_get_contents(SSH_RSA_PRIVATE_FILE);

        if (empty($rsaPrivateKey)) {
            exit('Cannot read private key file');
        }

        $key = \phpseclib3\Crypt\RSA::load($rsaPrivateKey, SSH_RSA_PASSPHRASE);

        if (!$ssh->login(SSH_RSA_USERNAME, $key)) {
            echo $ssh->getLastError();
            exit('SSH login failed');
        }

        $ssh->setWindowColumns(160);

        $ptyRequiredCommands = ['top', 'sudo', 'svn'];

        foreach ($actualCommands as $command) {
            $command = trim($command);

            if (empty($command) || $command[0] === '#') {
                continue;
            }

            $this->ensureSafeCommand($command);

            [$_command] = explode(' ', $command);
            $requirePty = in_array($_command, $ptyRequiredCommands);

            if ($requirePty) {
                $ssh->enablePTY();
                $ssh->exec($command);
                $ssh->setTimeout(100);
                $output = $ssh->read();
            } else {
                $output = $ssh->exec($command);
            }

            echo $this->getAugmentedOutput($output, $command);
        }

        $_SESSION['SSH_HOST']     = $host;
        $_SESSION['SSH_PORT']     = $port;
        $_SESSION['SSH_USERNAME'] = $username;
        $_SESSION['SSH_PASSWORD'] = $password;
    }

    public function privateSshExec(array|string $commands): void
    {
        if (is_string($commands)) {
            $commands = explode("\n", $commands);
        }

        $ssh = new \phpseclib3\Net\SSH2('localhost', SSH_PORT);

        $rsaPrivateKey = file_get_contents(SSH_RSA_PRIVATE_FILE);

        if (empty($rsaPrivateKey)) {
            exit('Cannot read private key file');
        }

        $key = \phpseclib3\Crypt\RSA::load($rsaPrivateKey, SSH_RSA_PASSPHRASE);

        if (!$ssh->login(SSH_RSA_USERNAME, $key)) {
            exit('SSH login failed');
        }

        $ssh->setWindowColumns(160);

        $ptyRequiredCommands = ['top', 'sudo', 'svn'];

        foreach ($commands as $command) {
            $command = trim($command);

            if (empty($command) || $command[0] === '#') {
                continue;
            }

            $this->ensureSafeCommand($command);

            [$_command] = explode(' ', $command);
            $requirePty = in_array($_command, $ptyRequiredCommands);

            if ($requirePty) {
                $ssh->enablePTY();
                $ssh->exec($command);
                $ssh->setTimeout(100);
                $output = $ssh->read();
            } else {
                $output = $ssh->exec($command);
            }

            echo $this->getAugmentedOutput($output, $command);
        }
    }

    public function sshExec2(array|string $commands, bool $verbose = true): string
    {
        static $ssh = null;

        if ($ssh === null) {
            $requires = ['SSH_HOST', 'SSH_PORT', 'SSH_USERNAME', 'SSH_PASSWORD'];

            foreach ($requires as $name) {
                if (empty($_SESSION[$name])) {
                    $this->builder->flush_line("[ERROR] $name is required\n", true);
                    return '';
                }
            }

            $ssh = new \phpseclib3\Net\SSH2($_SESSION['SSH_HOST'], $_SESSION['SSH_PORT']);

            if (!$ssh->login($_SESSION['SSH_USERNAME'], $_SESSION['SSH_PASSWORD'])) {
                exit('SSH login failed');
            }

            $ssh->setWindowColumns(160);
        }

        $command = is_array($commands) ? implode(';', $commands) : $commands;

        $this->ensureSafeCommand($command);

        [$_command]   = explode(' ', $command);
        $ptyRequired  = in_array($_command, ['top', 'sudo', 'svn']);

        if ($ptyRequired) {
            $ssh->enablePTY();
            $ssh->exec($command);
            $ssh->setTimeout(100);
            $output = $ssh->read();
        } else {
            $output = $ssh->exec($command);
        }

        $output = $this->getAugmentedOutput($output, $command);

        if ($verbose) {
            echo $output;
        }

        return $output;
    }

    public function executeLinux(string $cmd, bool $checkCmd = true): mixed
    {
        if ($checkCmd && preg_match('/(delete|del|rm)\s/is', $cmd)) {
            $this->builder->flush_line("[ERROR] Command not allowed.\n", true);
            return false;
        }

        $cwd = $_SESSION['cwd'] ?? '';
        $res = $this->builder->exec($cmd, $cwd);

        if (preg_match('/^cd (.+)/is', $cmd, $match)) {
            if (!empty($cwd)) {
                if ($match[1][0] !== DIRECTORY_SEPARATOR) {
                    $cwd = realpath($cwd . DIRECTORY_SEPARATOR . $match[1]);
                } else {
                    $cwd = $match[1];
                }
            } else {
                $cwd = $match[1];
            }
            $_SESSION['cwd'] = $cwd;
        }

        echo "[$cwd]#<br/>";

        return $res;
    }
}
