<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
$files = explode("\0", ciGit(['ls-files', '-z', 'README.md', 'docs']));
$patterns = ['/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/', '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/', '/\bgithub_pat_[A-Za-z0-9_]{50,}\b/', '/\bAKIA[A-Z0-9]{16}\b/'];
$count = 0;
foreach ($files as $file) {
    if (!is_file($file) || !in_array(pathinfo($file, PATHINFO_EXTENSION), ['md', 'json', 'txt'], true)) { continue; }
    $text = file_get_contents($file); ++$count;
    if (preg_match('//u', $text) !== 1 || str_contains($text, "\0")) { throw new RuntimeException('Invalid UTF-8/text: '.$file); }
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text)) { throw new RuntimeException('Possible credential in documentation: '.$file); }
    }
}
echo $count.' documentation files: encoding/credential patterns PASS (not a complete secret audit; historical evidence is scanned as text)'.PHP_EOL;
