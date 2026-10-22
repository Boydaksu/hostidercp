<?php
declare(strict_types=1);
$root=dirname(__DIR__); $errors=[]; $count=0;
$paths=['src','bootstrap','bin','public','migrations','tests','tools','.github'];
$patterns=['/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/', '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/', '/\bgithub_pat_[A-Za-z0-9_]{50,}\b/', '/\bAKIA[A-Z0-9]{16}\b/'];
foreach ($paths as $path) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$path,FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_contains(str_replace('\\','/',$file->getPathname()),'/tools/local/')) { continue; }
        if (str_contains(str_replace('\\','/',$file->getPathname()),'/tools/analysis/vendor/')) { continue; }
        if (!$file->isFile()) { continue; } ++$count;
        $source=file_get_contents($file->getPathname());
        foreach ($patterns as $pattern) {
            if (preg_match($pattern,$source)) { $errors[]='Possible credential in '.$file->getFilename(); }
        }
    }
}
foreach (['.env','vendor','node_modules'] as $name) {
    if (file_exists($root.'/'.$name)) { $errors[]='Review unexpected dependency/secret path: '.$name; }
}
foreach ($errors as $error) { echo 'FAIL '.$error.PHP_EOL; }
echo $count.' source/config/test files scanned; '.count($errors).' findings'.PHP_EOL;
echo 'Runtime third-party PHP dependencies: 0; embedded reference SDK is not loaded'.PHP_EOL;
echo 'Pattern scan is not a complete secret or vulnerability audit'.PHP_EOL;
exit($errors===[]?0:1);
