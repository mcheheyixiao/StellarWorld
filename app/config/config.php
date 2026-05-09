<?php
declare(strict_types=1);

// Basic site configuration and environment constants

define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Database
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'mc_web');
define('DB_USER', getenv('DB_USER') ?: 'mc_web');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Self-hosted auth verification defaults
define('AUTH_CAPTCHA_EXPIRE_SECONDS', max(60, (int)(getenv('CAPTCHA_EXPIRE_SECONDS') ?: 300)));
define('AUTH_CAPTCHA_REFRESH_COOLDOWN_SECONDS', max(1, (int)(getenv('CAPTCHA_REFRESH_COOLDOWN_SECONDS') ?: 3)));
define('AUTH_CAPTCHA_REFRESH_LIMIT_WINDOW_SECONDS', max(60, (int)(getenv('CAPTCHA_REFRESH_LIMIT_WINDOW_SECONDS') ?: 300)));
define('AUTH_CAPTCHA_REFRESH_LIMIT_COUNT', max(1, (int)(getenv('CAPTCHA_REFRESH_LIMIT_COUNT') ?: 20)));

define('DEFAULT_EMAIL_DOMAIN_WHITELIST_ENABLED', getenv('EMAIL_DOMAIN_WHITELIST_ENABLED') !== false
    ? (trim((string)getenv('EMAIL_DOMAIN_WHITELIST_ENABLED')) === '1' ? '1' : '0')
    : '1');
define('DEFAULT_EMAIL_DOMAIN_WHITELIST', getenv('EMAIL_DOMAIN_WHITELIST') ?: 'qq.com,foxmail.com,163.com,126.com,gmail.com,outlook.com,hotmail.com,icloud.com,yahoo.com');
define('DEFAULT_EMAIL_CODE_EXPIRE_SECONDS', max(60, (int)(getenv('EMAIL_CODE_EXPIRE_SECONDS') ?: 600)));
define('DEFAULT_EMAIL_CODE_SEND_COOLDOWN_SECONDS', max(30, (int)(getenv('EMAIL_CODE_SEND_COOLDOWN_SECONDS') ?: 60)));
define('EMAIL_CODE_IP_HOURLY_LIMIT', max(1, (int)(getenv('EMAIL_CODE_IP_HOURLY_LIMIT') ?: 10)));
define('EMAIL_CODE_EMAIL_DAILY_LIMIT', max(1, (int)(getenv('EMAIL_CODE_EMAIL_DAILY_LIMIT') ?: 10)));

$_auditLogStorage = strtolower(trim((string)(getenv('AUDIT_LOG_STORAGE') ?: 'mysql')));
if (!in_array($_auditLogStorage, ['mysql', 'file', 'both'], true)) {
    $_auditLogStorage = 'mysql';
}
define('DEFAULT_AUDIT_LOG_STORAGE', $_auditLogStorage);
unset($_auditLogStorage);

// Auth rate-limit cooldown in seconds
define('AUTH_ACTION_COOLDOWN', max(1, (int)(getenv('AUTH_ACTION_COOLDOWN') ?: 60)));

// SMTP (placeholder, document in deploy_guide)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.example.com');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 465));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@example.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Minecraft Server');

// MUA Union OAuth2 (replace placeholders before production)
define('MUA_CLIENT_ID', getenv('MUA_CLIENT_ID') ?: '');
define('MUA_CLIENT_SECRET', getenv('MUA_CLIENT_SECRET') ?: '');
define('MUA_REDIRECT_URI', getenv('MUA_REDIRECT_URI') ?: '');

// Microsoft / Xbox / Minecraft OAuth feature flag
define('MICROSOFT_MINECRAFT_LOGIN_ENABLED', filter_var(
    getenv('MICROSOFT_MINECRAFT_LOGIN_ENABLED') ?: '0',
    FILTER_VALIDATE_BOOLEAN
));

