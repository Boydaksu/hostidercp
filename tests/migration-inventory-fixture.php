<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap/autoload.php';
use Hostider\Migration\Contract;

/** All identifiers generated here are fictional synthetic fixture names, never source inventory. */
function inventoryId(string $s): string { return Contract::hash(['SYNTHETIC_INVENTORY_ONLY', $s]); }
function inventoryName(string $s): string { return 'SYNTHETIC_'.substr(inventoryId($s), 0, 20); }
function inventoryFixture(): array
{
    $source='source-'.inventoryId('source-a');$snapshot='snapshot-'.inventoryId('snapshot-a');
    $b=['source_id'=>$source,'snapshot_id'=>$snapshot];
    return [
        ...$b,'contract'=>'hostider.relational-inventory.v1','classification'=>'SYNTHETIC_EXPORT','encoding'=>'UTF-8',
        'system'=>'WHMCS','extracted_at'=>'2026-10-29T00:00:00Z','watermark'=>'2026-10-28T23:00:00Z',
        'schema_state'=>'KNOWN','schemas'=>[
            [...$b,'name'=>inventoryName('schema-a'),'tables_state'=>'KNOWN','tables'=>[
                [...$b,'name'=>inventoryName('table-a'),'charset'=>'UTF8MB4','collation'=>'UTF8MB4_BIN',
                    'columns_state'=>'KNOWN','columns'=>[
                        [...$b,'name'=>inventoryName('column-a'),'type'=>'INTEGER','nullable'=>false,'charset'=>'NONE','collation'=>'NONE'],
                        [...$b,'name'=>inventoryName('column-b'),'type'=>'STRING','nullable'=>true,'charset'=>'UTF8MB4','collation'=>'UTF8MB4_BIN'],
                    ],
                    'indexes_state'=>'KNOWN','indexes'=>[
                        [...$b,'name'=>inventoryName('index-a'),'kind'=>'PRIMARY','columns'=>[inventoryName('column-a')]],
                        [...$b,'name'=>inventoryName('index-b'),'kind'=>'INDEX','columns'=>[inventoryName('column-b'),inventoryName('column-a')]],
                    ],
                ],
            ]],
        ],
    ];
}
function inventoryJson(array $f): string { return json_encode($f, JSON_THROW_ON_ERROR); }
function inventoryRebind(array $f,string $source,string $snapshot):array
{
    $f['source_id']=$source;$f['snapshot_id']=$snapshot;
    foreach($f['schemas'] as &$s){
        $s['source_id']=$source;$s['snapshot_id']=$snapshot;
        foreach($s['tables'] as &$t){
            $t['source_id']=$source;$t['snapshot_id']=$snapshot;
            foreach(['columns','indexes'] as $kind){
                foreach($t[$kind] as &$item){$item['source_id']=$source;$item['snapshot_id']=$snapshot;}unset($item);
            }
        }unset($t);
    }unset($s);return $f;
}
