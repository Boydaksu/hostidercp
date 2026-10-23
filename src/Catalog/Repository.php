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
        $item=$this->find('item',$id,$lock);
        if($item['kind']==='ADDON'){
            $product=$this->db->execute('SELECT p.*,p.addon_id id FROM catalog_addon_policies p JOIN catalog_addons a ON a.id=p.addon_id WHERE p.addon_id=? AND a.standalone=TRUE'.($lock?' FOR UPDATE':''),[$id])->fetch();
            if(!$product){throw new HttpError(404,'STANDALONE_ADDON_POLICY_NOT_FOUND');}$product['product_type']='ADDON';
        }else{$product=$this->find('product',$id,$lock);}
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
        $where=$activeOnly?" WHERE p.sale_status='ACTIVE'":'';
        $fields='p.category_id,p.sale_status,p.tax_id,p.pricing_strategy,p.source_currency,p.activation_policy,p.refund_policy,i.name,i.version,c.name category_name';
        return $this->db->execute('SELECT p.id,p.product_type,'.$fields.' FROM catalog_products p JOIN catalog_items i ON i.id=p.id JOIN catalog_categories c ON c.id=p.category_id'.$where.' UNION ALL SELECT p.addon_id id,\'ADDON\' product_type,'.$fields.' FROM catalog_addon_policies p JOIN catalog_addons a ON a.id=p.addon_id AND a.standalone=TRUE JOIN catalog_items i ON i.id=p.addon_id JOIN catalog_categories c ON c.id=p.category_id'.$where.' ORDER BY name,id LIMIT 50 OFFSET '.$offset)->fetchAll();
    }
    public function configuration(string $product): array
    {
        $this->product($product);
        return [
            'variants'=>$this->db->execute('SELECT v.id,i.name FROM catalog_variants v JOIN catalog_items i ON i.id=v.id WHERE v.product_id=? ORDER BY i.id',[$product])->fetchAll(),
            'groups'=>$this->db->execute('SELECT * FROM catalog_option_groups WHERE product_id=? ORDER BY id',[$product])->fetchAll(),
            'options'=>$this->db->execute('SELECT v.*,i.name FROM catalog_option_values v JOIN catalog_items i ON i.id=v.id JOIN catalog_option_groups g ON g.id=v.group_id WHERE g.product_id=? ORDER BY v.id',[$product])->fetchAll(),
            'addons'=>$this->db->execute('SELECT a.*,i.name FROM catalog_addons a JOIN catalog_items i ON i.id=a.id JOIN catalog_product_addons p ON p.addon_id=a.id WHERE p.product_id=? ORDER BY a.id',[$product])->fetchAll(),
            'addon_cycles'=>$this->db->execute('SELECT DISTINCT a.addon_id,c.id,c.name,c.kind FROM catalog_product_addons a JOIN catalog_prices p ON p.item_id=a.addon_id JOIN catalog_cycles c ON c.id=p.cycle_id WHERE a.product_id=? ORDER BY a.addon_id,c.id',[$product])->fetchAll(),
            'cycles'=>$this->db->execute('SELECT DISTINCT c.* FROM catalog_cycles c JOIN catalog_prices p ON p.cycle_id=c.id LEFT JOIN catalog_variants v ON v.id=p.item_id WHERE p.item_id=? OR v.product_id=? ORDER BY c.id',[$product,$product])->fetchAll(),
            'base_cycles'=>array_column($this->db->execute('SELECT DISTINCT cycle_id FROM catalog_prices WHERE item_id=?',[$product])->fetchAll(),'cycle_id'),
        ];
    }
}
