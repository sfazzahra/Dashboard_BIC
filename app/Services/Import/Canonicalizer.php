<?php

namespace App\Services\Import;

/**
 * Menyeragamkan variasi HURUF BESAR/KECIL pada kategori
 * (misalnya "waste", "WASTE", "Waste" -> satu kategori).
 * Kategori yang beda ejaan atau beda nama TIDAK digabung.
 */
class Canonicalizer
{
    /**
     * @param array<string, array<string,int>> $variants  kunci huruf-kecil => [variasi => jumlah]
     * @return array<string,string> kunci huruf-kecil => nama kanonik
     */
    public static function buildMap(array $variants): array
    {
        $map = [];
        foreach ($variants as $key => $counts) {
            $map[$key] = self::pick($counts);
        }
        return $map;
    }

    /** Pilih penulisan terbaik: akronim pendek HURUF BESAR, kata panjang "Huruf Awal Besar" */
    private static function pick(array $counts): string
    {
        $best = null;
        $bestScore = -1;
        $bestCount = -1;
        foreach ($counts as $variant => $count) {
            $variant = (string) $variant;
            $score = self::score($variant);
            if ($score > $bestScore || ($score === $bestScore && $count > $bestCount)) {
                $best = $variant;
                $bestScore = $score;
                $bestCount = $count;
            }
        }
        return $best;
    }

    private static function score(string $v): int
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $v);
        $isLower = $v === mb_strtolower($v);
        $isUpper = $v === mb_strtoupper($v);

        if (mb_strlen($letters) <= 3) {
            return $isUpper ? 3 : ($isLower ? 0 : 1);
        }
        if (!$isLower && !$isUpper) {
            return 3;
        }
        return $isUpper ? 1 : 0;
    }
}