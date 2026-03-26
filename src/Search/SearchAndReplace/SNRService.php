<?php
namespace CloudPad\Search\SearchAndReplace;

use CloudPad\Core\Output\OutputManager;

/**
 * SNRService — Search & Replace service.
 *
 * Phase 9.4: Tách snr_get_regex(), snr_get_pos(), snr_get_pos_with_regex(),
 * snr_replace(), snr_revert(), file_mask_matched(), snr_search()
 * ra khỏi Builder class.
 *
 * Builder giữ thin wrappers để backward-compat với snr_search.php plugin.
 *
 * Dependencies (sẽ inject trực tiếp ở Phase 10):
 *   - OutputManager: để flush kết quả search dòng-by-dòng
 *   - Builder (tạm thời): getRepositoryFilePaths, getRelPath, getLocalizedPath, file_get/put_contents
 */
class SNRService
{
    private OutputManager $output;
    private \CloudPad\Repository\RepositoryManagerInterface $repoManager;
    private \CloudPad\FileSystem\FileOperationsInterface $fileOps;

    public function __construct(
        OutputManager $output,
        \CloudPad\Repository\RepositoryManagerInterface $repoManager,
        \CloudPad\FileSystem\FileOperationsInterface $fileOps
    ) {
        $this->output      = $output;
        $this->repoManager = $repoManager;
        $this->fileOps     = $fileOps;
    }

    // ── Regex helpers ─────────────────────────────────────────────────────

    /**
     * Chuyển search string thành regex pattern nếu cần.
     * - Full regex (vd: /pattern/flags): trả nguyên
     * - Simple wildcard (có *): convert sang regex
     * - Plain string: trả null (dùng strpos)
     */
    public function getRegex(string $search, bool $caseInsensitive): ?string
    {
        if (preg_match('/^\/.+\/[a-z]*$/is', $search)) {
            return $search; // Already a full regex
        }

        if (stripos($search, '*') !== false) {
            $pattern = str_replace(['.*', '*'], '.+?', $search);
            $flags   = $caseInsensitive ? 'is' : 's';
            return '/' . $pattern . '/' . $flags;
        }

        return null; // Plain string — use strpos
    }

    /**
     * Tìm vị trí xuất hiện đầu tiên của $search trong $string.
     * Trả false nếu không tìm thấy.
     * $fullMatchedSegment: đoạn text thực sự được match.
     */
    public function getPos(
        string $string,
        string $search,
        bool   $caseInsensitive,
        string &$fullMatchedSegment = ''
    ): int|false {
        $regex = $this->getRegex($search, $caseInsensitive);

        if ($regex !== null) {
            return $this->getPosWithRegex($string, $regex, $fullMatchedSegment);
        }

        if ($caseInsensitive) {
            $pos = stripos($string, $search);
        } else {
            $pos = strpos($string, $search);
        }

        if ($pos !== false) {
            $fullMatchedSegment = $search;
        }

        return $pos;
    }

    /**
     * Tìm vị trí match đầu tiên bằng regex.
     */
    public function getPosWithRegex(
        string $string,
        string $regex,
        string &$fullMatchedSegment = ''
    ): int|false {
        if (preg_match($regex, $string, $match, PREG_OFFSET_CAPTURE)) {
            $fullMatchedSegment = $match[0][0];
            return $match[0][1];
        }
        return false;
    }

    // ── File mask ─────────────────────────────────────────────────────────

    /**
     * Kiểm tra path có match file mask pattern không.
     * Supports: comma-separated, wildcards, exclude (prefix "-").
     */
    public function fileMaskMatched(string $pattern, string $path): bool
    {
        if (empty($pattern)) {
            return true;
        }

        $patterns         = preg_split('/[,;\s]+/', $pattern, -1, PREG_SPLIT_NO_EMPTY);
        $includePatterns  = [];
        $excludePatterns  = [];

        foreach ($patterns as $pat) {
            $pat = trim($pat);
            if (empty($pat)) continue;

            if ($pat[0] === '-') {
                $excludePatterns[] = substr($pat, 1);
            } else {
                $includePatterns[] = $pat;
            }
        }

        // Exclude check — nếu match exclude → loại
        foreach ($excludePatterns as $pat) {
            $regex = '/' . str_replace(['/', '.', '*'], ['\\/', '\\.', '.+'], $pat) . '/is';
            if (preg_match($regex, $path)) {
                return false;
            }
        }

        // Include check — nếu có include pattern → phải match ít nhất 1
        foreach ($includePatterns as $pat) {
            $regex = '/' . str_replace(['/', '.', '*'], ['\\/', '\\.', '.+'], $pat) . '/is';
            if (preg_match($regex, $path)) {
                return true;
            }
        }

        return empty($includePatterns); // Không có include pattern → match all
    }

