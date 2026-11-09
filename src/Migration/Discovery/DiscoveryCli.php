<?php
declare(strict_types=1);
namespace Hostider\Migration\Discovery;
use Hostider\Migration\Contract;

final class DiscoveryCli
{
    private static function descriptor(int $fd, bool $key): string
    {
        Contract::require($fd >= 3 && $fd <= 64 && function_exists('posix_geteuid'), 'GO_DESCRIPTOR');
        $h = @fopen('php://fd/'.$fd, 'rb'); Contract::require(is_resource($h), 'GO_DESCRIPTOR');
        try {
            $s = fstat($h); $max = $key ? 32 : 4096;
            Contract::require($s !== false && ($s['mode'] & 0170000) === 0100000 && ($s['mode'] & 0777) === 0600
                && $s['uid'] === posix_geteuid() && $s['size'] > 0 && $s['size'] <= $max && ftell($h) === 0, 'GO_DESCRIPTOR_PERMISSIONS');
            $data = stream_get_contents($h, $max+1);
            Contract::require(is_string($data) && strlen($data) === $s['size'] && (!$key || strlen($data) === 32), 'GO_DESCRIPTOR_SIZE'); return $data;
        } finally { fclose($h); }
    }
    public static function main(array $argv): int
    {
        try {
            Contract::require(PHP_SAPI === 'cli', 'GO_CLI_ONLY');
            $o = [];
            foreach (array_slice($argv, 1) as $arg) {
                Contract::require(is_string($arg) && preg_match('/^--(ack-owner-approved-read-only-discovery|config-fd|key-fd|private-dir)(?:=(.+))?$/D', $arg, $m) === 1
                    && !isset($o[$m[1]]), 'GO_ARGUMENTS');
                $o[$m[1]] = $m[2] ?? true;
            }
            Contract::fields($o, ['ack-owner-approved-read-only-discovery', 'config-fd', 'key-fd', 'private-dir']);
            Contract::require($o['ack-owner-approved-read-only-discovery'] === true, 'GO_MANUAL_GATE');
            Contract::require(is_string($o['config-fd']) && ctype_digit($o['config-fd']) && is_string($o['key-fd']) && ctype_digit($o['key-fd'])
                && $o['config-fd'] !== $o['key-fd'] && is_string($o['private-dir']), 'GO_ARGUMENTS');
            PrivateExport::assertRoot($o['private-dir']);
            $config = get_object_vars(StrictJson::decode(self::descriptor((int)$o['config-fd'], false)));
            Contract::fields($config, ['port', 'account', 'password', 'schema', 'source_id', 'snapshot_id']);
            Contract::require(is_int($config['port']) && is_string($config['account']) && is_string($config['password'])
                && is_string($config['schema']) && is_string($config['source_id']) && is_string($config['snapshot_id']), 'GO_CONFIG');
            $key = self::descriptor((int)$o['key-fd'], true);
            $s = MetadataSession::connect($config['port'], $config['account'], $config['password'], MetadataSession::PURPOSE);
            $report = (new Collector())->collect($s, $config['schema'], $config['source_id'], $config['snapshot_id'], $o['private-dir'], $key);
        } catch (\Throwable) { $report = Collector::blocked('GO_CLI_DENIED'); }
        echo json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL;
        return $report['status'] === 'BLOCKED' ? 1 : 0;
    }
}
