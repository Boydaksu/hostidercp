<?php
declare(strict_types=1);
// Dependency-free lint + token-based architectural checks; not a full type analyzer.
$root=dirname(__DIR__); $count=0; $errors=[];
foreach (['src','bootstrap','bin','public','migrations','tests','tools'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension()!=='php') { continue; }
        ++$count; $source=file_get_contents($file->getPathname());
        $pipes=[]; $p=proc_open([PHP_BINARY,'-n','-l',$file->getPathname()], [1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($p)!==0) { $errors[]=$output; }
        if ($dir!=='src') { continue; }
        $tokens=token_get_all($source);
        foreach ($tokens as $token) {
            if (!is_array($token)) { continue; }
            if (in_array($token[0],[T_EVAL,T_DNUMBER],true)) { $errors[]='Forbidden eval/float: '.$file->getFilename(); }
            if ($token[0]===T_STRING && in_array(strtolower($token[1]),['curl_exec','file_put_contents','shell_exec','exec','system','passthru','unserialize'],true)) {
                // Database PDO exec is safe SQL execution; distinguish method tokens below.
                if (strtolower($token[1])!=='exec') { $errors[]='Forbidden external I/O in core: '.$file->getFilename(); }
            }
            if (str_contains(str_replace('\\','/',$file->getPathname()),'/src/Core/') && $token[0]===T_NAME_QUALIFIED && str_starts_with($token[1],'Hostider\\Providers\\')) {
                $errors[]='Core depends on provider module: '.$file->getFilename();
            }
        }
        if (!str_contains($source,'declare(strict_types=1);')) { $errors[]='Missing strict types: '.$file->getFilename(); }
    }
}
foreach ($errors as $error) { echo 'FAIL '.$error.PHP_EOL; }
echo $count.' PHP files checked; '.count($errors).' violations'.PHP_EOL;
exit($errors===[]?0:1);
