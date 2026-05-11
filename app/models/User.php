<?php
declare(strict_types=1);

namespace Model;

use Core\Model;
use PDO;

class User extends Model
{
    private function normalizeMinecraftUuid(string $uuid): string
    {
        return strtolower(str_replace('-', '', trim($uuid)));
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByMuaSub(string $sub): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE mua_sub = :mua_sub LIMIT 1');
        $stmt->execute([':mua_sub' => $sub]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, username, email, role, status
            FROM users
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function countOtherActiveAdmins(int $excludeUserId): int
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*)
            FROM users
            WHERE role = \'admin\'
              AND status = \'active\'
              AND id <> :exclude_id
        ');
        $stmt->execute([':exclude_id' => $excludeUserId]);
        return (int)$stmt->fetchColumn();
    }

    public function softDeleteByAdmin(int $userId, int $adminId, ?string $reason = null): bool
    {
        if ($userId <= 0 || $adminId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('
            UPDATE users
            SET status = \'frozen\',
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $userId]);
        $updated = $stmt->rowCount() > 0;

        $this->deleteRememberTokensByUserId($userId);

        if ($updated) {
            $safeReason = trim((string)$reason);
            if ($safeReason === '') {
                $safeReason = 'admin_soft_delete';
            }
            error_log('[Admin] Soft delete user by admin=' . $adminId . ' target=' . $userId . ' reason=' . $safeReason);
        }

        return $updated;
    }

    public function findByMinecraftUuid(string $uuid): ?array
    {
        $uuid = $this->normalizeMinecraftUuid($uuid);
        if ($uuid === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM users WHERE mc_uuid = :mc_uuid LIMIT 1');
        $stmt->execute([':mc_uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $username = trim((string)($data['username'] ?? ''));
        $mcUsername = trim((string)($data['mc_username'] ?? ''));
        if ($mcUsername === '') {
            $mcUsername = $username;
        }
        $mcUuid = trim((string)($data['mc_uuid'] ?? ''));
        $mcUuid = $mcUuid !== '' ? $this->normalizeMinecraftUuid($mcUuid) : '';
        if ($mcUuid === '') {
            $mcUuid = null;
        }

        $ip = trim((string)($data['ip'] ?? ''));
        $ip = $ip !== '' ? $ip : null;

        $regIp = trim((string)($data['regip'] ?? ''));
        if ($regIp === '') {
            $regIp = $ip;
        }
        $regIp = $regIp !== '' ? $regIp : null;

        $regDate = isset($data['regdate']) ? (int)$data['regdate'] : (int)floor(microtime(true) * 1000);
        $world = trim((string)($data['world'] ?? 'world'));
        if ($world === '') {
            $world = 'world';
        }

        $stmt = $this->db->prepare('
            INSERT INTO users (
                username, mc_username, mc_uuid, email, password_hash, role, status, email_verified, mua_sub,
                ip, regip, regdate, isLogged, hasSession, x, y, z, world, created_at, updated_at
            )
            VALUES (
                :username, :mc_username, :mc_uuid, :email, :password_hash, :role, :status, :email_verified, :mua_sub,
                :ip, :regip, :regdate, :isLogged, :hasSession, :x, :y, :z, :world, NOW(), NOW()
            )
        ');
        $stmt->execute([
            ':username' => $username,
            ':mc_username' => $mcUsername,
            ':mc_uuid' => $mcUuid,
            ':email' => $data['email'],
            ':password_hash' => $data['password_hash'],
            ':role' => $data['role'] ?? 'player',
            ':status' => $data['status'] ?? 'active',
            ':email_verified' => isset($data['email_verified']) ? (int)$data['email_verified'] : 0,
            ':mua_sub' => $data['mua_sub'] ?? null,
            ':ip' => $ip,
            ':regip' => $regIp,
            ':regdate' => $regDate,
            ':isLogged' => isset($data['isLogged']) ? (int)$data['isLogged'] : 0,
            ':hasSession' => isset($data['hasSession']) ? (int)$data['hasSession'] : 0,
            ':x' => isset($data['x']) ? (float)$data['x'] : 0.0,
            ':y' => isset($data['y']) ? (float)$data['y'] : 0.0,
            ':z' => isset($data['z']) ? (float)$data['z'] : 0.0,
            ':world' => $world,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function bindMuaSub(int $userId, string $sub): void
    {
        $stmt = $this->db->prepare('UPDATE users SET mua_sub = :mua_sub, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':mua_sub' => $sub,
            ':id' => $userId,
        ]);
    }

    public function setMuaSub(int $userId, ?string $sub): void
    {
        $stmt = $this->db->prepare('UPDATE users SET mua_sub = :mua_sub, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':mua_sub' => $sub,
            ':id' => $userId,
        ]);
    }

    public function getMuaSubByUserId(int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT mua_sub FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !isset($row['mua_sub'])) {
            return null;
        }
        $sub = trim((string)$row['mua_sub']);
        return $sub === '' ? null : $sub;
    }

    public function markEmailVerified(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET email_verified = 1, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    }

    public function setStatus(int $userId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $userId]);
    }

    public function bindCharacter(int $userId, string $mcUsername, string $mcUuid): void
    {
        $mcUuid = $this->normalizeMinecraftUuid($mcUuid);

        $stmt = $this->db->prepare('
            UPDATE users
            SET mc_username = :mc_username, mc_uuid = :mc_uuid, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':mc_username' => $mcUsername,
            ':mc_uuid' => $mcUuid,
            ':id' => $userId,
        ]);
    }

    public function unbindCharacter(int $userId): void
    {
        $stmt = $this->db->prepare('
            UPDATE users
            SET mc_username = NULL, mc_uuid = NULL, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([':id' => $userId]);
    }

    public function bindMinecraftAccount(int $userId, string $uuid, string $name): void
    {
        $uuid = $this->normalizeMinecraftUuid($uuid);

        $stmt = $this->db->prepare('
            UPDATE users
            SET mc_uuid = :uuid, mc_username = :name, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':uuid' => $uuid,
            ':name' => $name,
            ':id' => $userId,
        ]);
    }

    public function getProfile(int $userId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                u.id,
                u.username,
                u.email,
                u.role,
                u.status,
                u.mc_username,
                u.mc_uuid,
                u.mua_sub,
                u.last_mc_bind_at,
                u.created_at,
                u.isLogged,
                u.hasSession,
                CASE
                    WHEN u.mc_uuid IS NOT NULL AND u.mc_uuid <> \'\' THEN 1
                    ELSE 0
                END AS game_accounts_count,
                ps.play_time_ticks,
                ps.fly_distance_cm,
                ps.deaths,
                ps.fish_caught,
                ps.player_kills,
                ps.blocks_mined,
                ps.blocks_placed,
                ps.last_updated
            FROM users u
            LEFT JOIN player_stats ps
                ON REPLACE(LOWER(ps.mc_uuid), \'-\', \'\') = REPLACE(LOWER(u.mc_uuid), \'-\', \'\')
            WHERE u.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['game_accounts_count'] = (!empty($row['mc_uuid']) || !empty($row['mc_username'])) ? 1 : 0;

        $row['is_online'] = null;
        $row['online_count'] = '待检测';
        $row['current_online'] = '待检测';

        $playTimeTicks = (int)($row['play_time_ticks'] ?? 0);
        $playTimeHours = $playTimeTicks > 0 ? round($playTimeTicks / 72000, 2) : 0;
        if ($playTimeHours > 0) {
            $playTimeDisplay = (string)$playTimeHours . ' h';
            $row['total_play_time'] = $playTimeDisplay;
            $row['play_time'] = $playTimeDisplay;
            $row['total_duration'] = $playTimeDisplay;
        } else {
            $row['total_play_time'] = '--';
            $row['play_time'] = '--';
            $row['total_duration'] = '--';
        }

        $deaths = isset($row['deaths']) ? (int)$row['deaths'] : 0;
        $row['death_count'] = (string)$deaths;
        $row['deaths'] = (string)$deaths;
        $row['player_kills'] = (string)((int)($row['player_kills'] ?? 0));
        $row['blocks_mined'] = (string)((int)($row['blocks_mined'] ?? 0));
        $row['blocks_placed'] = (string)((int)($row['blocks_placed'] ?? 0));
        $row['fish_caught'] = (string)((int)($row['fish_caught'] ?? 0));

        return $row;
    }

    public function updatePassword(int $userId, string $newHash): void
    {
        $stmt = $this->db->prepare('
            UPDATE users
            SET password_hash = :password_hash, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':password_hash' => $newHash,
            ':id' => $userId,
        ]);
    }

    public function updateLoginMetadata(int $userId, string $ip): void
    {
        $lastLogin = (int)floor(microtime(true) * 1000);
        $safeIp = trim($ip);
        $safeIp = $safeIp !== '' ? $safeIp : null;

        $stmt = $this->db->prepare('
            UPDATE users
            SET ip = :ip,
                lastlogin = :lastlogin,
                isLogged = 1,
                hasSession = 1,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':ip' => $safeIp,
            ':lastlogin' => $lastLogin,
            ':id' => $userId,
        ]);
    }

