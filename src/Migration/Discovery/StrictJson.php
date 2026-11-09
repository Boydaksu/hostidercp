<?php
declare(strict_types=1);
namespace Hostider\Migration\Discovery;
use Hostider\Migration\Contract;

/** Bounded UTF-8 JSON. Duplicate keys are checked after JSON escape decoding. */
final class StrictJson
{
    public const MAX_BYTES = 131072;
    private int $at = 0;
    private int $nodes = 0;
    private function __construct(private readonly string $json) {}
    public static function decode(#[\SensitiveParameter] string $json): \stdClass
    {
        Contract::require(strlen($json) > 0 && strlen($json) <= self::MAX_BYTES, 'P2_SIZE');
        Contract::require(preg_match('//u', $json) === 1 && !str_starts_with($json, "\xEF\xBB\xBF"), 'P2_ENCODING');
        // The native parser validates grammar, surrogate pairs and nesting first.
        $value = json_decode($json, false, 16, JSON_THROW_ON_ERROR);
        Contract::require($value instanceof \stdClass, 'P2_ROOT_OBJECT');
        $scanner = new self($json); $scanner->value(0); $scanner->space();
        Contract::require($scanner->at === strlen($json), 'P2_JSON');
        return $value;
    }
    private function space(): void
    {
        while (isset($this->json[$this->at]) && str_contains(" \t\r\n", $this->json[$this->at])) { ++$this->at; }
    }
    private function string(): string
    {
        $start = $this->at++;
        while (isset($this->json[$this->at])) {
            $c = $this->json[$this->at++];
            if ($c === '\\') { ++$this->at; continue; }
            if ($c === '"') { break; }
        }
        $s = json_decode(substr($this->json, $start, $this->at-$start), false, 2, JSON_THROW_ON_ERROR);
        Contract::require(is_string($s) && strlen($s) <= 128, 'P2_STRING_LIMIT');
        return $s;
    }
    private function value(int $depth): void
    {
        Contract::require($depth < 16 && ++$this->nodes <= 8192, 'P2_COMPLEXITY');
        $this->space(); $c = $this->json[$this->at];
        if ($c === '{' || $c === '[') {
            $object = $c === '{'; $end = $object ? '}' : ']'; ++$this->at; $this->space(); $seen = [];
            if ($this->json[$this->at] === $end) { ++$this->at; return; }
            while (true) {
                if ($object) {
                    $name = $this->string();
                    Contract::require(!array_key_exists($name, $seen), 'P2_DUPLICATE_KEY'); $seen[$name] = true;
                    $this->space(); ++$this->at; // colon, already checked by native JSON parser
                }
                $this->value($depth+1); $this->space();
                if ($this->json[$this->at++] === $end) { break; }
                $this->space(); // comma, already checked by native JSON parser
            }
        } elseif ($c === '"') { $this->string(); }
        else {
            while (isset($this->json[$this->at]) && !str_contains(",]} \t\r\n", $this->json[$this->at])) { ++$this->at; }
        }
    }
}
