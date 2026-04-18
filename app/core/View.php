<?php
declare(strict_types=1);

namespace Core;

class View
{
    public function render(string $template, array $data = []): string
    {
        $viewFile = BASE_PATH . '/app/views/' . $template . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            return 'View not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
        }

        // Extract data so templates can use $title, $description, etc.
        extract($data, EXTR_SKIP);

        // Render inner view first
        ob_start();
        include $viewFile;
        $content = (string) ob_get_clean();

        // Then render main layout with $content
        ob_start();
        include BASE_PATH . '/app/views/layouts/main.php';
        return (string) ob_get_clean();
    }
}

