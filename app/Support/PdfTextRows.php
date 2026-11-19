<?php

namespace App\Support;

/**
 * Minimal PDF text reader for simple report PDFs (e.g. Microsoft Access exports: FlateDecode content streams,
 * WinAnsi fonts) — no external package (vendor is uploaded by hand). Returns the text as rows: pieces on the same
 * line (same y within a stream) joined left to right. Not a general PDF parser: compressed object streams,
 * CID / Identity-H fonts and encrypted files are not supported.
 */
class PdfTextRows
{
    /** @return string[][] rows of cells, top to bottom, page by page */
    public static function rows(string $pdf): array
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m);
        $rows = [];
        foreach ($m[1] as $stream) {
            $data = @gzuncompress($stream);
            if ($data === false) {
                $data = @gzinflate(substr($stream, 2));
            }
            if ($data === false || !str_contains($data, 'BT')) {
                continue;
            }
            $pieces = self::pieces($data);
            if (!$pieces) {
                continue;
            }
            // group by y (tolerance 2pt), top to bottom; each row left to right
            usort($pieces, fn ($a, $b) => $b['y'] <=> $a['y'] ?: $a['x'] <=> $b['x']);
            $current = [];
            $lastY = null;
            foreach ($pieces as $p) {
                if ($lastY !== null && abs($p['y'] - $lastY) > 2) {
                    $rows[] = self::sortRow($current);
                    $current = [];
                }
                $current[] = $p;
                $lastY = $p['y'];
            }
            if ($current) {
                $rows[] = self::sortRow($current);
            }
        }
        return $rows;
    }

    private static function sortRow(array $pieces): array
    {
        usort($pieces, fn ($a, $b) => $a['x'] <=> $b['x']);
        return array_values(array_filter(array_map(fn ($p) => trim($p['text']), $pieces), fn ($t) => $t !== ''));
    }

    /** Text pieces with their position: each BT … ET block, position from Tm / Td. */
    private static function pieces(string $data): array
    {
        $out = [];
        preg_match_all('/BT(.*?)ET/s', $data, $blocks);
        foreach ($blocks[1] as $block) {
            $x = $y = 0.0;
            if (preg_match_all('/(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+Tm/', $block, $tm, PREG_SET_ORDER)) {
                $x = (float) $tm[0][5];
                $y = (float) $tm[0][6];
            } elseif (preg_match('/(-?[\d.]+)\s+(-?[\d.]+)\s+T[dD]/', $block, $td)) {
                $x = (float) $td[1];
                $y = (float) $td[2];
            }
            $text = '';
            // TJ arrays and Tj strings, in order
            preg_match_all('/\[((?:[^\]\\\\]|\\\\.)*)\]\s*TJ|(\((?:[^()\\\\]|\\\\.)*\))\s*(?:Tj|\'|")/s', $block, $ops, PREG_SET_ORDER);
            foreach ($ops as $op) {
                $src = $op[1] !== '' ? $op[1] : $op[2];
                preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)|(-?[\d.]+)/s', $src, $parts, PREG_SET_ORDER);
                foreach ($parts as $part) {
                    if (isset($part[1]) && $part[0][0] === '(') {
                        $text .= self::decode($part[1]);
                    } elseif ((float) $part[2] < -200) {
                        $text .= ' ';   // a wide gap is a space
                    }
                }
            }
            if ($text !== '') {
                $out[] = ['x' => $x, 'y' => $y, 'text' => $text];
            }
        }
        return $out;
    }

    private static function decode(string $s): string
    {
        $s = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($m) => chr(octdec($m[1])), $s);
        $s = strtr($s, ['\\n' => "\n", '\\r' => '', '\\t' => ' ', '\\(' => '(', '\\)' => ')', '\\\\' => '\\']);
        return mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }
}
