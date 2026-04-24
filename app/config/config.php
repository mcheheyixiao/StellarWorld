<?php
declare(strict_types=1);

// Basic site configuration and environment constants

define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Database
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'mc_web');
define('DB_USER', getenv('DB_USER') ?: 'mc_web');
define('DB_PASS', getenv('DB_PASS') ?: 'stellarvan1314');

//TurnStile
define('TURNSTILE_SITE_KEY', getenv('TURNSTILE_SITE_KEY') ?: '0x4AAAAAACqnWvWf2swGTcJI');
define('TURNSTILE_SECRET_KEY', getenv('TURNSTILE_SECRET_KEY') ?: '0x4AAAAAACqnWqghuOt6J1y7KnTV-8ykW50');

// SMTP (placeholder, document in deploy_guide)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.163.com');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 465));
define('SMTP_USER', getenv('SMTP_USER') ?: '18060524036@163.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'XPUsR39n7J8c2DfT');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '18060524036@163.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Minecraft Server');

// MUA Union OAuth2 (replace placeholders before production)
define('MUA_CLIENT_ID', getenv('MUA_CLIENT_ID') ?: '5a26c32cec484ffea1f9f16b1ed9a1df');
define('MUA_CLIENT_SECRET', getenv('MUA_CLIENT_SECRET') ?: 'MrjTfiChiTzVmPOm4aRrOB14mIGweuX9Dloqf2OCgLqQPToZqF6GWjBRYbvYg8k6');
define('MUA_REDIRECT_URI', getenv('MUA_REDIRECT_URI') ?: 'https://www.stellarvan.cn/auth/mua/callback');

// Mod API shared secret
define('SERVER_TOKEN', getenv('SERVER_TOKEN') ?: '123456');
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
define('REALTIME_WS_URL', getenv('REALTIME_WS_URL') ?: 'wss://realtime.stellarvan.cn/ws/admin?token=mcheheyixiao1314');
define('REALTIME_WS_AUTH_TOKEN', getenv('REALTIME_WS_AUTH_TOKEN') !== false ? (string)getenv('REALTIME_WS_AUTH_TOKEN') : '');
define('REALTIME_RECONNECT_INTERVAL_MS', max(500, (int)(getenv('REALTIME_RECONNECT_INTERVAL_MS') ?: 3000)));

// Website status data source (WebSocket service status center)
define('WS_STATUS_API_BASE', getenv('WS_STATUS_API_BASE') ?: 'http://localhost:3001');
define('WS_STATUS_API_TIMEOUT_MS', max(500, (int)(getenv('WS_STATUS_API_TIMEOUT_MS') ?: 2500)));
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
define('SITE_BASE_URL', rtrim(getenv('SITE_BASE_URL') ?: 'https://www.stellarvan.cn', '/'));
define('BAIDU_PUSH_TOKEN', getenv('BAIDU_PUSH_TOKEN') ?: 'f2cr3mSw2wn16St1');

// --- NapCatQQ 机器人配置 ---
// 填入机器人所在服务器的公网IP和端口
define('QQ_BOT_API_URL', 'http://111.222.333.444:3000'); 
// 填入你在 WebUI 里设置的密码 (如果没有设置就留空 '')
define('QQ_BOT_API_TOKEN', 'UA2ue08UY30DyBSH');      
// 填入你的玩家交流群群号
define('QQ_GROUP_ID', 123456789);