// Microsoft / Xbox / Minecraft OAuth
define('MICROSOFT_CLIENT_ID', getenv('MICROSOFT_CLIENT_ID') ?: '');
define('MICROSOFT_CLIENT_SECRET', getenv('MICROSOFT_CLIENT_SECRET') ?: '');
define('MICROSOFT_REDIRECT_URI', getenv('MICROSOFT_REDIRECT_URI') ?: 'https://www.stellarvan.cn/auth/microsoft/callback');
define('MICROSOFT_OAUTH_AUTHORIZE_URL', getenv('MICROSOFT_OAUTH_AUTHORIZE_URL') ?: 'https://login.live.com/oauth20_authorize.srf');
define('MICROSOFT_OAUTH_TOKEN_URL', getenv('MICROSOFT_OAUTH_TOKEN_URL') ?: 'https://login.live.com/oauth20_token.srf');
define('MICROSOFT_OAUTH_SCOPE', getenv('MICROSOFT_OAUTH_SCOPE') ?: 'XboxLive.signin offline_access');

// Mod API shared secret
define('SERVER_TOKEN', getenv('SERVER_TOKEN') ?: '');
$_pluginAllowQueryTokenEnv = getenv('PLUGIN_ALLOW_QUERY_TOKEN');
if ($_pluginAllowQueryTokenEnv === false || trim((string)$_pluginAllowQueryTokenEnv) === '') {
    $_pluginAllowQueryToken = APP_ENV !== 'production';
} else {
    $_pluginAllowQueryToken = filter_var((string)$_pluginAllowQueryTokenEnv, FILTER_VALIDATE_BOOLEAN);
}
define('PLUGIN_ALLOW_QUERY_TOKEN', $_pluginAllowQueryToken);
unset($_pluginAllowQueryTokenEnv, $_pluginAllowQueryToken);

define('PLUGIN_REQUIRE_HMAC', filter_var((string)(getenv('PLUGIN_REQUIRE_HMAC') ?: '0'), FILTER_VALIDATE_BOOLEAN));
define('PLUGIN_HMAC_TIME_WINDOW_SECONDS', max(60, (int)(getenv('PLUGIN_HMAC_TIME_WINDOW_SECONDS') ?: 300)));

$_pluginAllowGetDeliveriesEnv = getenv('PLUGIN_ALLOW_GET_DELIVERIES');
if ($_pluginAllowGetDeliveriesEnv === false || trim((string)$_pluginAllowGetDeliveriesEnv) === '') {
    $_pluginAllowGetDeliveries = APP_ENV !== 'production';
} else {
    $_pluginAllowGetDeliveries = filter_var((string)$_pluginAllowGetDeliveriesEnv, FILTER_VALIDATE_BOOLEAN);
}
define('PLUGIN_ALLOW_GET_DELIVERIES', $_pluginAllowGetDeliveries);
unset($_pluginAllowGetDeliveriesEnv, $_pluginAllowGetDeliveries);

// Redeem V1 security and plugin bridge config
define('REDEEM_CODE_PEPPER', getenv('REDEEM_CODE_PEPPER') !== false ? trim((string)getenv('REDEEM_CODE_PEPPER')) : '');
define('REDEEM_CODE_CASE_INSENSITIVE', filter_var((string)(getenv('REDEEM_CODE_CASE_INSENSITIVE') ?: '1'), FILTER_VALIDATE_BOOLEAN));
define('REDEEM_PLUGIN_SERVER_ID', getenv('REDEEM_PLUGIN_SERVER_ID') !== false ? trim((string)getenv('REDEEM_PLUGIN_SERVER_ID')) : '');
define('REDEEM_PLUGIN_SERVER_SECRET', getenv('REDEEM_PLUGIN_SERVER_SECRET') !== false ? trim((string)getenv('REDEEM_PLUGIN_SERVER_SECRET')) : '');
define('REDEEM_PLUGIN_TIME_WINDOW_SECONDS', max(60, (int)(getenv('REDEEM_PLUGIN_TIME_WINDOW_SECONDS') ?: 300)));
define('REALTIME_INTERNAL_EVENT_URL', getenv('REALTIME_INTERNAL_EVENT_URL') !== false ? trim((string)getenv('REALTIME_INTERNAL_EVENT_URL')) : '');
define('REALTIME_INTERNAL_SECRET', getenv('REALTIME_INTERNAL_SECRET') !== false ? trim((string)getenv('REALTIME_INTERNAL_SECRET')) : '');
define('REALTIME_INTERNAL_TIMEOUT_MS', max(100, (int)(getenv('REALTIME_INTERNAL_TIMEOUT_MS') ?: 800)));
$_realtimeInternalUrl = getenv('REALTIME_INTERNAL_URL') !== false ? trim((string)getenv('REALTIME_INTERNAL_URL')) : '';
if ($_realtimeInternalUrl === '') {
    $_realtimeInternalUrl = 'http://127.0.0.1:3001';
}
define('REALTIME_INTERNAL_URL', $_realtimeInternalUrl);
unset($_realtimeInternalUrl);
define('SIGNIN_SERVER_ID', getenv('SIGNIN_SERVER_ID') !== false ? trim((string)getenv('SIGNIN_SERVER_ID')) : 'survival-1');
define('SIGNIN_REQUIRE_PLAYER_ONLINE', filter_var((string)(getenv('SIGNIN_REQUIRE_PLAYER_ONLINE') ?: '1'), FILTER_VALIDATE_BOOLEAN));
define('SIGNIN_REQUEST_TIMEOUT_MS', max(500, (int)(getenv('SIGNIN_REQUEST_TIMEOUT_MS') ?: 5000)));

