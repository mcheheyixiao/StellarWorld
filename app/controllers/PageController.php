<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Database;
use Core\LeaderboardSnapshot;
use Core\MUAFetcher;
use Core\MinecraftUuid;
use PDOException;

class PageController extends Controller
{
    public function gallery(): string
    {
        $db = Database::connection();
        $stmt = $db->query('SELECT * FROM gallery_images ORDER BY created_at DESC');
        $images = $stmt->fetchAll() ?: [];

        return $this->render('pages/gallery', [
            'title' => '服务器相册',
            'images' => $images,
        ]);
    }

    public function leaderboard(): string
    {
        $snap = LeaderboardSnapshot::getOrBuild();

        return $this->render('pages/leaderboard', [
            'title' => '排行榜',
            'leaderboards' => $snap['leaderboards'],
            'lastUpdate' => $snap['lastUpdate'],
            'leaderboardError' => $snap['leaderboardError'],
        ]);
    }

    public function playerProfile(): string
    {
        $username = trim((string)($_GET['username'] ?? ''));
        if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{1,32}$/', $username)) {
            http_response_code(404);
            return $this->render('errors/404', ['title' => '玩家不存在']);
        }

        $playerId = '—';
        $coins = 0;
        $mcUuid = '';
        $muaSub = '';
        $skinTextureUrl = 'https://crafatar.com/skins/8667ba7b-8a27-4e0d-a4d4-8ebcdd474334';
        $playerFound = false;
        $playerId = '—';
        $mcUsername = '';
        $skinRenderName = 'MHF_Steve';
        $skinBodyUrl = 'https://minotar.net/armor/body/' . rawurlencode($skinRenderName) . '/300.png';
        $skinRawUrl = 'https://crafthead.net/skin/' . rawurlencode($skinRenderName);
        $skinUseProxy = false;
        $statsData = [
            'playTimeHours' => 0.0,
            'mined' => 0,
            'placed' => 0,
            'fishCaught' => 0,
            'kills' => 0,
            'deaths' => 0,
        ];
        $radarData = [
            'kills' => 0,
            'deaths' => 0,
            'placed' => 0,
            'mined' => 0,
            'fishCaught' => 0,
        ];
        $timelineEvents = [];

        try {
            $db = Database::connection();
            $stmt = $db->prepare('SELECT id, username, coins, mc_uuid, mc_username, mua_sub FROM users WHERE username = :username LIMIT 1');
            $stmt->execute([':username' => $username]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $playerFound = true;
                $mcUsername = trim((string)($row['mc_username'] ?? ''));
                $skinRenderName = $mcUsername !== '' ? $mcUsername : 'MHF_Steve';
                $skinBodyUrl = 'https://minotar.net/armor/body/' . rawurlencode($skinRenderName) . '/300.png';
                $skinRawUrl = 'https://crafthead.net/skin/' . rawurlencode($skinRenderName);
                $playerId = (string)($row['id'] ?? '—');
                $coins = (int)($row['coins'] ?? 0);
                $mcUuid = (string)($row['mc_uuid'] ?? '');
                $muaSub = trim((string)($row['mua_sub'] ?? ''));
                $dashed = MinecraftUuid::normalizeToDashed($mcUuid);
                if ($dashed !== '') {
                    $skinTextureUrl = 'https://crafatar.com/skins/' . rawurlencode($dashed);
                }

                if ($muaSub !== '') {
                    $lookupName = trim((string)($row['mc_username'] ?? ''));
                    if ($lookupName === '') {
                        $lookupName = (string)($row['username'] ?? $username);
                    }
                    if ($lookupName !== '') {
                        try {
                            $muaSkin = (new MUAFetcher())->getSkinDirectUrl($lookupName, $muaSub);
                            if (is_string($muaSkin) && $muaSkin !== '') {
                                $skinTextureUrl = $muaSkin;
                            }
                        } catch (\Throwable $e) {
                            // keep crafatar fallback on any MUA error
                        }
                    }
                }

                if ($skinTextureUrl !== '' && strpos($skinTextureUrl, 'https://crafatar.com/skins/') !== 0) {
                    $skinRawUrl = $skinTextureUrl;
                    $skinUseProxy = true;
                }
            }

            if ($mcUuid !== '') {
                $stmt = $db->prepare(
                    'SELECT play_time_ticks, blocks_mined, blocks_placed, fish_caught, player_kills, deaths
                     FROM player_stats
                     WHERE mc_uuid = :mc_uuid
                     LIMIT 1'
                );
                $stmt->execute([':mc_uuid' => $mcUuid]);
                $statRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!is_array($statRow)) {
                    $statRow = [];
                }

                $playTimeTicks = (int)($statRow['play_time_ticks'] ?? 0);
                $statsData['playTimeHours'] = $playTimeTicks <= 0 ? 0.0 : round($playTimeTicks / 72000, 2);

                $statsData['mined'] = (int)($statRow['blocks_mined'] ?? 0);
                $statsData['placed'] = (int)($statRow['blocks_placed'] ?? 0);
                $statsData['fishCaught'] = (int)($statRow['fish_caught'] ?? 0);
                $statsData['kills'] = (int)($statRow['player_kills'] ?? 0);
                $statsData['deaths'] = (int)($statRow['deaths'] ?? 0);

                $radarData = [
                    'kills' => $statsData['kills'],
                    'deaths' => $statsData['deaths'],
                    'placed' => $statsData['placed'],
                    'mined' => $statsData['mined'],
                    'fishCaught' => $statsData['fishCaught'],
                ];
            }

