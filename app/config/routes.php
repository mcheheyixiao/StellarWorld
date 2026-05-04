<?php
declare(strict_types=1);

use Core\Router;
use Controller\HomeController;
use Controller\AuthController;
use Controller\ApiController;
use Controller\PageController;
use Controller\AdminController;
use Controller\ProfileController;

/** @var Router $router */

// Public pages
$router->get('/', [HomeController::class, 'index']);

// Auth pages
$router->get('/auth/login', [AuthController::class, 'showLogin']);
$router->get('/auth/register', [AuthController::class, 'showRegister']);
$router->get('/auth/captcha', [AuthController::class, 'captcha']);
$router->get('/auth/mua', [AuthController::class, 'muaRedirect']);
$router->get('/auth/mua/callback', [AuthController::class, 'muaCallback']);
$router->get('/auth/microsoft', [AuthController::class, 'microsoftRedirect']);
$router->get('/auth/microsoft/callback', [AuthController::class, 'microsoftCallback']);
$router->post('/auth/email-code/send', [AuthController::class, 'sendEmailCode']);
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/auth/register', [AuthController::class, 'register']);
$router->get('/auth/logout', [AuthController::class, 'logout']);
$router->post('/auth/logout', [AuthController::class, 'logout']);
$router->get('/auth/verify', [AuthController::class, 'verify']);
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'sendResetLink']);
$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'updatePassword']);

// API for Mod & cache
$router->post('/api/server/status/update', [ApiController::class, 'updateServerStatus']);
$router->get('/api/status', [ApiController::class, 'getServerStatus']);
$router->get('/api/status/cache', [ApiController::class, 'getServerStatus']);
$router->get('/api/status/health', [ApiController::class, 'getRealtimeHealth']);
$router->get('/api/plugins', [ApiController::class, 'getPlugins']);
$router->get('/api/players', [ApiController::class, 'getPlayers']);
$router->get('/api/chat', [ApiController::class, 'getChat']);
$router->get('/api/leaderboard/search', [ApiController::class, 'searchLeaderboard']);
$router->get('/api/skin-proxy', [ApiController::class, 'proxySkin']);
$router->get('/api/avatar', [ApiController::class, 'avatar']);
$router->get('/api/checkin/status', [ApiController::class, 'checkinStatus']);
$router->post('/api/checkin/claim', [ApiController::class, 'checkinClaim']);
$router->get('/api/checkin/history', [ApiController::class, 'checkinHistory']);
$router->get('/api/checkin/rewards', [ApiController::class, 'checkinRewards']);
$router->get('/api/plugin/checkin/deliveries', [ApiController::class, 'pluginCheckinDeliveries']);
$router->post('/api/plugin/checkin/deliveries', [ApiController::class, 'pluginCheckinDeliveries']);
$router->post('/api/plugin/checkin/deliveries/ack', [ApiController::class, 'pluginCheckinDeliveriesAck']);

// Profile pages
$router->get('/profile', [ProfileController::class, 'index']);
$router->post('/profile/password/update', [ProfileController::class, 'updatePassword']);
$router->post('/profile/password/quick-reset', [ProfileController::class, 'sendQuickResetEmail']);
$router->post('/profile/mc-character/update', [ProfileController::class, 'updateMinecraftCharacter']);
$router->post('/profile/feedback/create', [ProfileController::class, 'feedbackCreate']);
$router->post('/profile/feedback/supplement', [ProfileController::class, 'feedbackSupplement']);

// Content pages
$router->get('/gallery', [PageController::class, 'gallery']);
$router->get('/leaderboard', [PageController::class, 'leaderboard']);
$router->get('/player', [PageController::class, 'playerProfile']);
$router->get('/announcements', [PageController::class, 'announcements']);
$router->get('/announcements/view', [PageController::class, 'announcementView']);
$router->get('/about', [PageController::class, 'about']);
$router->get('/robots.txt', [PageController::class, 'robots']);
$router->get('/sitemap.xml', [PageController::class, 'sitemap']);

// Admin panel (requires admin role)
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/realtime', [AdminController::class, 'realtime']);
$router->get('/admin/realtime-ticket', [AdminController::class, 'realtimeTicket']);
$router->post('/admin/realtime-ticket/verify', [AdminController::class, 'realtimeTicketVerify']);
$router->get('/admin/feedback/attachment', [AdminController::class, 'feedbackAttachment']);
$router->post('/admin/players/update', [AdminController::class, 'playerUpdate']);
$router->post('/admin/players/delete', [AdminController::class, 'playerDelete']);
$router->post('/admin/players/unbind', [AdminController::class, 'playerUnbind']);
$router->post('/admin/checkin/rewards/save', [AdminController::class, 'checkinRewardSave']);
$router->post('/admin/announcements/save', [AdminController::class, 'announcementSave']);
$router->post('/admin/announcements/delete', [AdminController::class, 'announcementDelete']);
$router->post('/admin/milestones/save', [AdminController::class, 'milestoneSave']);
$router->post('/admin/milestones/delete', [AdminController::class, 'milestoneDelete']);
$router->post('/admin/gallery/upload', [AdminController::class, 'galleryUpload']);
$router->post('/admin/gallery/delete', [AdminController::class, 'galleryDelete']);
$router->post('/admin/ip-blacklist/add', [AdminController::class, 'ipBlacklistAdd']);
$router->post('/admin/ip-blacklist/delete', [AdminController::class, 'ipBlacklistDelete']);
$router->post('/admin/site-settings/save', [AdminController::class, 'saveSettings']);
$router->post('/admin/team-members/save', [AdminController::class, 'teamMemberSave']);
$router->post('/admin/team-members/delete', [AdminController::class, 'teamMemberDelete']);
$router->post('/admin/ip-whitelist/add', [AdminController::class, 'ipWhitelistAdd']);
$router->post('/admin/ip-whitelist/delete', [AdminController::class, 'ipWhitelistDelete']);
$router->post('/admin/feedback/update', [AdminController::class, 'feedbackUpdate']);

