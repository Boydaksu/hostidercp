<?php
declare(strict_types=1);
/** Bounded fixture teardown only; never retries assertions, HTTP mutations or financial operations. */
final class FixtureDirectory
{
    public static function remove(string $directory): array
    {
        $storage=realpath(dirname(__DIR__,2).'/storage');$parent=realpath(dirname($directory));$name=basename($directory);
        if($storage===false||$parent!==$storage||!preg_match('/^(?:session-test|harness-test|service-test)-[a-f0-9]{16}$/D',$name)||is_link($directory)){throw new LogicException('Unsafe fixture cleanup target');}
        $start=hrtime(true);$attempts=0;$firstVisible=null;$warningCount=0;
        do{
            ++$attempts;clearstatcache();$files=glob($directory.'/*')?:[];
            set_error_handler(static function()use(&$warningCount): bool{++$warningCount;return true;});
            try{foreach($files as $file){if(is_file($file)&&!is_link($file)){unlink($file);}}$removed=!is_dir($directory)||rmdir($directory);}finally{restore_error_handler();}
            if($removed){$result=['os'=>PHP_OS_FAMILY,'attempts'=>$attempts,'warning_count'=>$warningCount,'first_remaining_visible_files'=>$firstVisible,'duration_ms'=>(hrtime(true)-$start)/1e6];if($attempts>1){self::record($result);}return $result;}
            clearstatcache();$firstVisible??=count(glob($directory.'/*')?:[]);usleep(20000);
        }while(hrtime(true)-$start<2_000_000_000);
        $result=['os'=>PHP_OS_FAMILY,'attempts'=>$attempts,'warning_count'=>$warningCount,'first_remaining_visible_files'=>$firstVisible,'duration_ms'=>(hrtime(true)-$start)/1e6,'failed'=>true];self::record($result);throw new RuntimeException('FIXTURE_CLEANUP_TIMEOUT '.json_encode($result,JSON_THROW_ON_ERROR));
    }
    private static function record(array $result): void
    {
        $root=dirname(__DIR__,2).'/storage/ci-diagnostics';if(!is_dir($root)){mkdir($root,0700,true);}file_put_contents($root.'/fixture-cleanup-'.bin2hex(random_bytes(8)).'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
}
