<?php
declare(strict_types=1);
namespace Hostider\HostingPreflight;
final class Evaluator
{
    public static function evaluate(Observation $observation): Report
    {
        $r=new Report();$v=$observation->runtime;$f=$v['functions'];$e=$v['extensions'];
        $check=static function(string $code,bool $ok)use($r):void{$r->add($code,$ok?'PASS':'FAIL',$ok?'OBSERVED':'MISSING');};
        $check('PHP_VERSION',$v['version']>=80300);$check('PHP_BITS',$v['bits']===64);$check('CLI_RUNTIME',$v['sapi']==='cli');
        $check('PDO_MYSQL',($e['PDO']??false)&&($e['pdo_mysql']??false)&&$v['mysql']);
        $check('SODIUM',($e['sodium']??false)&&($f['sodium_crypto_aead_xchacha20poly1305_ietf_encrypt']??false));
        $check('MBSTRING',($e['mbstring']??false)&&($f['mb_check_encoding']??false)&&($f['mb_strlen']??false));
        $check('SESSION',($e['session']??false)&&($f['session_start']??false));
        $ok=true;foreach(Environment::CORE_FUNCTIONS as $name){$ok=$ok&&($f[$name]??false);}$check('CORE_FUNCTIONS',$ok);$check('ARGON2ID',$v['argon2id']);
        foreach(['WEBROOT_PATH','CONFIG_FILE','STORAGE_PATH','SESSION_PATH','OPEN_BASEDIR','WRITE_AND_LOCK','DISK_CAPACITY','BACKUP_HEADROOM'] as $code){$row=$observation->filesystem[$code]??['UNKNOWN','UNVERIFIED'];$r->add($code,$row[0],$row[1]);}
        $check('FILEINFO',($e['fileinfo']??false)&&$v['finfo']);$check('INTL_IDN',($e['intl']??false)&&($f['idn_to_ascii']??false));
        $ok=true;foreach(Environment::FILE_FUNCTIONS as $name){$ok=$ok&&($f[$name]??false);}$check('FILE_LOCKS',$ok);foreach(['sodium_crypto_secretstream_xchacha20poly1305_init_push','sodium_crypto_secretstream_xchacha20poly1305_push','sodium_crypto_secretstream_xchacha20poly1305_init_pull','sodium_crypto_secretstream_xchacha20poly1305_pull'] as $name){$ok=$ok&&($f[$name]??false);}$check('BACKUP_FILE_FUNCTIONS',$ok);
        $check('POSIX_BACKUP',($f['posix_geteuid']??false)&&$v['nonroot']);$check('FSYNC_BACKUP',$f['fsync']??false);$check('POSIX_DISCOVERY',$f['posix_geteuid']??false);
        $r->add('OPENSSL_OBSERVED',($e['openssl']??false)?'PASS':'NOT_APPLICABLE',($e['openssl']??false)?'OBSERVED':'UNVERIFIED');
        $web=$observation->declaredWebVersion;$r->add('WEB_PHP',$web!==null&&$web!==$v['version']?'FAIL':'UNKNOWN',$web!==null&&$web!==$v['version']?'VERSION_MISMATCH':($web===null?'UNVERIFIED':'OPERATOR_DECLARATION'));
        foreach(['CRON','MEMORY_TIMEOUT','WEB_MAPPING_TLS','DB_SQL_FEATURES','BACKUP_PRIVILEGES','PITR_OFFSITE'] as $code){$r->add($code,'UNKNOWN','UNVERIFIED');}
        $db=$observation->database;$r->add('DB_CONNECTION',$db['connection'][0],$db['connection'][1]);$server=$db['server'];$r->add('DB_SCHEMA',is_array($server)&&isset($server['schema_selected'])?((int)$server['schema_selected']===1?'PASS':'FAIL'):'UNKNOWN',is_array($server)&&isset($server['schema_selected'])?((int)$server['schema_selected']===1?'OBSERVED':'MISSING'):'UNVERIFIED');
        if(!is_array($server)||!isset($server['version'])){$r->add('DB_MARIADB','UNKNOWN','UNVERIFIED');$r->add('DB_CHARSET','UNKNOWN','UNVERIFIED');$r->add('DB_SQL_MODE','UNKNOWN','UNVERIFIED');$r->add('DB_UTC','UNKNOWN','UNVERIFIED');}
        else{$s=(string)$server['version'];$ok=preg_match('/\A(?:5\.5\.5-)?([0-9]+\.[0-9]+\.[0-9]+)-MariaDB(?:-|\z)/D',$s,$m)===1&&version_compare($m[1],'10.11.0','>=');$r->add('DB_MARIADB',$ok?'PASS':'FAIL',$ok?'OBSERVED':'VERSION_MISMATCH');$check('DB_CHARSET',($server['charset']??null)==='utf8mb4');$modes=explode(',',(string)($server['sql_mode']??''));$ok=in_array('STRICT_ALL_TABLES',$modes,true)&&in_array('ERROR_FOR_DIVISION_BY_ZERO',$modes,true)&&in_array('NO_ENGINE_SUBSTITUTION',$modes,true);$r->add('DB_SQL_MODE',$ok?'PASS':'UNKNOWN',$ok?'OBSERVED':'UNVERIFIED');$ok=isset($server['utc_offset'])&&(string)$server['utc_offset']==='0';$r->add('DB_UTC',$ok?'PASS':'UNKNOWN',$ok?'OBSERVED':'UNVERIFIED');}
        $engine=$db['engine'];if($engine===null){$r->add('DB_INNODB','UNKNOWN','UNVERIFIED');}else{$check('DB_INNODB',in_array($engine['SUPPORT']??null,['YES','DEFAULT'],true)&&($engine['TRANSACTIONS']??null)==='YES');}
        if($db['connection'][0]!=='PASS'||$db['plugin']===null){$r->add('DB_DISKS','UNKNOWN','UNVERIFIED');}elseif(($db['plugin']['PLUGIN_STATUS']??null)!=='ACTIVE'){$r->add('DB_DISKS','FAIL','MISSING');}elseif($db['disks']===null){$r->add('DB_DISKS','UNKNOWN','DENIED');}else{$r->add('DB_DISKS',isset($db['disks']['AVAILABLE'])?'PASS':'UNKNOWN',isset($db['disks']['AVAILABLE'])?'OBSERVED':'UNVERIFIED');}
        // Force the inventory invariant before returning a report to any caller.
        $r->jsonSerialize();return $r;
    }
}
