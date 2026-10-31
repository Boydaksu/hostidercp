<?php
declare(strict_types=1);
// Development-only; no user-supplied source path, URL, rows or provider endpoint.
require dirname(__DIR__).'/bootstrap/autoload.php';require dirname(__DIR__).'/tests/migration-import-fixture.php';
use Hostider\Migration\Synthetic\{Sandbox,Fixture,Loader};
Sandbox::flags();$f=importFixture();
try{$loader=new Loader();$fixture=Fixture::named('baseline');$dry=$loader->dryRun($fixture,$f['sandbox']);$load=$loader->load($fixture,$f['sandbox']);$replay=$loader->load($fixture,$f['sandbox']);echo json_encode(['classification'=>'SYNTHETIC','fixture_byte_sha256'=>Fixture::BASE_SHA256,'dry_run'=>$dry,'synthetic_load'=>$load,'replay'=>$replay,'real_files_verified'=>false,'native_entities_written'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;}
finally{importCleanup();}
