<?php
declare(strict_types=1);
namespace Hostider\Catalog;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
final readonly class Repository
{
    public function __construct(public Database $db) {}
    private function table(string $kind): string
    {
        return match($kind) {'tax'=>'catalog_tax_rules','category'=>'catalog_categories','cycle'=>'catalog_cycles','item'=>'catalog_items','product'=>'catalog_products','variant'=>'catalog_variants','group'=>'catalog_option_groups','option'=>'catalog_option_values','addon'=>'catalog_addons','promotion'=>'catalog_promotions',default=>throw new \InvalidArgumentException('Unknown catalog entity')};
    }
    public function find(string $kind,string $id,bool $lock=false): array
    {
        if ($lock) { $this->db->requireTransaction(); }
        $row=$this->db->execute('SELECT * FROM '.$this->table($kind).' WHERE id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();
        if (!$row) { throw new HttpError(404,'CATALOG_ENTITY_NOT_FOUND'); } return $row;
    }
    public function insert(string $kind,array $values): void
    {
        $this->db->requireTransaction();
        // Keys come exclusively from application service's literal column lists.
        $columns=array_keys($values);
        foreach($columns as $column) { if(!preg_match('/^[a-z_]+$/D',$column)){throw new \InvalidArgumentException('Invalid column');} }
        $this->db->execute('INSERT INTO '.$this->table($kind).'('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')',array_values($values));
    }
    public function product(string $id,bool $lock=false): array
    {
        $product=$this->find('product',$id,$lock); $item=$this->find('item',$id,$lock);
        $category=$this->find('category',$product['category_id'],$lock);
        $taxId=$product['tax_id']??$category['tax_id'];
        return $product+$item+['tax'=>$taxId===null?null:$this->find('tax',$taxId,$lock)];
    }
    public function price(string $item,string $cycle,string $currency): ?array
    {
        $row=$this->db->execute('SELECT * FROM catalog_prices WHERE item_id=? AND cycle_id=? AND currency=? ORDER BY version DESC LIMIT 1',[$item,$cycle,$currency])->fetch(); return $row?:null;
    }
    public function listProducts(bool $activeOnly=true,int $offset=0): array
    {
        if($offset<0 || $offset>1000000){throw new HttpError(422,'INVALID_OFFSET');}
        return $this->db->execute('SELECT p.*,i.name,i.version,c.name category_name FROM catalog_products p JOIN catalog_items i ON i.id=p.id JOIN catalog_categories c ON c.id=p.category_id'.($activeOnly?" WHERE p.sale_status='ACTIVE'":'').' ORDER BY i.created_at,i.id LIMIT 50 OFFSET '.$offset)->fetchAll();
    }
    public function configuration(string $product): array
    {
        $this->find('product',$product);
        return [
            'variants'=>$this->db->execute('SELECT v.id,i.name FROM catalog_variants v JOIN catalog_items i ON i.id=v.id WHERE v.product_id=? ORDER BY i.id',[$product])->fetchAll(),
            'groups'=>$this->db->execute('SELECT * FROM catalog_option_groups WHERE product_id=? ORDER BY id',[$product])->fetchAll(),
            'options'=>$this->db->execute('SELECT v.*,i.name FROM catalog_option_values v JOIN catalog_items i ON i.id=v.id JOIN catalog_option_groups g ON g.id=v.group_id WHERE g.product_id=? ORDER BY v.id',[$product])->fetchAll(),
            'addons'=>$this->db->execute('SELECT a.*,i.name FROM catalog_addons a JOIN catalog_items i ON i.id=a.id JOIN catalog_product_addons p ON p.addon_id=a.id WHERE p.product_id=? ORDER BY a.id',[$product])->fetchAll(),
            'cycles'=>$this->db->execute('SELECT DISTINCT c.* FROM catalog_cycles c JOIN catalog_prices p ON p.cycle_id=c.id WHERE p.item_id=? ORDER BY c.id',[$product])->fetchAll(),
        ];
    }
}
