<?php declare(strict_types=1);

namespace DaveRandom\LibDNS\Decoding;

use DaveRandom\LibDNS\Records\ResourceData;

final class ResourceDataDecoder
{
    const DECODERS = [
        ResourceData\A::TYPE_ID => 'decodeA', /** @uses decodeA */
        ResourceData\AAAA::TYPE_ID => 'decodeAAAA', /** @uses decodeAAAA */
        ResourceData\CNAME::TYPE_ID => 'decodeCNAME', /** @uses decodeCNAME */
        ResourceData\DNAME::TYPE_ID => 'decodeDNAME', /** @uses decodeDNAME */
        ResourceData\HINFO::TYPE_ID => 'decodeHINFO', /** @uses decodeHINFO */
        ResourceData\MB::TYPE_ID => 'decodeMB', /** @uses decodeMB */
        ResourceData\MD::TYPE_ID => 'decodeMD', /** @uses decodeMD */
        ResourceData\MF::TYPE_ID => 'decodeMF', /** @uses decodeMF */
        ResourceData\MG::TYPE_ID => 'decodeMG', /** @uses decodeMG */
        ResourceData\MINFO::TYPE_ID => 'decodeMINFO', /** @uses decodeMINFO */
        ResourceData\MR::TYPE_ID => 'decodeMR', /** @uses decodeMR */
        ResourceData\MX::TYPE_ID => 'decodeMX', /** @uses decodeMX */
        ResourceData\NS::TYPE_ID => 'decodeNS', /** @uses decodeNS */
        ResourceData\NULLRecord::TYPE_ID => 'decodeNULL', /** @uses decodeNULL */
        ResourceData\PTR::TYPE_ID => 'decodePTR', /** @uses decodePTR */
        ResourceData\SOA::TYPE_ID => 'decodeSOA', /** @uses decodeSOA */
        ResourceData\TXT::TYPE_ID => 'decodeTXT', /** @uses decodeTXT */
        ResourceData\WKS::TYPE_ID => 'decodeWKS', /** @uses decodeWKS */
    ];

    private function decodeA(DecodingContext $ctx): ResourceData\A
    {
        return new ResourceData\A(\DaveRandom\LibDNS\decode_ipv4address($ctx));
    }

    private function decodeAAAA(DecodingContext $ctx): ResourceData\AAAA
    {
        return new ResourceData\AAAA(\DaveRandom\LibDNS\decode_ipv6address($ctx));
    }

    private function decodeCNAME(DecodingContext $ctx): ResourceData\CNAME
    {
        return new ResourceData\CNAME(\DaveRandom\LibDNS\decode_domain_name($ctx));
    }

    private function decodeDNAME(DecodingContext $ctx): ResourceData\DNAME
    {
        return new ResourceData\DNAME(\DaveRandom\LibDNS\decode_domain_name($ctx));
    }

    private function decodeHINFO(DecodingContext $ctx): ResourceData\HINFO
    {
        $cpu = \DaveRandom\LibDNS\decode_character_data($ctx);
        $os = \DaveRandom\LibDNS\decode_character_data($ctx);

        return new ResourceData\HINFO($cpu, $os);
    }

    private function decodeMB(DecodingContext $ctx): ResourceData\MB
    {
        return new ResourceData\MB(\DaveRandom\LibDNS\decode_domain_name($ctx));
    }

    private function decodeMD(DecodingContext $ctx): ResourceData\MD
    {
        return new ResourceData\MD(\DaveRandom\LibDNS\decode_domain_name($ctx));
    }

    private function decodeMF(DecodingContext $ctx): ResourceData\MF
    {
        return new ResourceData\MF(\DaveRandom\LibDNS\decode_domain_name($ctx));
    }

    private function decodeMG(DecodingContext $ctx): ResourceData\MG
    {
        return new ResourceData\MG(\DaveRandom\LibDNS\decode_domain_name($ctx));
    }

    private function decodeMINFO(DecodingContext $ctx): ResourceData\MINFO
    {
        $responsibleMailbox = \DaveRandom\LibDNS\decode_character_data($ctx);
        $errorMailbox = \DaveRandom\LibDNS\decode_character_data($ctx);

        return new ResourceData\MINFO($responsibleMailbox, $errorMailbox);
    }

    private function decodeMR(DecodingContext $ctx): ResourceData\MR
    {
        return new ResourceData\MR(\DaveRandom\LibDNS\decode_domain_name($ctx));
    }

    private function decodeMX(DecodingContext $ctx): ResourceData\MX
    {
        $preference = $ctx->unpack('n', 2)[1];
        $exchange = \DaveRandom\LibDNS\decode_domain_name($ctx);

        return new ResourceData\MX($preference, $exchange);
    }

    private function decodeNS(DecodingContext $ctx): ResourceData\NS
    {
        return new ResourceData\NS(\DaveRandom\LibDNS\decode_domain_name($ctx));
    }

    private function decodeNULL(DecodingContext $ctx, int $length): ResourceData\NULLRecord
    {
        return new ResourceData\NULLRecord($ctx->unpack("a{$length}", $length)[1]);
    }

    private function decodePTR(DecodingContext $ctx): ResourceData\PTR
    {
        return new ResourceData\PTR(\DaveRandom\LibDNS\decode_domain_name($ctx));
    }

    private function decodeSOA(DecodingContext $ctx): ResourceData\SOA
    {
        $masterServerName = \DaveRandom\LibDNS\decode_domain_name($ctx);
        $responsibleMailAddress = \DaveRandom\LibDNS\decode_domain_name($ctx);
        $meta = $ctx->unpack('Nserial/Nrefresh/Nretry/Nexpire/Nttl', 20);

        return new ResourceData\SOA(
            $masterServerName,
            $responsibleMailAddress,
            $meta['serial'], $meta['refresh'], $meta['retry'], $meta['expire'], $meta['ttl'],
            false
        );
    }

    private function decodeTXT(DecodingContext $ctx, int $length): ResourceData\TXT
    {
        $consumed = 0;
        $strings = [];

        while ($consumed < $length) {
            $string = \DaveRandom\LibDNS\decode_character_data($ctx);
            $strings[] = $string;
            $consumed += \strlen($string) + 1;
        }

        return new ResourceData\TXT($strings);
    }

    private function decodeWKS(DecodingContext $ctx, int $length): ResourceData\WKS
    {
        $address = \DaveRandom\LibDNS\decode_ipv4address($ctx);
        $parts = $ctx->unpack('Cprotocol/Zbitmap' . ($length - 5), $length - 4);

        return new ResourceData\WKS($address, $parts['protocol'], $parts['bitmap']);
    }

    public function decode(DecodingContext $ctx, int $type, int $length): ResourceData
    {
        if (!\array_key_exists($type, self::DECODERS)) {
            throw new \UnexpectedValueException("Unknown resource data type ID: {$type}");
        }

        $expectedOffset = $ctx->offset + $length;
        $result = ([$this, self::DECODERS[$type]])($ctx, $length);

        if ($ctx->offset !== $expectedOffset) {
            throw new \RuntimeException(
                "Current offset {$ctx->offset} does not match expected offset {$expectedOffset}"
            );
        }

        return $result;
    }
}
