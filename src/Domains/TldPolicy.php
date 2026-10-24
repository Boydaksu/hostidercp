<?php
declare(strict_types=1);
namespace Hostider\Domains;
use Hostider\Core\Http\HttpError;

/** Internal normalized policy, never a claimed vendor response schema. */
final readonly class TldPolicy
{
    public const ACTIONS = ['availability','register','renew','initiateTransfer','status','nameservers','dns','epp','lock','privacy'];
    public const CONTACT_FIELDS = ['name','organization','email','phone','address','city','region','postal_code','country','identity_number','tax_number'];
    public function __construct(public array $actions, public array $requiredFields, public ?int $graceDays, public ?int $redemptionDays)
    {
        if (!array_is_list($actions) || !array_is_list($requiredFields) || array_diff($actions,self::ACTIONS) !== [] || array_diff($requiredFields,self::CONTACT_FIELDS) !== [] || count(array_unique($actions)) !== count($actions) || count(array_unique($requiredFields)) !== count($requiredFields)) {
            throw new \InvalidArgumentException('Invalid TLD capability policy');
        }
        foreach ([$graceDays,$redemptionDays] as $days) {
            if ($days !== null && ($days < 0 || $days > 3650)) { throw new \InvalidArgumentException('Invalid TLD period'); }
        }
    }
    public function requireAction(string $action): void
    {
        if (!in_array($action,$this->actions,true)) { throw new HttpError(422,'DOMAIN_CAPABILITY_UNSUPPORTED'); }
    }
    public function validateContact(array $contact): void
    {
        if (array_diff(array_keys($contact),self::CONTACT_FIELDS) !== []) { throw new HttpError(422,'DOMAIN_CONTACT_FIELDS'); }
        foreach ($this->requiredFields as $field) {
            if (!isset($contact[$field]) || !is_string($contact[$field]) || trim($contact[$field]) === '') { throw new HttpError(422,'DOMAIN_CONTACT_REQUIRED'); }
        }
        foreach ($contact as $value) {
            if (!is_string($value) || strlen($value) > 512 || preg_match('/[\x00-\x1f\x7f]/',$value)) { throw new HttpError(422,'DOMAIN_CONTACT_INVALID'); }
        }
        if (isset($contact['country']) && !preg_match('/^[A-Z]{2}$/D',$contact['country'])) { throw new HttpError(422,'DOMAIN_CONTACT_INVALID'); }
        if (isset($contact['email']) && !filter_var($contact['email'],FILTER_VALIDATE_EMAIL)) { throw new HttpError(422,'DOMAIN_CONTACT_INVALID'); }
    }
    public function snapshot(): array
    {
        return ['actions'=>$this->actions,'required_fields'=>$this->requiredFields,'grace_days'=>$this->graceDays,'redemption_days'=>$this->redemptionDays,'live_validated'=>false];
    }
    public static function fromSnapshot(array $data): self
    {
        return new self($data['actions'],$data['required_fields'],$data['grace_days'],$data['redemption_days']);
    }
}
