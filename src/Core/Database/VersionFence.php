<?php
declare(strict_types=1);
namespace Hostider\Core\Database;
/** Local application aggregate versions; writers acquire this before their business rows. */
final readonly class VersionFence
{
    public function __construct(private Database $db) {}
    public function read(string $aggregate,bool $exclusive=false): string
    {
        $this->db->requireTransaction();$row=$this->db->execute('SELECT version FROM commerce_state_versions WHERE aggregate_name=?'.($exclusive?' FOR UPDATE':' LOCK IN SHARE MODE'),[$aggregate])->fetchColumn();if($row===false){throw new \LogicException('Unknown aggregate fence');}return (string)$row;
    }
    public function advance(string $aggregate): void
    {
        $this->read($aggregate,true);$this->db->execute('UPDATE commerce_state_versions SET version=version+1 WHERE aggregate_name=?',[$aggregate]);
    }
}
