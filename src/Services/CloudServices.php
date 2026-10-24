<?php
declare(strict_types=1);
namespace Hostider\Services;
use Hostider\Core\Config;
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
/** Trusted Cloud application gateway; other modules never write Services tables. */
final readonly class CloudServices
{
    public function __construct(private Database $db,private Config $config){if($config->environment!=='test'){throw new HttpError(403,'F4_TEST_ONLY');}}
    public function source(string $service,string $customer,array $states=['QUEUED']): array{return (new RegistrationEligibility($this->db,$this->config))->lock($service,$customer,'CLOUD',$states);}
    public function state(string $service,string $status,?string $operation=null): void
    {
        $this->db->requireTransaction();$this->db->execute('UPDATE svc_services SET status=?,current_operation_id=COALESCE(?,current_operation_id),version=version+1 WHERE id=?',[$status,$operation,$service]);
    }
    public function imported(string $service,string $customer): array
    {
        $row=(new Repository($this->db,$this->config))->find($service,$customer);$type=$this->db->execute('SELECT product_type FROM catalog_products WHERE id=?',[$row['product_id']])->fetchColumn();if(!$row['imported']||$row['origin_environment']!=='test'||$type!=='CLOUD'){throw new HttpError(409,'CLOUD_IMPORTED_SERVICE_REQUIRED');}return $row;
    }
}
