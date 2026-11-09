<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
final class TestAdapter implements InboundAdapter
{
    public function __construct(private array $envelopes){if(count($envelopes)>25){throw new \InvalidArgumentException('Bounded test adapter required');}}
    public function receive(): ?array{return $this->envelopes[0]??null;}
    public function acknowledge(): void{array_shift($this->envelopes);}
}
