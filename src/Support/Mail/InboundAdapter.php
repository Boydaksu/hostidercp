<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
interface InboundAdapter {public function receive(): ?array;public function acknowledge(): void;}
