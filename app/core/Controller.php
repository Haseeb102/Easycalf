<?php
namespace App\Core;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 1));
}

class Controller
{
    protected $db = null;
    protected $data = [];

    public function __construct()
    {
        try {
            if (class_exists('Database')) {
                $this->db = new \Database();
            }
        } catch (\Throwable $e) {
            error_log('Controller construct error: ' . $e->getMessage());
            $this->db = null;
        }
    }

    protected function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    protected function render(string $view, array $data = [], ?string $module = null): void
    {
        $vars = array_merge($this->data, $data);

        if ($module === null) {
            $module = $this->detectModuleName();
        }

        // If view contains a slash like "settings/index", extract the module part
        if (strpos($view, '/') !== false) {
            $parts = explode('/', $view, 2);
            $firstPart = $parts[0];
            
            // Check if first part matches the current module name (case-insensitive)
            if (strtolower($firstPart) === strtolower($module)) {
                // Remove the redundant module prefix: "settings/index" becomes "index"
                $view = $parts[1];
            } else {
                // First part is a different module, use it
                $maybeView = $parts[1];
                if (is_dir(BASE_PATH . '/app/modules/' . $firstPart)) {
                    $module = $firstPart;
                    $view = $maybeView;
                }
            }
        }

        $viewFile = BASE_PATH . '/app/modules/' . $module . '/views/' . $view . '.php';

        if (!is_readable($viewFile)) {
            $alt = BASE_PATH . '/app/views/' . $module . '/' . $view . '.php';
            if (is_readable($alt)) {
                $viewFile = $alt;
            } else {
                http_response_code(500);
                echo "<h1>View not found</h1>";
                echo "<p>Could not find view: " . htmlspecialchars($viewFile) . "</p>";
                echo "<p><strong>Module:</strong> {$module} | <strong>View:</strong> {$view}</p>";
                echo "<p><strong>Looking for:</strong></p>";
                echo "<ul>";
                echo "<li>" . htmlspecialchars($viewFile) . "</li>";
                echo "<li>" . htmlspecialchars($alt) . "</li>";
                echo "</ul>";
                error_log("Controller::render - view not found: {$viewFile}");
                return;
            }
        }

        extract($vars, EXTR_SKIP);
        include $viewFile;
    }

    protected function json($payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
        $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        echo "<script>window.location.href = '{$escaped}';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url={$escaped}'></noscript>";
        exit;
    }

    protected function detectModuleName(): string
    {
        try {
            $ref = new \ReflectionClass($this);
            $short = $ref->getShortName();
            if (substr($short, -10) === 'Controller') {
                $name = substr($short, 0, -10);
                return $name ?: 'Core';
            }
            return $short;
        } catch (\ReflectionException $e) {
            return 'Core';
        }
    }

    protected function isAdmin(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['user_role']) && strtolower((string)$_SESSION['user_role']) === 'admin') {
            return true;
        }
        if (!empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin') {
            return true;
        }
        return false;
    }

    protected function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Admin access required'], 403);
            }
            $this->redirect('/public/login');
        }
    }

    protected function isLoggedIn(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return !empty($_SESSION['logged_in']) || !empty($_SESSION['user_id']);
    }
}