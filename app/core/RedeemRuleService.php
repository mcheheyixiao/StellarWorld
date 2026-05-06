<?php
declare(strict_types=1);

namespace Core;

use PDO;

class RedeemRuleService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @param array<string,mixed> $key
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function evaluate(array $key, array $context): array
    {
        $keyId = (int)($key['id'] ?? 0);
        $serverId = trim((string)($context['server_id'] ?? ''));
        $playerUuid = $this->normalizePlayerUuid((string)($context['player_uuid'] ?? ''));
        $playerName = trim((string)($context['player_name'] ?? ''));

        $allowedServerIds = $this->parseAllowedServerIds((string)($key['allowed_server_ids'] ?? ''));
        $boundPlayerUuidRaw = trim((string)($key['bound_player_uuid'] ?? ''));
        $boundPlayerUuid = $this->normalizePlayerUuid($boundPlayerUuidRaw);
        $boundPlayerName = trim((string)($key['bound_player_name'] ?? ''));
        $requireBoundAccount = (int)($key['require_bound_account'] ?? 0) === 1;
        $requireEmailVerified = (int)($key['require_email_verified'] ?? 0) === 1;
        $requireAccountActive = (int)($key['require_account_active'] ?? 0) === 1;
        $perPlayerLimit = max(0, (int)($key['per_player_limit'] ?? 0));
        $perAccountLimit = max(0, (int)($key['per_account_limit'] ?? 0));

        $snapshot = [
            'allowed_server_ids' => $allowedServerIds,
            'bound_player_uuid' => $boundPlayerUuid,
            'bound_player_name' => $boundPlayerName,
            'require_bound_account' => $requireBoundAccount,
            'require_email_verified' => $requireEmailVerified,
            'require_account_active' => $requireAccountActive,
            'per_player_limit' => $perPlayerLimit,
            'per_account_limit' => $perAccountLimit,
            'warnings' => [],
        ];

        if (
            $allowedServerIds === []
            && $boundPlayerUuid === ''
            && $boundPlayerName === ''
            && !$requireBoundAccount
            && !$requireEmailVerified
            && !$requireAccountActive
            && $perPlayerLimit === 0
            && $perAccountLimit === 0
        ) {
            return [
                'passed' => true,
                'reason' => '',
                'message' => '',
                'result' => 'skipped',
                'website_user_id' => null,
                'snapshot' => $snapshot,
            ];
        }

        if ($allowedServerIds !== [] && !$this->isServerAllowed($serverId, $allowedServerIds)) {
            return $this->reject('server_not_allowed', null, $snapshot);
        }

        if ($boundPlayerUuidRaw !== '' && $boundPlayerUuid === '') {
            return $this->reject('rule_invalid', null, $snapshot);
        }

        if ($boundPlayerUuid !== '' && $boundPlayerUuid !== $playerUuid) {
            return $this->reject('player_not_allowed', null, $snapshot);
        }

        if ($boundPlayerUuid === '' && $boundPlayerName !== '') {
            if ($playerName === '' || mb_strtolower($playerName) !== mb_strtolower($boundPlayerName)) {
                return $this->reject('player_not_allowed', null, $snapshot);
            }
        }

        $websiteUser = null;
        $needsWebsiteUser = $requireBoundAccount || $requireEmailVerified || $requireAccountActive || $perAccountLimit > 0;
        if ($needsWebsiteUser && $playerUuid !== '') {
            $websiteUser = $this->findWebsiteUserByMcUuid($playerUuid);
        }

        $websiteUserId = null;
        if (is_array($websiteUser) && isset($websiteUser['id'])) {
            $candidateId = (int)$websiteUser['id'];
            if ($candidateId > 0) {
                $websiteUserId = $candidateId;
            }
        }

        if ($requireBoundAccount && $websiteUserId === null) {
            return $this->reject('bound_account_required', null, $snapshot);
        }

        if ($requireEmailVerified) {
            if ($websiteUserId === null) {
                return $this->reject('bound_account_required', null, $snapshot);
            }
            if ((int)($websiteUser['email_verified'] ?? 0) !== 1) {
                return $this->reject('email_not_verified', $websiteUserId, $snapshot);
            }
        }

        if ($requireAccountActive) {
            if ($websiteUserId === null) {
                return $this->reject('bound_account_required', null, $snapshot);
            }
            if (strtolower(trim((string)($websiteUser['status'] ?? ''))) !== 'active') {
                return $this->reject('account_not_active', $websiteUserId, $snapshot);
            }
        }

        if ($perPlayerLimit > 0) {
            if ($playerUuid === '') {
                return $this->reject('rule_invalid', $websiteUserId, $snapshot);
            }
            $playerUsed = $this->countKeyClaimsByPlayer($keyId, $playerUuid);
            $snapshot['per_player_used'] = $playerUsed;
            if ($playerUsed >= $perPlayerLimit) {
                return $this->reject('per_player_limit_reached', $websiteUserId, $snapshot);
            }
        }

        if ($perAccountLimit > 0) {
            if ($websiteUserId === null) {
                if ($requireBoundAccount) {
                    return $this->reject('bound_account_required', null, $snapshot);
                }
                $snapshot['warnings'][] = 'per_account_limit_skipped_no_bound_user';
            } else {
                $accountUsed = $this->countKeyClaimsByWebsiteUser($keyId, $websiteUserId);
                $snapshot['per_account_used'] = $accountUsed;
                if ($accountUsed >= $perAccountLimit) {
                    return $this->reject('per_account_limit_reached', $websiteUserId, $snapshot);
                }
            }
        }

        return [
            'passed' => true,
            'reason' => '',
            'message' => '',
            'result' => 'passed',
            'website_user_id' => $websiteUserId,
            'snapshot' => $snapshot,
        ];
    }

    private function normalizePlayerUuid(string $uuid): string
    {
        $normalized = strtolower(str_replace('-', '', trim($uuid)));
        $normalized = preg_replace('/[^0-9a-f]/', '', $normalized) ?? '';
        return mb_substr($normalized, 0, 64);
    }

    /**
     * @return array<int,string>
     */
    private function parseAllowedServerIds(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = [];
                foreach ($decoded as $item) {
                    if (!is_scalar($item)) {
                        continue;
                    }
                    $value = trim((string)$item);
                    if ($value !== '') {
                        $items[] = $value;
                    }
                }
                return array_values(array_unique($items));
            }
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $items = [];
        foreach ($parts as $part) {
            $value = trim((string)$part);
            if ($value !== '') {
                $items[] = $value;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param array<int,string> $allowedServerIds
     */
    private function isServerAllowed(string $serverId, array $allowedServerIds): bool
    {
        if ($allowedServerIds === []) {
            return true;
        }

        $serverId = strtolower(trim($serverId));
        if ($serverId === '') {
            return false;
        }

        foreach ($allowedServerIds as $allowed) {
            if ($serverId === strtolower(trim($allowed))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findWebsiteUserByMcUuid(string $normalizedPlayerUuid): ?array
    {
        if ($normalizedPlayerUuid === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT id, email_verified, status, role '
            . 'FROM users '
            . 'WHERE mc_uuid = :mc_uuid '
            . 'LIMIT 1'
        );
        $stmt->execute([':mc_uuid' => $normalizedPlayerUuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function countKeyClaimsByPlayer(int $keyId, string $playerUuid): int
    {
        if ($keyId <= 0 || $playerUuid === '') {
            return 0;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM redeem_logs WHERE key_id = :key_id AND player_uuid = :player_uuid AND status IN ('executing','success')"
        );
        $stmt->execute([
            ':key_id' => $keyId,
            ':player_uuid' => $playerUuid,
        ]);
        return (int)$stmt->fetchColumn();
    }

    private function countKeyClaimsByWebsiteUser(int $keyId, int $websiteUserId): int
    {
        if ($keyId <= 0 || $websiteUserId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM redeem_logs WHERE key_id = :key_id AND website_user_id = :website_user_id AND status IN ('executing','success')"
        );
        $stmt->execute([
            ':key_id' => $keyId,
            ':website_user_id' => $websiteUserId,
        ]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    private function reject(string $reason, ?int $websiteUserId, array $snapshot): array
    {
        return [
            'passed' => false,
            'reason' => $reason,
            'message' => $this->reasonMessage($reason),
            'result' => 'rejected',
            'website_user_id' => $websiteUserId,
            'snapshot' => $snapshot,
        ];
    }

    private function reasonMessage(string $reason): string
    {
        return match ($reason) {
            'server_not_allowed' => 'This code cannot be redeemed on this server.',
            'player_not_allowed' => 'This code is restricted to another player.',
            'bound_account_required' => 'A bound website account is required.',
            'email_not_verified' => 'The bound website account email is not verified.',
            'account_not_active' => 'The bound website account is not active.',
            'per_player_limit_reached' => 'Player redeem limit reached for this code.',
            'per_account_limit_reached' => 'Website account redeem limit reached for this code.',
            'rule_invalid' => 'Redeem rule is invalid.',
            default => 'Redeem rule rejected this claim.',
        };
    }
}
