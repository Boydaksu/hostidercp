<?php
declare(strict_types=1);
namespace Hostider\CloudCosts;
use Hostider\Core\Database\Database;
/** Only local SQL, after Database has rolled back. Never wraps provider calls or unknown commits. */
final class LocalTransactions
{
    private static array $counts=[];
    public static function counters(): array{return self::$counts;}
    public static function run(Database $db,\Closure $work): mixed
    {
        if($db->pdo->inTransaction()){throw new \LogicException('Nested cost transaction forbidden');}
        for($attempt=0;;++$attempt){
            // One transaction only: do not change the session isolation used by F3/F4-D.
            $db->execute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            try{return $db->transaction($work);}
            catch(\PDOException $e){
                $code=(int)($e->errorInfo[1]??0);
                if(!in_array($code,[1205,1213],true)||$attempt>=2){throw $e;}
                self::$counts[$code]=(self::$counts[$code]??0)+1;
                usleep(($attempt+1)*5000);
            }
        }
    }
}
