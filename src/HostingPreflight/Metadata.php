<?php
declare(strict_types=1);
namespace Hostider\HostingPreflight;
final class Metadata
{
    public const QUERIES=[
        'server'=>"SELECT VERSION() AS version,@@session.sql_mode AS sql_mode,@@session.time_zone AS time_zone,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW()) AS utc_offset,@@session.character_set_connection AS charset,DATABASE() IS NOT NULL AS schema_selected",
        'engine'=>"SELECT SUPPORT,TRANSACTIONS FROM information_schema.ENGINES WHERE ENGINE='InnoDB'",
        'plugin'=>"SELECT PLUGIN_STATUS FROM information_schema.PLUGINS WHERE PLUGIN_NAME='DISKS'",
        'disks'=>"SELECT AVAILABLE FROM information_schema.DISKS LIMIT 1",
    ];
    public static function absent(): array {return ['connection'=>['UNKNOWN','NO_CONNECTION'],'server'=>null,'engine'=>null,'plugin'=>null,'disks'=>null];}
    /** Pure adapter seam: runtime uses only the four literal metadata SELECT statements above. */
    public static function inspect(\Closure $select): array
    {
        $out=['connection'=>['PASS','OBSERVED'],'server'=>null,'engine'=>null,'plugin'=>null,'disks'=>null];
        foreach(['server','engine','plugin'] as $name){try{$v=$select(self::QUERIES[$name]);$out[$name]=is_array($v)?$v:[];}catch(\Throwable){$out[$name]=null;}}
        if(($out['plugin']['PLUGIN_STATUS']??null)==='ACTIVE'){try{$v=$select(self::QUERIES['disks']);$out['disks']=is_array($v)?$v:[];}catch(\Throwable){$out['disks']=null;}}
        return $out;
    }
    public static function fromDescriptor(int $fd): array {return self::connect(PrivateFd::read($fd));}
    private static function connect(#[\SensitiveParameter] array $config): array
    {
        $keys=array_keys($config);sort($keys);if($keys!==['dsn','password','read_only','user']||$config['read_only']!==true||!is_string($config['dsn'])||strlen($config['dsn'])>512||!is_string($config['user'])||strlen($config['user'])<1||strlen($config['user'])>128||!is_string($config['password'])||strlen($config['password'])>1024||str_contains($config['password'],"\0")){throw new \InvalidArgumentException('Database config');}
        if(!str_starts_with($config['dsn'],'mysql:')){throw new \InvalidArgumentException('Database driver');}
        $parts=[];foreach(explode(';',substr($config['dsn'],6)) as $part){if(preg_match('/\A(host|port|dbname|charset)=([a-zA-Z0-9_.:\\-]+)\z/D',$part,$m)!==1||isset($parts[$m[1]])){throw new \InvalidArgumentException('Database DSN');}$parts[$m[1]]=$m[2];}
        if(!isset($parts['host'],$parts['dbname'],$parts['charset'])||$parts['charset']!=='utf8mb4'||preg_match('/\A[a-zA-Z0-9_]+\z/D',$parts['dbname'])!==1||(isset($parts['port'])&&(preg_match('/\A[0-9]+\z/D',$parts['port'])!==1||(int)$parts['port']<1||(int)$parts['port']>65535))){throw new \InvalidArgumentException('Database DSN');}
        // This initial offline installation probe intentionally has no remote-host/TLS connector.
        if(!in_array($parts['host'],['localhost','127.0.0.1','::1'],true)){return self::absent();}
        if(!class_exists(\PDO::class)||!in_array('mysql',\PDO::getAvailableDrivers(),true)){return self::absent();}
        try{
            $pdo=new \PDO($config['dsn'],$config['user'],$config['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false,\PDO::ATTR_PERSISTENT=>false,\PDO::ATTR_TIMEOUT=>2,\PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
            return self::inspect(static function(string $sql)use($pdo): array {if(!in_array($sql,self::QUERIES,true)){throw new \LogicException('Metadata allowlist');}$stmt=$pdo->query($sql);return $stmt->fetch()?:[];});
        }catch(\Throwable){$out=self::absent();$out['connection']=['UNKNOWN','ACCESS_UNAVAILABLE'];return $out;}
    }
}
