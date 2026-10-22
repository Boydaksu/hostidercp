<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
final class Session
{
    public static function start(string $context): void
    {
        if (!in_array($context, ['admin', 'client'], true) || session_status() !== PHP_SESSION_NONE) {
            throw new \LogicException('Invalid session context');
        }
        if (($_SERVER['HTTPS'] ?? '') !== 'on') {
            throw new \RuntimeException('HTTPS session required');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name('__Host-hostider_' . $context);
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
        if (!session_start()) {
            throw new \RuntimeException('Session unavailable');
        }
        $now = time();
        if (!isset($_SESSION['_born']) || $now - (int) $_SESSION['_seen'] > 1800 || $now - (int) $_SESSION['_born'] > 28800) {
            $_SESSION = ['_born' => $now, '_seen' => $now, '_rotated' => $now];
            session_regenerate_id(true);
        }
        if ($now - (int) $_SESSION['_rotated'] > 900) {
            session_regenerate_id(true);
            $_SESSION['_rotated'] = $now;
        }
        $_SESSION['_seen'] = $now;
    }
    public static function rotate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \LogicException('Session not active');
        }
        session_regenerate_id(true);
        $_SESSION['_rotated'] = time();
        $_SESSION['csrf'] = Csrf::token();
    }
    public static function end(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
            session_destroy();
        }
    }
}
