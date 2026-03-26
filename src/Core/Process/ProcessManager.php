<?php
namespace CloudPad\Core\Process;

use CloudPad\Core\Output\OutputManager;

/**
 * ProcessManager — Quản lý shell process execution, PID tracking, stop signal.
 *
 * Phase 9.3: Tách exec(), save_pid(), is_stop_pending() ra khỏi Builder.
 * Builder giữ thin wrappers delegate sang instance này.
 *
 * Dependencies:
 *   - OutputManager: để flush output dòng-by-dòng từ process
 *   - userDataDir: thư mục chứa .pid / .stop files
 */
class ProcessManager
{
    private OutputManager $output;
    private string $userDataDir;

    public function __construct(OutputManager $output, string $userDataDir)
    {
        $this->output      = $output;
        $this->userDataDir = $userDataDir;
    }

    /**
     * Thực thi shell command, stream output ra client theo từng dòng.
     *
     * @param string  $cmd           Command cần chạy
     * @param string|null $cwd       Working directory (null = inherit)
     * @param bool    $returnOutput  Nếu true, gom output vào $output thay vì flush
     * @param string  $output        Output buffer (out param)
     * @return int                   Exit code của process
     */
    public function exec(
        string  $cmd,
        ?string $cwd          = null,
        bool    $returnOutput = false,
        string  &$output      = ''
    ): int {
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        flush();
        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, null);

        if (!is_resource($process)) {
            return -1;
        }

        $status = proc_get_status($process);
        $this->savePid((int)$status['pid']);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $time = time();

        while (!feof($pipes[1]) || !feof($pipes[2])) {
            if (!feof($pipes[1])) {
                $s = fgets($pipes[1], 4096);
                if ($s !== false) {
                    if ($returnOutput) {
                        $output .= $s;
                    } else {
                        $this->output->flushLine($s);
                    }
                }
            }

            if (!feof($pipes[2])) {
                $s = fgets($pipes[2], 4096);
                if ($s !== false) {
                    if ($returnOutput) {
                        $output .= $s;
                    } else {
                        $this->output->flush('<span class="error">' . $s . '</span>');
                    }
                }
            }

            // Check stop signal mỗi giây
            if (time() - $time > 1) {
                if ($this->isStopPending()) {
                    $this->output->flush('<span class="error">[NOTICE] Stop as requested</span>');
                    break;
                }
                $time = time();
            }
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    /**
     * Lưu PID của child process — dùng để kill nếu user nhấn Stop.
     */
    public function savePid(int $pid): void
    {
        @file_put_contents($this->userDataDir . '/.pid', (string)$pid);
    }

    /**
     * Kiểm tra user có yêu cầu stop process không.
     * Frontend tạo file .stop khi user nhấn Stop.
     * Method này kiểm tra và xoá file đó.
     */
    public function isStopPending(): bool
    {
        $file = $this->userDataDir . '/.stop';

        if (file_exists($file)) {
            @unlink($file);
            return true;
        }

        return false;
    }

    /**
     * Cập nhật userDataDir — cần gọi sau khi user đăng nhập (session available).
     */
    public function setUserDataDir(string $dir): void
    {
        $this->userDataDir = $dir;
    }
}
