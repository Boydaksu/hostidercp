<?php
declare(strict_types=1);
namespace Hostider\Core\Security;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Id;
final class Session
{
    /** Detached snapshot: the native session lock is closed before returning. */
    public static function start(string $context, ?SessionGuard $guard = null): SessionSnapshot
    {
        self::open($context);
        try {
            $snapshot = self::snapshot();
            $guard?->assertActive($snapshot);
            self::commit();
            return $snapshot;
        } catch (\Throwable $error) {
            if (session_status() === PHP_SESSION_ACTIVE) { session_abort(); }
            throw $error;
        }
    }

    /** Short local state transform only. Never perform network/business work under this lock. */
    public static function mutate(string $context, \Closure $update, ?int $expectedVersion = null,
        ?SessionGuard $guard = null): SessionSnapshot
    {
        self::open($context);
        try {
            $before = self::snapshot();
            $guard?->assertActive($before);
            if ($expectedVersion !== null && $expectedVersion !== $before->version) {
                throw new HttpError(409, 'SESSION_CONFLICT');
            }
            $data = $update($before->data);
            if (!is_array($data)) { throw new \LogicException('Session transform must return array'); }
            $_SESSION['data'] = $data;
            $_SESSION['_version'] = $before->version + 1;
            $after = self::snapshot();
            self::commit();
            return $after;
        } catch (\Throwable $error) {
            if (session_status() === PHP_SESSION_ACTIVE) { session_abort(); }
            throw $error;
        }
    }

    /** Explicit login/step-up boundary. The old SID receives a persistent tombstone. */
    public static function rotate(string $context, ?SessionGuard $guard = null): SessionSnapshot
    {
        self::open($context);
        try {
            $guard?->assertActive(self::snapshot());
            $state = $_SESSION;
            $_SESSION = ['_retired' => true];
            if (!session_regenerate_id(false)) {
                self::commit();
                throw new \RuntimeException('Session rotation failed');
            }
            $_SESSION = $state;
            $_SESSION['_version'] = (int) $state['_version'] + 1;
            $_SESSION['data']['csrf'] = Csrf::token();
            $snapshot = self::snapshot();
            self::commit();
            $_COOKIE[session_name()] = session_id();
            return $snapshot;
        } catch (\Throwable $error) {
            if (session_status() === PHP_SESSION_ACTIVE) { session_abort(); }
            throw $error;
        }
    }

    public static function end(string $context): void
    {
        self::open($context);
        $_SESSION = ['_retired' => true];
        self::commit();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
        unset($_COOKIE[session_name()]);
    }

    private static function open(string $context): void
    {
        if (!in_array($context, ['admin', 'client'], true) || session_status() === PHP_SESSION_ACTIVE) {
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
        $requested = $_COOKIE[session_name()] ?? '';
        if (!is_string($requested) || ($requested !== '' && !preg_match('/^[A-Za-z0-9,-]{22,256}$/D', $requested))) {
            throw new HttpError(401, 'INVALID_SESSION');
        }
        session_id(is_string($requested) ? $requested : '');
        if (!session_start()) {
            throw new \RuntimeException('Session unavailable');
        }
        $now = time();
        if (isset($_SESSION['_context']) && $_SESSION['_context'] !== $context) {
            session_abort();
            throw new HttpError(401, 'SESSION_CONTEXT_MISMATCH');
        }
        if (($requested !== '' && session_id() !== $requested) || isset($_SESSION['_retired'])
            || (isset($_SESSION['_born']) && (!isset($_SESSION['_context'], $_SESSION['_version'], $_SESSION['data'], $_SESSION['_reference'])))
            || (isset($_SESSION['_born']) && ($now - (int) $_SESSION['_seen'] > 1800 || $now - (int) $_SESSION['_born'] > 28800))) {
            $_SESSION = ['_retired' => true];
            self::commit();
            throw new HttpError(401, 'SESSION_RETIRED');
        }
        if (!isset($_SESSION['_born'])) {
            $_SESSION = ['_born' => $now, '_seen' => $now, '_version' => 0, '_reference' => Id::uuid(), '_context' => $context, 'data' => []];
        }
        $_COOKIE[session_name()] = session_id();
        $_SESSION['_seen'] = $now;
        // AJAX reads never rotate cookies implicitly; Identity controls rotation boundaries.
    }

    private static function snapshot(): SessionSnapshot
    {
        return new SessionSnapshot($_SESSION['data'], (int) $_SESSION['_version'], $_SESSION['_reference']);
    }

    private static function commit(): void
    {
        if (!session_write_close()) { throw new \RuntimeException('Session commit failed'); }
        // Detached global writes can never silently persist after unlock.
        $_SESSION = [];
    }
}
