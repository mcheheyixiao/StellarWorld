<?php
declare(strict_types=1);

use Core\Router;
use Controller\HomeController;
use Controller\AuthController;
use Controller\ApiController;
use Controller\PageController;
use Controller\AdminController;
use Controller\ProfileController;
use Controller\AdminRedeemController;
use Controller\AdminRedeemApiController;
use Controller\MinecraftRedeemApiController;

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
$router->post('/internal/realtime/signin-result', [ApiController::class, 'realtimeSigninResult']);

// Admin redeem APIs
$router->get('/api/admin/redeem/categories', [AdminRedeemApiController::class, 'categories']);
$router->post('/api/admin/redeem/categories', [AdminRedeemApiController::class, 'createCategory']);
$router->patch('/api/admin/redeem/categories/{id}', [AdminRedeemApiController::class, 'updateCategory']);
$router->delete('/api/admin/redeem/categories/{id}', [AdminRedeemApiController::class, 'deleteCategory']);

$router->get('/api/admin/redeem/keys', [AdminRedeemApiController::class, 'keys']);
$router->get('/api/admin/redeem/keys/export', [AdminRedeemApiController::class, 'exportKeys']);
$router->post('/api/admin/redeem/keys/batch', [AdminRedeemApiController::class, 'batchGenerateKeys']);
$router->patch('/api/admin/redeem/keys/{id}/revoke', [AdminRedeemApiController::class, 'revokeKey']);
$router->post('/api/admin/redeem/keys/revoke-batch', [AdminRedeemApiController::class, 'revokeBatchKeys']);
$router->post('/api/admin/redeem/keys/delete-batch', [AdminRedeemApiController::class, 'deleteBatchKeys']);

$router->get('/api/admin/redeem/batches', [AdminRedeemApiController::class, 'batches']);
$router->get('/api/admin/redeem/batches/{id}', [AdminRedeemApiController::class, 'batchDetail']);
$router->get('/api/admin/redeem/batches/{id}/stats', [AdminRedeemApiController::class, 'batchStats']);

$router->get('/api/admin/redeem/logs', [AdminRedeemApiController::class, 'logs']);
$router->patch('/api/admin/redeem/logs/{id}/admin-status', [AdminRedeemApiController::class, 'updateLogAdminStatus']);
$router->get('/api/admin/redeem/admin-logs', [AdminRedeemApiController::class, 'adminLogs']);
$router->get('/api/admin/redeem/stats/publish', [AdminRedeemApiController::class, 'statsPublish']);

// Minecraft redeem APIs
$router->post('/api/minecraft/redeem/claim', [MinecraftRedeemApiController::class, 'claim']);
$router->post('/api/minecraft/redeem/{redeemId}/complete', [MinecraftRedeemApiController::class, 'complete']);
$router->post('/api/minecraft/redeem/{redeemId}/fail', [MinecraftRedeemApiController::class, 'fail']);
$router->post('/api/minecraft/redeem/heartbeat', [MinecraftRedeemApiController::class, 'heartbeat']);

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
$router->get('/admin/redeem', [AdminRedeemController::class, 'index']);
$router->get('/admin/realtime', [AdminController::class, 'realtime']);
$router->get('/admin/realtime-ticket', [AdminController::class, 'realtimeTicket']);
$router->get('/admin/checkin/rewards', [AdminController::class, 'legacyCheckinRewardsRedirect']);
$router->post('/admin/checkin/rewards/save', [AdminController::class, 'checkinRewardSave']);
$router->post('/admin/realtime-ticket/verify', [AdminController::class, 'realtimeTicketVerify']);
$router->get('/admin/feedback/attachment', [AdminController::class, 'feedbackAttachment']);
$router->post('/admin/players/update', [AdminController::class, 'playerUpdate']);
$router->post('/admin/players/delete', [AdminController::class, 'playerDelete']);
$router->post('/admin/players/unbind', [AdminController::class, 'playerUnbind']);
$router->post('/admin/announcements/save', [AdminController::class, 'announcementSave']);
$router->post('/admin/announcements/delete', [AdminController::class, 'announcementDelete']);
$router->post('/admin/milestones/save', [AdminController::class, 'milestoneSave']);
$router->post('/admin/milestones/delete', [AdminController::class, 'milestoneDelete']);
$router->post('/admin/gallery/upload', [AdminController::class, 'galleryUpload']);
$router->post('/admin/gallery/delete', [AdminController::class, 'galleryDelete']);
$router->post('/admin/signin-rewards/save-draft', [AdminController::class, 'signinRewardsSaveDraft']);
$router->post('/admin/signin-rewards/publish', [AdminController::class, 'signinRewardsPublish']);
$router->post('/admin/signin-rewards/delete-rule', [AdminController::class, 'signinRewardsDeleteRule']);
$router->post('/admin/signin-rewards/test-send', [AdminController::class, 'signinRewardsTestSend']);
$router->post('/admin/ip-blacklist/add', [AdminController::class, 'ipBlacklistAdd']);
$router->post('/admin/ip-blacklist/delete', [AdminController::class, 'ipBlacklistDelete']);
$router->post('/admin/site-settings/save', [AdminController::class, 'saveSettings']);
$router->post('/admin/team-members/save', [AdminController::class, 'teamMemberSave']);
$router->post('/admin/team-members/delete', [AdminController::class, 'teamMemberDelete']);
$router->post('/admin/ip-whitelist/add', [AdminController::class, 'ipWhitelistAdd']);
$router->post('/admin/ip-whitelist/delete', [AdminController::class, 'ipWhitelistDelete']);
$router->post('/admin/feedback/update', [AdminController::class, 'feedbackUpdate']);