$_skinProxyAllowedHostsRaw = trim((string)(getenv('SKIN_PROXY_ALLOWED_HOSTS') ?: 'textures.minecraft.net,sessionserver.mojang.com'));
$_skinProxyAllowedHosts = [];
if ($_skinProxyAllowedHostsRaw !== '') {
    $parts = preg_split('/\s*,\s*/', $_skinProxyAllowedHostsRaw) ?: [];
    foreach ($parts as $part) {
        $host = strtolower(trim((string)$part));
        if ($host !== '') {
            $_skinProxyAllowedHosts[] = $host;
        }
    }
}
if ($_skinProxyAllowedHosts === []) {
    $_skinProxyAllowedHosts = ['textures.minecraft.net'];
}
define('SKIN_PROXY_ALLOWED_HOSTS', array_values(array_unique($_skinProxyAllowedHosts)));
unset($_skinProxyAllowedHostsRaw, $_skinProxyAllowedHosts, $parts, $part, $host);

define('SKIN_PROXY_MAX_BYTES', max(262144, (int)(getenv('SKIN_PROXY_MAX_BYTES') ?: 2097152)));
define('SKIN_PROXY_MAX_DIMENSION', max(64, (int)(getenv('SKIN_PROXY_MAX_DIMENSION') ?: 2048)));
define('MC_SERVER_HOST', getenv('MC_SERVER_HOST') ?: '202.189.7.81');
define('MC_SERVER_PORT', (int)(getenv('MC_SERVER_PORT') ?: 11052));
define('RCON_HOST', getenv('RCON_HOST') ?: '127.0.0.1');
define('RCON_PORT', (int)(getenv('RCON_PORT') ?: 25575));
define('RCON_PASSWORD', getenv('RCON_PASSWORD') ?: '');
define('RCON_TIMEOUT', (float)(getenv('RCON_TIMEOUT') ?: 3.0));

// Admin realtime panel websocket config
$_realtimeEnabledEnv = strtolower((string)(getenv('REALTIME_ENABLE_PANEL') ?: '1'));
define('REALTIME_ENABLE_PANEL', !in_array($_realtimeEnabledEnv, ['0', 'false', 'off', 'no'], true));
unset($_realtimeEnabledEnv);
define('REALTIME_WS_URL', getenv('REALTIME_WS_URL') ?: 'ws://127.0.0.1:3001/ws/admin');
define('REALTIME_WS_AUTH_TOKEN', getenv('REALTIME_WS_AUTH_TOKEN') !== false ? (string)getenv('REALTIME_WS_AUTH_TOKEN') : '');
define('REALTIME_RECONNECT_INTERVAL_MS', max(500, (int)(getenv('REALTIME_RECONNECT_INTERVAL_MS') ?: 3000)));
define('REALTIME_WS_TICKET_TTL_SECONDS', max(60, min(300, (int)(getenv('REALTIME_WS_TICKET_TTL_SECONDS') ?: 120))));
$_wsTicketQueryParam = trim((string)(getenv('REALTIME_WS_TICKET_QUERY_PARAM') ?: 'token'));
if ($_wsTicketQueryParam === '') {
    $_wsTicketQueryParam = 'token';
}
define('REALTIME_WS_TICKET_QUERY_PARAM', $_wsTicketQueryParam);
unset($_wsTicketQueryParam);
define('REALTIME_TICKET_VERIFY_TOKEN', getenv('REALTIME_TICKET_VERIFY_TOKEN') !== false ? trim((string)getenv('REALTIME_TICKET_VERIFY_TOKEN')) : '');
define('REALTIME_TICKET_VERIFY_ALLOW_EMPTY_TOKEN', filter_var((string)(getenv('REALTIME_TICKET_VERIFY_ALLOW_EMPTY_TOKEN') ?: '0'), FILTER_VALIDATE_BOOLEAN));

