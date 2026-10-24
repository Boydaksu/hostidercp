<?php
declare(strict_types=1);
namespace Hostider\Domains;

/** One UTS #46 non-transitional key for both Unicode and A-label input. */
final readonly class DomainName
{
    public string $ascii;
    public function __construct(string $input)
    {
        if (!function_exists('idn_to_ascii')) {
            throw new \RuntimeException('IDNA support required');
        }
        if ($input === '' || strlen($input) > 1024 || preg_match('/[\s\x00-\x20\x7f\/\\\\:@?#%]/u', $input) !== 0) {
            throw new \InvalidArgumentException('Invalid domain name');
        }
        $info = [];
        $ascii = idn_to_ascii($input, IDNA_USE_STD3_RULES | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46, $info);
        if ($ascii === false || ($info['errors'] ?? 1) !== 0) {
            throw new \InvalidArgumentException('Invalid IDNA name');
        }
        $ascii = strtolower($ascii);
        if (str_ends_with($ascii, '.')) { $ascii = substr($ascii, 0, -1); }
        if (strlen($ascii) > 253 || !str_contains($ascii, '.')) {
            throw new \InvalidArgumentException('Fully qualified domain required');
        }
        foreach (explode('.', $ascii) as $label) {
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label)) {
                throw new \InvalidArgumentException('Invalid domain label');
            }
        }
        if (preg_match('/^[0-9.]+$/D', $ascii)) { throw new \InvalidArgumentException('IP is not a domain'); }
        $this->ascii = $ascii;
    }
    public function under(string $tld): bool
    {
        // TLD rules are explicit; there is no guessed public-suffix list.
        return str_ends_with($this->ascii, '.'.$tld) && strlen($this->ascii) > strlen($tld) + 1;
    }
}
