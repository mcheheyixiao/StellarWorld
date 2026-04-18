<?php
declare(strict_types=1);

namespace Core;

final class MUAFetcher
{
    private const UNION_LIST_URL = 'https://skin.mualliance.ltd/api/union';

    private const MUA_FALLBACK_BASE = 'https://skin.mualliance.ltd';

    public function getSkinDirectUrl(string $playerName, string $muaSub): ?string
    {
        $playerName = trim($playerName);
        $muaSub = trim($muaSub);
        if ($playerName === '' || $muaSub === '') {
            return null;
        }

        $parts = explode(':', $muaSub, 2);
        $code = strtoupper(trim($parts[0]));
        error_log("[MUAFetcher Debug] Parsed Code: {$code} for player: {$playerName}");

        $baseUrl = null;
        $unionData = $this->requestJson(self::UNION_LIST_URL);
        if (is_array($unionData)) {
            $list = $unionData['nodes'] ?? $unionData['data'] ?? $unionData;
            if (is_array($list)) {
                foreach ($list as $node) {
                    if (is_array($node) && !empty($node['url'])) {
                        $nodeCode = strtoupper((string)($node['code'] ?? ''));
                        if ($nodeCode === $code) {
                            $baseUrl = rtrim((string)$node['url'], '/');
                            break;
                        }
                    }
                }
            }
        }

        if ($baseUrl === null && $code === 'MUA') {
            $baseUrl = self::MUA_FALLBACK_BASE;
        }

        if ($baseUrl === null || $baseUrl === '') {
            error_log("MUAFetcher Error: Node URL not found for code [{$code}]");
            return null;
        }

        error_log("[MUAFetcher Debug] Found Base URL: {$baseUrl} for Code: {$code}");

        $cslPaths = [
            '/csl/' . rawurlencode($playerName) . '.json',
            '/' . rawurlencode($playerName) . '.json',
        ];

        error_log('[MUAFetcher Debug] Trying CSL paths: ' . implode(', ', array_map(static fn (string $p): string => $baseUrl . $p, $cslPaths)));

        foreach ($cslPaths as $path) {
            $cslData = $this->requestJson($baseUrl . $path);
            if (is_array($cslData) && isset($cslData['skins']) && is_array($cslData['skins'])) {
                $hash = $cslData['skins']['slim'] ?? $cslData['skins']['default'] ?? null;
                if (is_string($hash) && preg_match('/^[a-f0-9]{64}$/i', $hash)) {
                    error_log("[MUAFetcher Debug] Successfully found hash via CSL: {$hash}");
                    return $baseUrl . '/textures/' . strtolower($hash);
                }
            }
        }

        $profilesUrl = $baseUrl . '/api/yggdrasil/api/profiles/minecraft/' . rawurlencode($playerName);
        error_log("[MUAFetcher Debug] CSL did not yield skin hash; trying Yggdrasil UUID lookup: {$profilesUrl}");

        $uuidPayload = $this->requestJson($profilesUrl);
        if (is_array($uuidPayload) && !empty($uuidPayload['id'])) {
            $uuidForProfile = $this->normalizeYggdrasilUuid((string)$uuidPayload['id']);
            error_log('[MUAFetcher Debug] Yggdrasil profile UUID resolved: ' . ($uuidForProfile !== '' ? $uuidForProfile : '(invalid)'));
            if ($uuidForProfile !== '') {
                $profileUrl = $baseUrl . '/api/yggdrasil/sessionserver/session/minecraft/profile/' . $uuidForProfile;
                error_log("[MUAFetcher Debug] Fetching Yggdrasil session profile: {$profileUrl}");

                $profilePayload = $this->requestJson($profileUrl);
                if (is_array($profilePayload) && !empty($profilePayload['properties']) && is_array($profilePayload['properties'])) {
                    foreach ($profilePayload['properties'] as $prop) {
                        if (!is_array($prop)) {
                            continue;
                        }
                        if (($prop['name'] ?? '') === 'textures' && !empty($prop['value'])) {
                            $decoded = base64_decode((string)$prop['value'], true);
                            $textures = $decoded !== false && $decoded !== '' ? json_decode($decoded, true) : null;
                            $url = is_array($textures) ? (string)($textures['textures']['SKIN']['url'] ?? '') : '';
                            if ($url !== '') {
                                error_log("[MUAFetcher Debug] Successfully found skin URL via Yggdrasil: {$url}");
                                return $url;
                            }
                        }
                    }
                }
            }
        } else {
            error_log('[MUAFetcher Debug] Yggdrasil UUID lookup returned no id for player: ' . $playerName);
        }

        error_log("MUAFetcher Error: Could not find skin for {$playerName} on {$baseUrl}");
        return null;
    }

    private function normalizeYggdrasilUuid(string $id): string
    {
        $id = strtolower(trim($id));
        if ($id === '') {
            return '';
        }
        $hex = preg_replace('/[^a-f0-9]/', '', $id) ?? '';
        if (strlen($hex) !== 32) {
            return '';
        }
        return $hex;
    }

    /**
     * GET JSON; returns null on any failure.
     *
     * @return array<string, mixed>|null
     */
    private function requestJson(string $url): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $resp = curl_exec($ch);
            $errno = curl_errno($ch);
            $curlErr = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            error_log("[MUAFetcher Debug] cURL GET {$url} - HTTP Code: {$httpCode}");
            if ($errno !== 0) {
                error_log("[MUAFetcher Debug] cURL Error for {$url}: " . $curlErr);
            }
            curl_close($ch);

            if ($errno !== 0 || !is_string($resp) || $resp === '') {
                return null;
            }
            if ($httpCode === 404) {
                return null;
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                return null;
            }

            $json = json_decode($resp, true);
            return is_array($json) ? $json : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents($url, false, $context);
        if ($resp === false || !is_string($resp) || $resp === '') {
            return null;
        }
        $hdrs = $http_response_header ?? [];
        if (!empty($hdrs[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $hdrs[0], $m)) {
            $code = (int)$m[1];
            if ($code === 404 || $code < 200 || $code >= 300) {
                return null;
            }
        }

        $json = json_decode($resp, true);
        return is_array($json) ? $json : null;
    }
}
