<?php
namespace CloudPad\Core\Output;

/**
 * OutputManager — Quản lý tất cả output flushing và verbose logging.
 *
 * Phase 9.2: Tách các flush_* methods ra khỏi Builder.
 * Builder giữ thin wrappers delegate sang instance này.
 *
 * Inject vào services cần output (RepositoryManager, SSHService, FileSearchService)
 * thay vì gọi $builder->flush_line() — xem Phase 10.
 */
class OutputManager
{
    /**
     * Flush raw string ra output stream ngay lập tức.
     */
    public function flush(string $s): void
    {
        print $s;
        flush();
        ob_flush();
    }

    /**
     * Flush một dòng text, tự động wrap lỗi trong <span class="error">.
     * Chỉ output khi request có verbose=true (default true).
     *
     * @param bool        $flushJsMessage  Có flush thêm JS message popup không
     * @param string|null $jsMessage       Nội dung JS message (auto-extract từ $s nếu null)
     * @param bool        $modal           true = modal dialog, false = notification toast
     */
    public function flushLine(
        string  $s,
        bool    $flushJsMessage = false,
        ?string $jsMessage      = null,
        bool    $modal          = true
    ): void {
        $verbose = \CloudPad\Core\Request::getBool('verbose', true);
        $quiet   = \CloudPad\Core\Request::getBool('quiet',   false);

        if (!$verbose) {
            return;
        }

        if (preg_match('/\[(error|warning|notice)/is', $s)
            || preg_match('/(unknown|error|warning|notice)/is', $s)) {
            $this->flush('<span class="error">' . $s . '</span>');
        } else {
            $this->flush($s);
        }

        if ($flushJsMessage && !$quiet) {
            $type = 'info';

            if ($jsMessage === null) {
                if (preg_match('/^\s*\[(.*)\]\s*(.*)/is', $s, $match)) {
                    $type      = $match[1];
                    $jsMessage = $match[2];
                }
            }

            if ($modal) {
                $this->flushJsMessage(trim((string)$jsMessage), $type);
            } else {
                $this->flushJsNotification(trim((string)$jsMessage));
            }
        }
    }

    /**
     * Flush một block text nhiều dòng (split theo newline).
     */
    public function flushBlock(string $block): void
    {
        foreach (explode(PHP_EOL, $block) as $line) {
            $this->flushLine($line . PHP_EOL);
        }
    }

    /**
     * Flush JS showMessage() call (modal dialog).
     *
     * @param string $type  info|success|warning|danger
     */
    public function flushJsMessage(string $message, string $type = 'info'): void
    {
        $this->flush('<script type="text/javascript">showMessage("' . $message . '");</script>');
    }

    /**
     * Flush JS showNotification() call (toast notification).
     */
    public function flushJsNotification(string $message): void
    {
        $this->flush('<script type="text/javascript">showNotification("' . $message . '");</script>');
    }

    /**
     * Log verbose message — alias của flushLine với verbose check.
     * Dùng cho services cần log debug info.
     */
    public function verbose(string $message): void
    {
        $verbose = \CloudPad\Core\Request::getBool('verbose', true);
        if ($verbose) {
            $this->flushLine($message);
        }
    }
}
