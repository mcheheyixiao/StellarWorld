<?php
declare(strict_types=1);

namespace Core;

final class BaiduSEO
{
    private const BAIDU_PUSH_HOST = 'data.zz.baidu.com';
    private const BAIDU_PUSH_ENDPOINT = 'http://data.zz.baidu.com/urls';

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

        $response = SafeHttpClient::request('POST', $endpoint, [
            'headers' => [
                'Content-Type: text/plain; charset=utf-8',
                'Accept: application/json',
            ],
            'body' => $payload,
            'timeout' => 4,
            'connect_timeout' => 2,
            'max_redirects' => 0,
            'allowed_hosts' => [self::BAIDU_PUSH_HOST],
            'max_body_bytes' => 128 * 1024,
        ]);

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
}
