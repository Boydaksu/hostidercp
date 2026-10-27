<?php
declare(strict_types=1);
namespace Hostider\Migration\Reconciliation;
use Hostider\Migration\Contract;

/** Aggregate evidence only. Never a native client authorization/projection. */
final readonly class Report implements \JsonSerializable
{
    public function __construct(public array $content,public ?array $attestation=null) {}
    public function attest(string $reviewerHash,string $time): self
    {
        Contract::sha($reviewerHash);Contract::timestamp($time);
        return new self($this->content,['reviewer_sha256'=>$reviewerHash,'reviewed_at'=>$time]);
    }
    public function jsonSerialize(): array
    {
        return ['content'=>$this->content,'fingerprint'=>Contract::hash($this->content),'attestation'=>$this->attestation];
    }
}
