<?php
declare(strict_types=1);
namespace Hostider\Migration\Discovery;
use Hostider\Migration\Contract;

/** Narrow POSIX local temporary handoff. Raw bytes are unlinked before any write. */
final class PrivateExport
{
    public static function assertRoot(string $root): void
    {
        Contract::require(function_exists('posix_geteuid') && str_starts_with($root, '/') && realpath($root) === $root, 'GO_PRIVATE_DIRECTORY');
        $part = $root;
        while (true) {
            clearstatcache(true, $part); $s = lstat($part);
            Contract::require($s !== false && ($s['mode'] & 0170000) === 0040000, 'GO_DIRECTORY_LINK');
            if ($part === $root) {
                Contract::require(($s['mode'] & 0777) === 0700 && $s['uid'] === posix_geteuid(), 'GO_DIRECTORY_PERMISSIONS');
            }
            if ($part === '/') { break; } $part = dirname($part);
        }
    }
    public static function adapt(
        string $root, #[\SensitiveParameter] string $json, string $source, string $snapshot,
        string $pin, #[\SensitiveParameter] string $key,
    ): array {
        self::assertRoot($root);
        Contract::require(strlen($json) <= StrictJson::MAX_BYTES, 'GO_EXPORT_SIZE');
        $directory = $root.'/go03-'.bin2hex(random_bytes(16)); $path = $directory.'/inventory';
        $handle = null; $made = false; $linked = false; $mode = umask(0077);
        try {
            Contract::require(@mkdir($directory, 0700), 'GO_TEMP_DIRECTORY'); $made = true;
            self::assertRoot($directory);
            $handle = @fopen($path, 'x+b'); Contract::require(is_resource($handle), 'GO_TEMP_FILE'); $linked = true;
            $stat = fstat($handle); $named = lstat($path);
            Contract::require($stat !== false && $named !== false && $stat['ino'] === $named['ino'] && $stat['dev'] === $named['dev']
                && ($stat['mode'] & 0170000) === 0100000 && ($stat['mode'] & 0777) === 0600
                && $stat['uid'] === posix_geteuid(), 'GO_TEMP_PERMISSIONS');
            Contract::require(unlink($path), 'GO_TEMP_UNLINK'); $linked = false;
            Contract::require(fwrite($handle, $json) === strlen($json) && fflush($handle) && rewind($handle), 'GO_TEMP_WRITE');
            return (new InventoryAdapter())->convert($handle, $source, $snapshot, $pin, $key);
        } finally {
            if (is_resource($handle)) { fclose($handle); }
            $clean = true;
            if ($linked) { $clean = @unlink($path); }
            if ($made) { $clean = @rmdir($directory) && $clean; }
            umask($mode); Contract::require($clean, 'GO_CLEANUP_FAILED');
        }
    }
}
