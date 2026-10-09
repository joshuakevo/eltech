<?php

namespace App\Services;

/**
 * Minimal CBOR (RFC 8949) decoder for WebAuthn attestation objects and COSE keys:
 * unsigned/negative integers, byte and text strings, arrays, maps, tags, simple values and floats.
 * Byte strings are returned as raw binary strings; indefinite lengths are not supported (WebAuthn never uses them).
 */
class CborDecoder
{
    private int $pos = 0;

    public function __construct(private string $data) {}

    public function decode()
    {
        $value = $this->item();
        return $value;
    }

    private function item()
    {
        $initial = $this->byte();
        $major = $initial >> 5;
        $info = $initial & 0x1f;

        if ($major === 7) {
            return match ($info) {
                20 => false, 21 => true, 22, 23 => null,
                25 => $this->half(), 26 => unpack('G', $this->read(4))[1], 27 => unpack('E', $this->read(8))[1],
                default => throw new \RuntimeException('Unsupported CBOR simple value'),
            };
        }

        $len = $this->length($info);
        switch ($major) {
            case 0: return $len;
            case 1: return -1 - $len;
            case 2: return $this->read($len);
            case 3: return $this->read($len);
            case 4:
                $arr = [];
                for ($i = 0; $i < $len; $i++) {
                    $arr[] = $this->item();
                }
                return $arr;
            case 5:
                $map = [];
                for ($i = 0; $i < $len; $i++) {
                    $key = $this->item();
                    $map[$key] = $this->item();
                }
                return $map;
            case 6: return $this->item();   // tag: ignore, return tagged value
        }
        throw new \RuntimeException('Invalid CBOR');
    }

    private function length(int $info): int
    {
        if ($info < 24) {
            return $info;
        }
        return match ($info) {
            24 => $this->byte(),
            25 => unpack('n', $this->read(2))[1],
            26 => unpack('N', $this->read(4))[1],
            27 => (int) unpack('J', $this->read(8))[1],
            default => throw new \RuntimeException('Unsupported CBOR length'),
        };
    }

    private function half(): float
    {
        $h = unpack('n', $this->read(2))[1];
        $exp = ($h >> 10) & 0x1f; $mant = $h & 0x3ff;
        $val = $exp === 0 ? $mant * 2 ** -24 : ($exp !== 31 ? ($mant + 1024) * 2 ** ($exp - 25) : ($mant === 0 ? INF : NAN));
        return $h & 0x8000 ? -$val : $val;
    }

    private function byte(): int
    {
        return ord($this->read(1));
    }

    private function read(int $n): string
    {
        if ($n < 0 || $this->pos + $n > strlen($this->data)) {
            throw new \RuntimeException('Truncated CBOR data');
        }
        $s = substr($this->data, $this->pos, $n);
        $this->pos += $n;
        return $s;
    }
}
