<?php
declare(strict_types=1);

namespace Controller;

use Core\ApiCode;
use Core\ApiResponse;
use Core\Controller;

class AdminRedeemController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(): void
    {
        header('Location: /admin?tab=redeem');
        exit;
    }

    private function requireAdmin(): void
    {
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            if ($this->isAjaxRequest() || array_key_exists('ajax', $_GET) || array_key_exists('ajax', $_POST)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo ApiResponse::error(ApiCode::AUTH_INVALID, 'Unauthorized');
                exit;
            }

            header('Location: /auth/login');
            exit;
        }
    }
}
