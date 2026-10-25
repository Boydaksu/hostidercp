<?php
declare(strict_types=1);
namespace Hostider\Services;

use Hostider\Core\{CanonicalJson,Config};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;

/** Services owns period writes and the immutable renewal finance binding. Test/mock only. */
final readonly class DomainPeriods
{
    public function __construct(private Database $db, private Config $config)
    {
        if ($config->environment !== 'test') { throw new HttpError(403, 'F4_TEST_ONLY'); }
    }

    public function renewal(string $serviceId, string $customer, string $orderId, string $periodId, int $years): array
    {
        $this->db->requireTransaction();
        $service = (new Repository($this->db, $this->config))->find($serviceId, $customer);
        $renewal = $this->db->execute('SELECT * FROM svc_renewals WHERE service_id=? AND customer_id=? AND order_id=? AND period_id=? FOR UPDATE', [$serviceId, $customer, $orderId, $periodId])->fetch();
        $period = $this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE', [$periodId])->fetch();
        $previous = $this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE', [$service['current_period_id']])->fetch();
        $order = $this->db->execute('SELECT * FROM orders WHERE id=? AND customer_id=? FOR UPDATE', [$orderId, $customer])->fetch();
        if (!$renewal || !$period || !$previous || !$order || $orderId === $service['order_id'] || $this->config->environment !== 'test'
            || $service['origin_environment'] !== 'test' || $service['imported'] || $order['origin_environment'] !== 'test'
            || $order['status'] !== 'READY_FOR_PROVISIONING' || $order['refund_status'] !== 'NONE'
            || !in_array($renewal['status'], ['AWAITING_PAYMENT','PENDING_PROVIDER','UNKNOWN'], true)
            || !in_array($period['status'], ['AWAITING_PAYMENT','PENDING_PROVIDER','UNKNOWN'], true)
            || $period['service_id'] !== $serviceId || $period['customer_id'] !== $customer
            || $period['billing_request_id'] !== $order['payment_request_id']
            || (int)$previous['generation'] + 1 !== (int)$period['generation']
            || (int)$renewal['generation'] !== (int)$period['generation'] || $previous['status'] !== 'VERIFIED' || $previous['ends_at'] === null) {
            throw new HttpError(409, 'DOMAIN_RENEWAL_SOURCE_BINDING');
        }
        $items = $this->db->execute('SELECT * FROM order_items WHERE order_id=? FOR UPDATE', [$orderId])->fetchAll();
        $price = $this->db->execute('SELECT * FROM svc_prices WHERE id=?', [$period['price_id']])->fetch();
        $bill = $this->db->execute('SELECT r.*,s.status,s.refund_status FROM billing_requests r JOIN billing_request_states s ON s.request_id=r.id WHERE r.id=? FOR UPDATE', [$order['payment_request_id']])->fetch();
        if (count($items) !== 1 || !$price || !$bill) { throw new HttpError(409, 'DOMAIN_RENEWAL_SOURCE_BINDING'); }
        $item = $items[0];
        $line = json_decode($item['snapshot'], true, 64, JSON_THROW_ON_ERROR);
        $cycle = json_decode($period['cycle_snapshot'], true, 32, JSON_THROW_ON_ERROR);
        $type = $this->db->execute('SELECT product_type FROM catalog_products WHERE id=?', [$item['product_id']])->fetchColumn();
        if ($type !== 'DOMAIN' || $item['product_id'] !== $service['product_id'] || ($line['quantity'] ?? null) !== 1
            || ($line['activation_policy'] ?? null) !== 'AUTO' || ($cycle['kind'] ?? null) !== 'MONTHS' || ($cycle['interval_count'] ?? null) !== $years * 12
            || CanonicalJson::encode($line['cycle']) !== CanonicalJson::encode($cycle)
            || CanonicalJson::fromJson($price['snapshot']) !== CanonicalJson::encode($line)
            || $price['service_id'] !== $serviceId || $price['customer_id'] !== $customer
            || $bill['order_id'] !== $orderId || $bill['customer_id'] !== $customer || $bill['currency'] !== $price['currency']
            || (string)$bill['total_minor'] !== (string)$price['total_minor'] || (string)$line['total_minor'] !== (string)$price['total_minor']
            || (string)$line['net_minor'] !== (string)$price['net_minor'] || (string)$line['tax_minor'] !== (string)$price['tax_minor']
            || $bill['status'] !== 'PAID' || $bill['refund_status'] !== 'NONE') {
            throw new HttpError(409, 'DOMAIN_RENEWAL_SOURCE_BINDING');
        }
        return compact('renewal', 'period', 'previous', 'price', 'service');
    }

    public function queued(string $periodId, string $operation): void
    {
        $this->db->requireTransaction();
        $this->db->execute("UPDATE svc_periods SET status='PENDING_PROVIDER',version=version+1 WHERE id=?", [$periodId]);
        $this->db->execute("UPDATE svc_renewals SET status='PENDING_PROVIDER',operation_id=? WHERE period_id=?", [$operation, $periodId]);
        $this->db->execute('UPDATE svc_services SET current_operation_id=?,version=version+1 WHERE id=(SELECT service_id FROM svc_periods WHERE id=?)', [$operation, $periodId]);
    }

    public function serviceState(string $service, string $status, ?string $operation = null): void
    {
        $this->db->requireTransaction();
        $this->db->execute('UPDATE svc_services SET status=?,current_operation_id=COALESCE(?,current_operation_id),version=version+1 WHERE id=?', [$status, $operation, $service]);
    }

    public function renewalState(string $period, string $status): void
    {
        $this->db->requireTransaction();
        $this->db->execute('UPDATE svc_renewals SET status=? WHERE period_id=?', [$status, $period]);
        $this->db->execute("UPDATE svc_periods SET status=?,version=version+1 WHERE id=? AND status<>'VERIFIED'", [$status, $period]);
    }

    public function complete(array $op, array $service, array $domain, int $years, bool $funded): string
    {
        $this->db->requireTransaction();
        $utc = new \DateTimeZone('UTC');
        if ($op['kind'] === 'RENEW') {
            $renewal = $this->db->execute('SELECT * FROM domain_renewals WHERE operation_id=? FOR UPDATE', [$op['id']])->fetch();
            if (!$renewal || $renewal['status'] === 'VERIFIED' || !$domain['expires_at']) { throw new HttpError(409, 'DOMAIN_RENEWAL_PERIOD_CONFLICT'); }
            $period = $this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE', [$renewal['period_id']])->fetch();
            $previous = $this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE', [$service['current_period_id']])->fetch();
            if (!$period || !$previous || $previous['ends_at'] !== $domain['expires_at'] || (int)$period['generation'] !== (int)$previous['generation']+1 || $period['status'] === 'VERIFIED') { throw new HttpError(409, 'DOMAIN_RENEWAL_PERIOD_CONFLICT'); }
            $start = $domain['expires_at'];
            $years = (int)$renewal['term_years'];
            $this->db->execute("UPDATE svc_renewals SET status='VERIFIED' WHERE period_id=?", [$period['id']]);
        } else {
            $period = $this->db->execute('SELECT * FROM svc_periods WHERE id=? FOR UPDATE', [$service['current_period_id']])->fetch();
            $start = (new \DateTimeImmutable('now', $utc))->format('Y-m-d H:i:s.u');
        }
        if (!$period || $period['status'] === 'VERIFIED') { throw new HttpError(409, 'DOMAIN_PERIOD_CONFLICT'); }
        $end = (new \DateTimeImmutable($start, $utc))->add(new \DateInterval('P'.$years.'Y'))->format('Y-m-d H:i:s.u');
        $this->db->execute("UPDATE svc_periods SET starts_at=?,ends_at=?,status='VERIFIED',version=version+1 WHERE id=?", [$start, $end, $period['id']]);
        $this->db->execute('UPDATE svc_services SET status=?,current_period_id=?,current_price_id=?,version=version+1 WHERE id=?', [$funded?'ACTIVE':'SUSPENDED', $period['id'], $period['price_id'], $service['id']]);
        return $end;
    }
}