            $userIdInt = (int)$playerId;
            if ($userIdInt > 0) {
                $stmt = $db->prepare('
                    (
                        SELECT
                            created_at AS event_time,
                            "checkin" AS event_type,
                            checkin_date AS checkin_date,
                            NULL AS action,
                            NULL AS details_json
                        FROM user_checkins
                        WHERE user_id = :user_id
                    )
                    UNION ALL
                    (
                        SELECT
                            created_at AS event_time,
                            "audit" AS event_type,
                            NULL AS checkin_date,
                            action AS action,
                            details AS details_json
                        FROM audit_logs
                        WHERE user_id = :user_id
                    )
                    ORDER BY event_time DESC
                    LIMIT 10
                ');
                $stmt->execute([':user_id' => $userIdInt]);
                $events = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                $timelineEvents = [];
                foreach ($events as $e) {
                    $eventType = (string)($e['event_type'] ?? '');
                    $eventTime = (string)($e['event_time'] ?? '');

                    if ($eventType === 'checkin') {
                        $checkinDate = (string)($e['checkin_date'] ?? '');
                        $timelineEvents[] = [
                            'time' => $eventTime,
                            'title' => '每日签到',
                            'sub' => $checkinDate !== '' ? ('签到日期：' . $checkinDate) : null,
                        ];
                        continue;
                    }

                    if ($eventType === 'audit') {
                        $action = (string)($e['action'] ?? '');
                        $detailsJson = $e['details_json'] ?? null;
                        $detailsArr = [];
                        if (is_string($detailsJson) && trim($detailsJson) !== '') {
                            $decoded = json_decode($detailsJson, true);
                            if (is_array($decoded)) {
                                $detailsArr = $decoded;
                            }
                        }

                        $label = $action;
                        if ($action === 'LOGIN' && array_key_exists('success', $detailsArr)) {
                            $success = $detailsArr['success'];
                            if (is_bool($success)) {
                                $label = $success ? '登录成功' : '登录失败';
                            } else {
                                $label = ($success ? '登录成功' : '登录失败');
                            }
                        }

                        $sub = null;
                        if (isset($detailsArr['username']) && is_string((string)$detailsArr['username']) && $detailsArr['username'] !== '') {
                            $sub = '用户：' . (string)$detailsArr['username'];
                        }

                        $timelineEvents[] = [
                            'time' => $eventTime,
                            'title' => $label !== '' ? $label : '行为记录',
                            'sub' => $sub,
                        ];
                    }
                }
            }
        } catch (\PDOException $e) {
            // 展示页：数据库读取失败时仍允许渲染占位信息
        }

