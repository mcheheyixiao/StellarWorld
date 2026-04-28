<?php
declare(strict_types=1);

namespace Core;

final class BaiduSEO
{
    private const BAIDU_PUSH_HOST = 'data.zz.baidu.com';
    private const BAIDU_PUSH_ENDPOINT = 'https://data.zz.baidu.com/urls';

    /**
     * @return list<string>
     */
    public function collectPublicUrls(): array
    {
        $baseUrl = rtrim((string)(defined('SITE_BASE_URL') ? SITE_BASE_URL : 'http://localhost'), '/');
        $urls = [
            $baseUrl . '/',
            $baseUrl . '/gallery',
            $baseUrl . '/leaderboard',
            $baseUrl . '/announcements',
            $baseUrl . '/about',
        ];

        try {
            $db = Database::connection();
            $stmt = $db->query('SELECT id FROM announcements WHERE is_published = 1 AND created_at <= NOW()');
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $id = isset($row['id']) && is_numeric($row['id']) ? (int)$row['id'] : 0;
                if ($id > 0) {
                    $urls[] = $baseUrl . '/announcements/view?id=' . $id;
                }
            }
        } catch (\Throwable $e) {
            // Keep static URLs only when database is unavailable.
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param list<string> $urls
     * @return array{
     *   status:bool,
     *   success_count:int,
     *   remain:int|null,
     *   not_same_site:int|null,
     *   not_valid:int|null,
     *   message:string
     * }
     */
    public function pushUrlsToBaidu(array $urls): array
    {
        $token = trim((string)(defined('BAIDU_PUSH_TOKEN') ? BAIDU_PUSH_TOKEN : ''));
        if ($token === '') {
            return [
                'status' => false,
                'success_count' => 0,
                'remain' => null,
                'not_same_site' => null,
                'not_valid' => null,
                'message' => 'Baidu push token is not configured',
            ];
        }

        $baseUrl = rtrim((string)(defined('SITE_BASE_URL') ? SITE_BASE_URL : 'http://localhost'), '/');
        if ($urls === []) {
            return [
                'status' => true,
                'success_count' => 0,
                'remain' => null,
                'not_same_site' => null,
                'not_valid' => null,
                'message' => 'No URLs to push',
            ];
        }

        $payload = implode("\n", array_values(array_filter(array_map(
            static fn ($u): string => trim((string)$u),
            $urls
        ))));
        if ($payload === '') {
            return [
                'status' => true,
                'success_count' => 0,
                'remain' => null,
                'not_same_site' => null,
                'not_valid' => null,
                'message' => 'No URLs to push',
            ];
        }

        $endpoint = self::BAIDU_PUSH_ENDPOINT
            . '?site=' . rawurlencode($baseUrl)
            . '&token=' . rawurlencode($token);

        $response = $this->requestBaiduPush($endpoint, $payload);

        if (($response['success'] ?? false) !== true) {
            $error = trim((string)($response['error'] ?? 'Request failed'));
            return [
                'status' => false,
                'success_count' => 0,
                'remain' => null,
                'not_same_site' => null,
                'not_valid' => null,
                'message' => $error === '' ? 'Request failed' : $error,
            ];
        }

        $decoded = json_decode((string)($response['body'] ?? ''), true);
        if (!is_array($decoded)) {
            return [
                'status' => false,
                'success_count' => 0,
                'remain' => null,
                'not_same_site' => null,
                'not_valid' => null,
                'message' => 'Baidu API returned invalid JSON',
            ];
        }

        $successCount = isset($decoded['success']) && is_numeric($decoded['success']) ? (int)$decoded['success'] : 0;
        $remain = isset($decoded['remain']) && is_numeric($decoded['remain']) ? (int)$decoded['remain'] : null;
        $notSameSite = isset($decoded['not_same_site']) && is_numeric($decoded['not_same_site']) ? (int)$decoded['not_same_site'] : null;
        $notValid = isset($decoded['not_valid']) && is_numeric($decoded['not_valid']) ? (int)$decoded['not_valid'] : null;

        $message = 'Baidu push finished';
        if (isset($decoded['error']) && is_scalar($decoded['error'])) {
            $message = trim((string)$decoded['error']) !== '' ? (string)$decoded['error'] : $message;
        } elseif (isset($decoded['message']) && is_scalar($decoded['message'])) {
            $message = trim((string)$decoded['message']) !== '' ? (string)$decoded['message'] : $message;
        }

        $ok = $successCount > 0 || (($notSameSite ?? 0) === 0 && ($notValid ?? 0) === 0);
        return [
            'status' => $ok,
            'success_count' => $successCount,
            'remain' => $remain,
            'not_same_site' => $notSameSite,
            'not_valid' => $notValid,
            'message' => $message,
        ];
    }

    /**
     * @return array{success:bool,body?:string,error?:string}
     */
    private function requestBaiduPush(string $endpoint, string $payload): array
    {
        $parts = parse_url($endpoint);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host !== self::BAIDU_PUSH_HOST) {
            return [
                'success' => false,
                'error' => 'Invalid Baidu push endpoint',
            ];
        }

        $headers = [
            'Content-Type: text/plain; charset=utf-8',
            'Accept: application/json',
        ];
        $maxBodyBytes = 128 * 1024;

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            if ($ch === false) {
                return [
                    'success' => false,
                    'error' => 'Unable to initialize cURL',
                ];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            }
            if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
            }

            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!is_string($body) || $body === '') {
                return [
                    'success' => false,
                    'error' => $curlError !== '' ? $curlError : 'Empty response from Baidu API',
                ];
            }
            if (strlen($body) > $maxBodyBytes) {
                return [
                    'success' => false,
                    'error' => 'Baidu API response exceeded size limit',
                ];
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                return [
                    'success' => false,
                    'error' => 'Baidu API HTTP ' . $httpCode,
                ];
            }

            return [
                'success' => true,
                'body' => $body,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $payload,
                'timeout' => 4,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($endpoint, false, $context);
        if (!is_string($body) || $body === '') {
            return [
                'success' => false,
                'error' => 'Empty response from Baidu API',
            ];
        }
        if (strlen($body) > $maxBodyBytes) {
            return [
                'success' => false,
                'error' => 'Baidu API response exceeded size limit',
            ];
        }

        $httpCode = 0;
        $responseHeaders = $http_response_header ?? [];
        if (is_array($responseHeaders)) {
            foreach ($responseHeaders as $headerLine) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', (string)$headerLine, $matches) === 1) {
                    $httpCode = (int)$matches[1];
                    break;
                }
            }
        }
        if ($httpCode !== 0 && ($httpCode < 200 || $httpCode >= 300)) {
            return [
                'success' => false,
                'error' => 'Baidu API HTTP ' . $httpCode,
            ];
        }

        return [
            'success' => true,
            'body' => $body,
        ];
    }
}
