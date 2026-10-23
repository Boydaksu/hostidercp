<?php
declare(strict_types=1);
namespace Hostider\Commerce;
use Hostider\Core\Config;
use Hostider\Core\Database\Database;
final readonly class Services
{
    public \Hostider\Identity\IdentityService $identity;
    public \Hostider\Catalog\CatalogService $catalog;
    public \Hostider\Pricing\FxRateRepository $fx;
    public \Hostider\Pricing\QuoteService $quotes;
    public \Hostider\Finance\FinanceService $finance;
    public \Hostider\Billing\BillingService $billing;
    public \Hostider\Orders\OrderService $orders;
    public \Hostider\Payments\PaymentService $payments;
    public \Hostider\Finance\CashService $cash;
    public \Hostider\Billing\Credits $credits;
    public function __construct(public Database $db,public Config $config)
    {
        $this->identity=new \Hostider\Identity\IdentityService($db,$config);$this->catalog=new \Hostider\Catalog\CatalogService($db);$this->fx=new \Hostider\Pricing\FxRateRepository($db,$config);
        $this->quotes=new \Hostider\Pricing\QuoteService($db,$this->catalog->repository,new \Hostider\Pricing\PriceResolver($this->catalog->repository,$this->fx,new \Hostider\Pricing\ExchangeRatePolicy(3600)));
        $this->finance=new \Hostider\Finance\FinanceService($db,$config);$this->billing=new \Hostider\Billing\BillingService($db,$config,$this->finance);$this->orders=new \Hostider\Orders\OrderService($db,$this->billing,$this->quotes,$config->environment);$this->payments=new \Hostider\Payments\PaymentService($db,$config,$this->finance,$this->billing,$this->orders);$this->cash=new \Hostider\Finance\CashService($db,$config);
        $this->credits=new \Hostider\Billing\Credits($db,$this->billing,$this->finance,$this->orders,$this->fx);
    }
    public function documents(): \Hostider\Billing\DocumentService
    {
        $root=dirname(__DIR__,2);return new \Hostider\Billing\DocumentService($this->db,$this->config,new \Hostider\Billing\DocumentStore($root.'/storage/private/invoices',$root.'/public'),$this->billing->repository);
    }
}