    // ── Replace & Revert ──────────────────────────────────────────────────

    /**
     * Thực hiện search & replace trong một file.
     * Backup nội dung gốc vào $_SESSION['snr-backup'] trước khi ghi.
     */
    public function replace(
        string $filepath,
        string $search,
        bool   $caseInsensitive,
        string $replace,
        string $repository = ''
    ): void {
        $content = $this->fileOps->fileGetContents($filepath, $repository);
        $regex   = $this->getRegex($search, $caseInsensitive);

        if ($regex !== null) {
            $content2 = preg_replace($regex, $replace, $content);
        } else {
            $content2 = str_replace($search, $replace, $content);
        }

        if ($content2 !== $content) {
            $_SESSION['snr-backup'][$filepath] = $content;
            $this->fileOps->filePutContents($filepath, $content2, $repository, $ignored, false);
        }
    }

    /**
     * Revert tất cả files đã được replace trong session hiện tại.
     */
    public function revert(): void
    {
        foreach ($_SESSION['snr-backup'] ?? [] as $filepath => $content) {
            $this->fileOps->filePutContents($filepath, $content);
            $this->output->flushLine("Restore $filepath\n");
        }
    }

    // ── Main search ───────────────────────────────────────────────────────

    /**
     * Search & Replace chính — tìm kiếm text qua tất cả files của repository.
     *
     * @param string $repository        Repository code
     * @param string $search            Search string (plain, wildcard, hoặc /regex/)
     * @param bool   $caseInsensitive   Case insensitive search
     * @param string $fileMask          File pattern filter (vd: "*.php, -*.min.js")
     * @param int    $maxFoundFiles     Dừng sau khi tìm đủ số file này
     * @param bool   $searchByFilename  Tìm theo tên file thay vì nội dung
     * @param bool   $forceReplace      Thực hiện replace nếu true
     * @param string $replace           Chuỗi thay thế (khi forceReplace=true)
     * @param bool   $forceDelete       Xoá file nếu match (khi searchByFilename=true)
     */
    public function search(
        string $repository,
        string $search,
        bool   $caseInsensitive  = true,
        string $fileMask         = '',
        int    $maxFoundFiles    = 20,
        bool   $searchByFilename = false,
        bool   $forceReplace     = false,
        string $replace          = '',
        bool   $forceDelete      = false
    ): void {
        $filepaths = $this->repoManager->getRepositoryFilePaths($repository, false);

        $searchFile    = 0;
        $foundFile     = 0;
        $foundOcc      = 0;
        $manualCmds    = [];

        foreach ($filepaths as $path) {
            if (!$this->fileMaskMatched($fileMask, $path)) {
                continue;
            }

            $relpath = $this->fileOps->getRelPath($path, $repository);

            // ── Search by filename mode ───────────────────────────────────
            if ($searchByFilename) {
                if ($this->fileMaskMatched($search, $path)) {
                    $foundFile++;

                    if ($forceDelete) {
                        $manualCmds[] = "svn delete $path";
                    } else {
                        $this->output->flush(
                            "\nFound file: <span data-url=\"index.php?action=open-inline-file"
                            . "&repository=$repository&file=$relpath\""
                            . " class=\"snr-file js-snr-file\">$path</span>\n"
                        );
                    }

                    if ($foundFile >= $maxFoundFiles) break;
                }
                continue;
            }

            // ── Search by content mode ────────────────────────────────────
            $searchFile++;
            $localPath = $this->fileOps->getLocalizedPath($path, $repository);
            $handle    = @fopen($localPath, 'r');
            $found     = false;
            $lineNo    = 0;
            $occ       = 0;

            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $lineNo++;
                    $line = trim($line);
                    $pos  = $this->getPos($line, $search, $caseInsensitive, $fullMatchedSegment);

                    if ($pos === false) continue;

                    if (!$found) {
                        $this->output->flush(
                            "<div class=\"snr-item\" data-repository=\"$repository\" data-file=\"$relpath\">"
                            . "<span class=\"snr-item-header\">Processing file: "
                            . "<span class=\"snr-file\">$relpath</span></span>\n"
                            . "<div class=\"snr-item-body\">"
                        );
                    }
                    $found = true;

                    $occ += $caseInsensitive
                        ? substr_count(strtoupper($line), strtoupper($fullMatchedSegment))
                        : substr_count($line, $fullMatchedSegment);

                    $segment  = substr($line, max(0, $pos - 50), strlen($fullMatchedSegment) + 50);
                    $segment  = htmlentities($segment, ENT_QUOTES);
                    $_search  = htmlentities($fullMatchedSegment, ENT_QUOTES);
                    $segment  = preg_replace(
                        '/(' . preg_quote($_search, '/') . ')/s' . ($caseInsensitive ? 'i' : ''),
                        '<span class="snr-match">\\1</span>',
                        $segment
                    );

                    $fileUrl = "index.php?action=open-inline-file&repository=$repository&file=$relpath&line=$lineNo";
                    $this->output->flush(
                        "<span data-line=\"$lineNo\" data-url=\"$fileUrl\""
                        . " class=\"snr-line js-snr-file\">- Line $lineNo -&nbsp;&nbsp;&nbsp;&nbsp; "
                        . trim($segment) . "</span>\n"
                    );

                    // Inline replace preview
                    if ($forceReplace && !empty($replace)) {
                        $seg2     = substr($line, max(0, $pos - 50), strlen($fullMatchedSegment) + 50);
                        $seg2     = str_replace($fullMatchedSegment, $replace, $seg2);
                        $seg2     = htmlentities($seg2, ENT_QUOTES);
                        $_replace = htmlentities($replace, ENT_QUOTES);
                        $seg2     = preg_replace(
                            '/(' . preg_quote($_replace, '/') . ')/s' . ($caseInsensitive ? 'i' : ''),
                            '<span class="snr-replacement">\\1</span>',
                            $seg2
                        );
                        $this->output->flush(
                            "<span data-line=\"$lineNo\" data-url=\"$fileUrl\""
                            . " class=\"snr-line js-snr-file\">- Replaced by -&nbsp;&nbsp;&nbsp;&nbsp; "
                            . trim($seg2) . "</span>\n"
                        );
                    }
                }
                fclose($handle);
            }