// Website status data source (WebSocket service status center)
define('WS_STATUS_API_BASE', getenv('WS_STATUS_API_BASE') ?: 'http://127.0.0.1:3001');
define('WS_STATUS_API_TIMEOUT_MS', max(500, (int)(getenv('WS_STATUS_API_TIMEOUT_MS') ?: 2500)));
define('WS_STATUS_API_TOKEN', getenv('WS_STATUS_API_TOKEN') !== false ? trim((string)getenv('WS_STATUS_API_TOKEN')) : '');
define('PUBLIC_STATUS_WS_URL', getenv('PUBLIC_STATUS_WS_URL') !== false ? trim((string)getenv('PUBLIC_STATUS_WS_URL')) : '');

// Redis（可选；扩展未加载或连接失败时全站自动降级）
$_redisEnv = strtolower((string)(getenv('REDIS_ENABLED') ?: '1'));
define('REDIS_ENABLED', !in_array($_redisEnv, ['0', 'false', 'off', 'no'], true));
unset($_redisEnv);
define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
define('REDIS_PORT', (int) (getenv('REDIS_PORT') ?: 6379));
define('REDIS_TIMEOUT', (float) (getenv('REDIS_TIMEOUT') ?: 2.0));
define('REDIS_PASSWORD', getenv('REDIS_PASSWORD') !== false ? (string) getenv('REDIS_PASSWORD') : '');
define('REDIS_DB', (int) (getenv('REDIS_DB') ?: 0));

// Paths for JSON cache
define('CACHE_PATH', BASE_PATH . '/storage/cache');
if (!is_dir(CACHE_PATH)) {
    @mkdir(CACHE_PATH, 0775, true);
}

// Static assets paths used in CSS/JS
define('PUBLIC_PATH', BASE_PATH . '/public');

// Baidu push (optional): sitemap base URL and active push token
define('SITE_BASE_URL', rtrim(getenv('SITE_BASE_URL') ?: 'http://localhost', '/'));
define('BAIDU_PUSH_TOKEN', getenv('BAIDU_PUSH_TOKEN') ?: '');

// Trusted reverse proxies (comma-separated IP/CIDR), e.g. "127.0.0.1,10.0.0.0/8"
$_trustedProxiesRaw = trim((string)(getenv('TRUSTED_PROXIES') ?: ''));
$_trustedProxies = [];
if ($_trustedProxiesRaw !== '') {
    $parts = preg_split('/\s*,\s*/', $_trustedProxiesRaw) ?: [];
    foreach ($parts as $part) {
        $item = trim((string)$part);
        if ($item !== '') {
            $_trustedProxies[] = $item;
        }
    }
}
define('TRUSTED_PROXIES', $_trustedProxies);
unset($_trustedProxiesRaw, $_trustedProxies, $parts, $part, $item);

// --- NapCatQQ 机器人配置 ---
// 填入机器人所在服务器的公网IP和端口
define('QQ_BOT_API_URL', getenv('QQ_BOT_API_URL') ?: '');
// 填入你在 WebUI 里设置的密码 (如果没有设置就留空 '')
define('QQ_BOT_API_TOKEN', getenv('QQ_BOT_API_TOKEN') !== false ? (string)getenv('QQ_BOT_API_TOKEN') : '');
// 填入你的玩家交流群群号
define('QQ_GROUP_ID', (int)(getenv('QQ_GROUP_ID') ?: 0));
