<?php
declare(strict_types=1);

namespace Controller;

use Core\Controller;

class HomeController extends Controller
{
    public function index(): string
    {
        return $this->render('home/index', [
            'title' => '主页',
            'description' => '欢迎来到我们的 Minecraft 公益服务器',
            'serverDisplayAddress' => 'mc.stellarvan.cn',
            'serverVersion' => '1.8+',
            'publicStatusWsUrl' => defined('PUBLIC_STATUS_WS_URL') ? (string)PUBLIC_STATUS_WS_URL : '',
        ]);
    }

    public function notFound(): string
    {
        http_response_code(404);
        return $this->render('errors/404', [
            'title' => '页面未找到',
        ]);
    }
}