            if (!$found) continue;

            if ($forceReplace) {
                $this->replace($path, $search, $caseInsensitive, $replace, $repository);
            }

            if ($forceDelete) {
                @unlink($path);
                $this->output->flush(
                    "<div class=\"snr-item\"><span class=\"snr-item-header\">"
                    . "Deleting file: <span class=\"snr-file\">$path</span></span>\n"
                );
            }

            $foundFile++;
            $foundOcc += $occ;
            $this->output->flush("  <span>Found $occ occurrences.</span>");
            $this->output->flush("</div></div>");

            if ($foundFile >= $maxFoundFiles) break;
        }

        if (!empty($manualCmds)) {
            $this->output->flush(implode("\n", $manualCmds));
        }

        if (!$forceDelete) {
            $this->output->flush(
                "  <span class=\"snr-file\">Searched $searchFile file(s), "
                . "found $foundOcc occurrences in $foundFile file(s).</span>\n"
            );
        } else {
            $this->output->flush(
                "  <span class=\"snr-file\">Searched $searchFile file(s), "
                . "deleted $foundFile file(s).</span>\n"
            );
        }

        if (!$foundOcc && !$forceDelete) {
            $this->output->flush(
                "  <span style=\"color:red\">HINTS: Rebuild the repository's indexes and try again.</span>\n"
            );
        }
    }
}
