<?php
namespace CloudPad\Core;

/**
 * Response — Cách DUY NHẤT để trả JSON response trong CloudPad.
 *
 * Quy ước:
 *   Response::ok($data)       → {"success":true, ...}
 *   Response::fail($message)  → {"success":false,"message":"..."}
 *
 * KHÔNG dùng echo json_encode() trực tiếp ở bất kỳ đâu.
 */
class Response
{
    /**
     * Trả JSON thành công.
     *
     * @param array|null  $payload  Dữ liệu cần trả, merge vào root object
     * @param string|null $message  Thông báo tuỳ chọn
     */
    public static function ok($payload = null, ?string $message = null): void
    {
        $result = ['success' => true];

        if (is_array($payload)) {
            $result = array_merge($result, $payload);
        } elseif ($payload !== null) {
            $result['data'] = $payload;
        }

        if ($message !== null) {
            $result['message'] = $message;
        }

        self::json($result);
    }

    /**
     * Trả JSON lỗi.
     *
     * @param string $message  Mô tả lỗi rõ ràng
     * @param array  $extra    Dữ liệu bổ sung (vd: ['code' => 422])
     */
    public static function fail(string $message, array $extra = []): void
    {
        $payload = array_merge(['success' => false, 'message' => $message], $extra);
        self::json($payload);
    }

    /**
     * Trả raw JSON — internal use only.
     * Luôn set Content-Type đúng và exit sau khi gửi.
     */
    public static function json(array $data): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Trigger file download.
     *
     * @param string      $filepath  Đường dẫn tuyệt đối đến file
     * @param string|null $filename  Tên file download (mặc định: basename $filepath)
     */
    public static function download(string $filepath, ?string $filename = null): void
    {
        if (!is_file($filepath)) {
            self::fail('File not found.');
        }

        $filename = $filename ?? basename($filepath);

        if (!headers_sent()) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: no-cache');
        }

        readfile($filepath);
        exit;
    }
}