    public function updateMinecraftBindWithCooldown(int $userId, string $mcUuid, string $mcName): void
    {
        $mcUuid = $this->normalizeMinecraftUuid($mcUuid);

        $stmt = $this->db->prepare('
            UPDATE users
            SET mc_uuid = :mc_uuid, mc_username = :mc_username, last_mc_bind_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':mc_uuid' => $mcUuid,
            ':mc_username' => $mcName,
            ':id' => $userId,
        ]);
    }

    public function updateMinecraftNameWithCooldown(int $userId, string $mcName): void
    {
        $stmt = $this->db->prepare('
            UPDATE users
            SET mc_username = :mc_username, last_mc_bind_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':mc_username' => $mcName,
            ':id' => $userId,
        ]);
    }

    /**
     * Store Remember Me token record for persistent multi-device login.
     *
     * Note: Only validator hash is persisted (never store raw validator).
     */
    public function storeRememberToken(int $userId, string $selector, string $validatorHash, string $expires): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO auth_tokens (user_id, selector, validator_hash, expires, created_at)
            VALUES (:user_id, :selector, :validator_hash, :expires, NOW())
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':selector' => $selector,
            ':validator_hash' => $validatorHash,
            ':expires' => $expires,
        ]);
    }

    /**
     * Load Remember Me token record and joined basic user info.
     */
    public function getRememberToken(string $selector): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                t.user_id,
                t.selector,
                t.validator_hash,
                t.expires,
                u.username,
                u.role,
                u.status
            FROM auth_tokens t
            INNER JOIN users u ON u.id = t.user_id
            WHERE t.selector = :selector
            LIMIT 1
        ');
        $stmt->execute([':selector' => $selector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Delete Remember Me token record by selector (single device).
     */
    public function deleteRememberToken(string $selector): void
    {
        $stmt = $this->db->prepare('
            DELETE FROM auth_tokens
            WHERE selector = :selector
            LIMIT 1
        ');
        $stmt->execute([':selector' => $selector]);
    }

    /**
     * Delete all Remember Me token records for one user.
     */
    public function deleteRememberTokensByUserId(int $userId): void
    {
        $stmt = $this->db->prepare('
            DELETE FROM auth_tokens
            WHERE user_id = :user_id
        ');
        $stmt->execute([':user_id' => $userId]);
    }
}