        return $this->render('pages/player_profile', [
            'title' => '玩家数据 - ' . $username,
            'username' => $username,
            'playerFound' => $playerFound,
            'playerId' => $playerId,
            'coins' => $coins,
            'mcUsername' => $mcUsername,
            'skinRenderName' => $skinRenderName,
            'skinBodyUrl' => $skinBodyUrl,
            'skinRawUrl' => $skinRawUrl,
            'skinUseProxy' => $skinUseProxy,
            'statsData' => $statsData,
            'radarData' => $radarData,
            'timelineEvents' => $timelineEvents,
        ]);
    }

    public function announcements(): string
    {
        $db = Database::connection();
        $stmt = $db->query('SELECT id, title, created_at FROM announcements WHERE is_published = 1 AND created_at <= NOW() ORDER BY created_at DESC');
        $items = $stmt->fetchAll() ?: [];

        return $this->render('pages/announcements', [
            'title' => '公告板',
            'announcements' => $items,
        ]);
    }

    public function announcementView(): string
    {
        $id = (int)($_GET['id'] ?? 0);
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM announcements WHERE id = :id AND is_published = 1 AND created_at <= NOW() LIMIT 1');
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch() ?: null;

        if (!$item) {
            http_response_code(404);
            return $this->render('errors/404', ['title' => '公告不存在']);
        }

        $rawDesc = strip_tags((string)($item['content'] ?? ''));
        $description = $rawDesc === '' ? '' : mb_substr($rawDesc, 0, 100, 'UTF-8') . (mb_strlen($rawDesc, 'UTF-8') > 100 ? '...' : '');
        $keywords = (isset($item['tags']) && $item['tags'] !== '') ? (string)$item['tags'] : '';

        return $this->render('pages/announcement_view', [
            'title' => ((string)$item['title']) . ' - 公告详情',
            'announcement' => $item,
            'description' => $description,
            'keywords' => $keywords,
        ]);
    }

    public function about(): string
    {
        $db = Database::connection();
        $milestones = $db->query('SELECT id, milestone_date, description, created_at FROM milestones ORDER BY created_at DESC, id DESC')->fetchAll() ?: [];

        /** @var array<string,mixed> $aboutConfig */
        $aboutConfig = require BASE_PATH . '/app/config/about_config.php';

        $members = [];
        try {
            $rows = $db->query('SELECT username, role FROM team_members ORDER BY id ASC')->fetchAll() ?: [];
            foreach ($rows as $row) {
                $username = trim((string)($row['username'] ?? ''));
                if ($username === '') {
                    continue;
                }
                $role = trim((string)($row['role'] ?? ''));
                if ($role === '') {
                    $role = '服务器成员';
                }
                $avatarUrl = 'https://minotar.net/helm/' . rawurlencode($username) . '/100.png';
                $members[] = [
                    'username' => $username,
                    'role' => $role,
                    'avatar' => $avatarUrl,
                ];
            }
        } catch (\PDOException $e) {
            $members = [];
        }

        return $this->render('pages/about', [
            'title' => '关于我们',
            'aboutConfig' => $aboutConfig,
            'members' => $members,
            'milestones' => $milestones,
        ]);
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $baseUrl = SITE_BASE_URL;
        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# 后台与敏感路径不收录',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /auth',
            'Disallow: /auth/',
            'Disallow: /api',
            'Disallow: /api/',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            '',
            'Sitemap: ' . $baseUrl . '/sitemap.xml',
        ];
        echo implode("\n", $lines);
        exit;
    }

    public function sitemap(): void
    {
        header('Content-Type: text/xml; charset=utf-8');
        $baseUrl = SITE_BASE_URL;
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $staticUrls = ['/', '/gallery', '/leaderboard', '/announcements', '/about'];
        foreach ($staticUrls as $path) {
            $xml .= '  <url><loc>' . htmlspecialchars($baseUrl . $path, ENT_XML1, 'UTF-8') . '</loc></url>' . "\n";
        }

        $db = Database::connection();
        $stmt = $db->query('SELECT id, created_at FROM announcements WHERE is_published = 1');
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as $row) {
            $loc = $baseUrl . '/announcements/view?id=' . (int)$row['id'];
            $xml .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc></url>' . "\n";
        }

        $xml .= '</urlset>';
        echo $xml;
        exit;
    }
}

