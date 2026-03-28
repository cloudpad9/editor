<?php
namespace CloudPad\SSH;

use CloudPad\Core\Output\OutputManager;
use CloudPad\Core\Process\ProcessManager;
use CloudPad\Core\Request;
use CloudPad\Core\Session\SSHSessionStore;
use CloudPad\Repository\RepositoryManagerInterface;
use phpseclib3\Crypt\RSA;
use phpseclib3\Net\SSH2;

class SSHService
{
    private const PTY_COMMANDS = ['top', 'sudo', 'svn'];

    private OutputManager              $output;
    private ProcessManager             $process;
    private RepositoryManagerInterface $repoManager;
    private SSHSessionStore            $sshSession;

    public function __construct(
        OutputManager              $output,
        ProcessManager             $process,
        RepositoryManagerInterface $repoManager,
        SSHSessionStore            $sshSession
    ) {
        $this->output      = $output;
        $this->process     = $process;
        $this->repoManager = $repoManager;
        $this->sshSession  = $sshSession;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function ensureSafeCommand(string $command): void
    {
        if (preg_match('/(rm|rmdir)\s+/i', $command) && !preg_match('/(svn delete)\s+/i', $command)) {
            $this->output->flushLine('[ERROR] Unsafe commands are not allowed. Please check again.', true);
            exit(-1);
        }
    }

    public function getAugmentedOutput(string $output, string $command): string
    {
        return $output;
    }

    /**
     * Execute builtin/custom SSH commands using RSA key auth.
     * Auth strategy: RSA private key (SSH_RSA_PRIVATE_FILE / SSH_RSA_USERNAME).
     */
    public function sshExec(bool $execBuiltin = true, bool $execCustom = true): void
    {
        foreach (['SSH_HOST', 'SSH_PORT', 'SSH_USERNAME'] as $name) {
            if (empty(Request::getString($name))) {
                $this->output->flushLine("[ERROR] $name is required\n", true);
                return;
            }
        }

        $sshCommand      = Request::getString('SSH_COMMAND');
        $sshCommands     = Request::getString('SSH_COMMANDS');
        $sshCommandNames = Request::getArray('SSH_COMMAND_NAMES');

        if (empty($sshCommandNames) && empty($sshCommand) && empty($sshCommands)) {
            $this->output->flushLine("[ERROR] Please specify a command\n", true);
            return;
        }

        $host     = Request::getString('SSH_HOST');
        $port     = Request::getInt('SSH_PORT', (int) SSH_PORT);
        $username = Request::getString('SSH_USERNAME');
        $password = Request::getString('SSH_PASSWORD');

        // Resolve which commands to run
        $actualCommands = [];
        $shellCommands  = $this->getFrequentUsedShellCommands();

        if ($execBuiltin && !empty($sshCommandNames)) {
            foreach ($sshCommandNames as $name) {
                $cmd = $shellCommands[$name] ?? '';
                if (!empty($cmd)) $actualCommands[] = $cmd;
            }
        } elseif ($execCustom) {
            $actualCommands = !empty($sshCommands)
                ? explode("\n", $sshCommands)
                : [$sshCommand];
        }

        if (empty($password) && $this->sshSession->hasPassword()) {
            $password = $this->sshSession->getPassword();
        }

        $ssh = $this->connectWithRsa();
        $this->runCommandsOnSsh($ssh, $actualCommands);

        $this->sshSession->setHost($host);
        $this->sshSession->setPort($port);
        $this->sshSession->setUsername($username);
        $this->sshSession->setPassword($password);
    }

    /**
     * Execute pre-approved commands using RSA key auth (no request params).
     * Auth strategy: same RSA key as sshExec — but no session write-back.
     */
    public function privateSshExec(array|string $commands): void
    {
        if (is_string($commands)) {
            $commands = explode("\n", $commands);
        }

        $ssh = $this->connectWithRsa();
        $this->runCommandsOnSsh($ssh, $commands);
    }

    /**
     * Execute a single command (or semicolon-joined array) using password auth.
     * Auth strategy: credentials stored in SSHSessionStore (from previous sshExec).
     * Returns combined output string; echoes when $verbose = true.
     */
    public function sshExec2(array|string $commands, bool $verbose = true): string
    {
        static $ssh = null;

        if ($ssh === null) {
            foreach (['SSH_HOST', 'SSH_PORT', 'SSH_USERNAME', 'SSH_PASSWORD'] as $name) {
                if (empty($this->sshSession->getParam($name))) {
                    $this->output->flushLine("[ERROR] $name is required\n", true);
                    return '';
                }
            }

            $ssh = new SSH2($this->sshSession->getHost(), $this->sshSession->getPort());

            if (!$ssh->login($this->sshSession->getUsername(), $this->sshSession->getPassword())) {
                exit('SSH login failed');
            }

            $ssh->setWindowColumns(160);
        }

        $command = is_array($commands) ? implode(';', $commands) : $commands;

        $this->ensureSafeCommand($command);

        $output = $this->execOneCommand($ssh, $command);
        $output = $this->getAugmentedOutput($output, $command);

        if ($verbose) {
            echo $output;
        }

        return $output;
    }

    public function executeLinux(string $cmd, bool $checkCmd = true): mixed
    {
        if ($checkCmd && preg_match('/(delete|del|rm)\s/is', $cmd)) {
            $this->output->flushLine("[ERROR] Command not allowed.\n", true);
            return false;
        }

        $cwd = $this->sshSession->getCwd();
        $res = $this->process->exec($cmd, $cwd);

        if (preg_match('/^cd (.+)/is', $cmd, $match)) {
            $cwd = empty($cwd) || $match[1][0] === DIRECTORY_SEPARATOR
                ? $match[1]
                : realpath($cwd . DIRECTORY_SEPARATOR . $match[1]);
            $this->sshSession->setCwd($cwd);
        }

        echo "[$cwd]#<br/>";

        return $res;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Create an SSH2 connection authenticated with the configured RSA private key.
     * Used by sshExec() and privateSshExec().
     */
    private function connectWithRsa(): SSH2
    {
        $rsaPrivateKey = file_get_contents(SSH_RSA_PRIVATE_FILE);

        if (empty($rsaPrivateKey)) {
            exit('Cannot read private key file');
        }

        $key = RSA::load($rsaPrivateKey, SSH_RSA_PASSPHRASE);
        $ssh = new SSH2('localhost', SSH_PORT);
        $ssh->setWindowColumns(160);

        if (!$ssh->login(SSH_RSA_USERNAME, $key)) {
            echo $ssh->getLastError();
            exit('SSH login failed');
        }

        return $ssh;
    }

    /**
     * Execute a list of shell commands on an established SSH2 connection,
     * streaming output for each. Skips empty lines and comments (#).
     * Used by sshExec() and privateSshExec().
     */
    private function runCommandsOnSsh(SSH2 $ssh, array $commands): void
    {
        foreach ($commands as $command) {
            $command = trim($command);

            if ($command === '' || $command[0] === '#') {
                continue;
            }

            $this->ensureSafeCommand($command);

            $output = $this->execOneCommand($ssh, $command);
            echo $this->getAugmentedOutput($output, $command);
        }
    }

    /**
     * Execute one command on an SSH2 connection, enabling PTY when required.
     * Shared by runCommandsOnSsh() and sshExec2().
     */
    private function execOneCommand(SSH2 $ssh, string $command): string
    {
        [$_cmd] = explode(' ', $command);
        $requirePty = in_array($_cmd, self::PTY_COMMANDS, true);

        if ($requirePty) {
            $ssh->enablePTY();
            $ssh->exec($command);
            $ssh->setTimeout(100);
            return $ssh->read();
        }

        return $ssh->exec($command);
    }

    private function getFrequentUsedShellCommands(): array
    {
        $repository = Request::getString('repository');
        if (empty($repository)) return [];
        $settings = $this->repoManager->getRepositorySettings($repository);
        if (empty($settings) || empty($settings['handler'])) return [];
        return $settings['handler']->getRepositoryOperations($settings);
    }
}
