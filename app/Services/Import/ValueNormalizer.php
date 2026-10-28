<?php

namespace App\Services\Import;

/**
 * Pembersih nilai sel Excel. Semua fungsi bersifat static dan tidak mengubah file asli.
 */
class ValueNormalizer
{
    private const MONTHS = [
        'januari' => 1, 'jan' => 1, 'january' => 1,
        'februari' => 2, 'feb' => 2, 'february' => 2, 'pebruari' => 2,
        'maret' => 3, 'mar' => 3, 'march' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5, 'may' => 5,
        'juni' => 6, 'jun' => 6, 'june' => 6,
        'juli' => 7, 'jul' => 7, 'july' => 7,
        'agustus' => 8, 'agu' => 8, 'agt' => 8, 'aug' => 8, 'august' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10, 'oct' => 10, 'october' => 10,
        'november' => 11, 'nov' => 11, 'nop' => 11,
        'desember' => 12, 'des' => 12, 'dec' => 12, 'december' => 12,
    ];

    private const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    public static function isBlank(mixed $v): bool
    {
        if ($v === null) {
            return true;
        }
        return is_string($v) && trim($v) === '';
    }

    /** Teks mentah untuk header (tanpa membuang apa pun) */
    public static function headerText(mixed $v): string
    {
        if (is_string($v)) {
            return trim($v);
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        return '';
    }

    /** Teks umum: rapikan spasi, ubah nilai error Excel jadi NULL, seragamkan placeholder */
    public static function text(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            if (fmod($v, 1.0) === 0.0) {
                return (string) (int) $v;
            }
            return rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
        }
        if (!is_string($v)) {
            return null;
        }

        $s = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $v));
        if ($s === '') {
            return null;
        }
        // Nilai error Excel (#N/A, #REF!, dst) dianggap kosong
        if (preg_match('/^#(N\/A|REF!|VALUE!|DIV\/0!|NAME\?|NULL!|NUM!)$/i', $s)) {
            return null;
        }
        return self::placeholder($s);
    }

    /** "no record" -> "No Record", "NO RENEWAL" -> "No Renewal" */
    public static function placeholder(string $s): string
    {
        $l = mb_strtolower($s);
        if ($l === 'no record') {
            return 'No Record';
        }
        if ($l === 'no renewal') {
            return 'No Renewal';
        }
        return $s;
    }

    public static function isPlaceholder(?string $s): bool
    {
        return $s === 'No Record' || $s === 'No Renewal';
    }

    /** Teks, tetapi "0" dan "NONE" dianggap kosong (Grade, Eselon, Education, dst) */
    public static function textZero(mixed $v): ?string
    {
        $s = self::text($v);
        if ($s === null) {
            return null;
        }
        return in_array(mb_strtoupper($s), ['0', 'NONE'], true) ? null : $s;
    }

    public static function lower(mixed $v): ?string
    {
        $s = self::text($v);
        return $s === null ? null : mb_strtolower($s);
    }

    /** Group: EXECUTIVE / MANAGER / NON-EXEC. "0" dan #N/A menjadi NULL. Nilai lain tetap disimpan. */
    public static function level(mixed $v): ?string
    {
        $s = self::text($v);
        if ($s === null) {
            return null;
        }
        $u = mb_strtoupper($s);
        return in_array($u, ['0', 'NONE', '-'], true) ? null : $u;
    }

    public static function status(mixed $v): ?string
    {
        $s = self::text($v);
        if ($s === null) {
            return null;
        }
        $n = preg_replace('/[\s_-]+/', '-', mb_strtolower($s));
        if ($n === 'active') {
            return 'Active';
        }
        if (in_array($n, ['non-active', 'nonactive'], true)) {
            return 'Non-Active';
        }
        return $s; // nilai lain (misal "New-Active") dibiarkan apa adanya
    }

    /** Kategori (Division, Training Type): hanya rapikan spasi. Penyeragaman huruf dilakukan Canonicalizer. */
    public static function category(mixed $v): ?string
    {
        return self::text($v);
    }

    public static function integer(mixed $v): ?int
    {
        [$n] = self::decimal($v);
        return $n === null ? null : (int) $n;
    }

    /** @return array{0: ?int, 1: bool} [nilai, apakah tidak valid] */
    public static function year(mixed $v): array
    {
        if (self::isBlank($v)) {
            return [null, false];
        }
        if (is_int($v) || is_float($v) || (is_string($v) && is_numeric(trim($v)))) {
            $n = (float) (is_string($v) ? trim($v) : $v);
            if ($n == floor($n) && $n >= 1900 && $n <= 2100) {
                return [(int) $n, false];
            }
            return [null, true];
        }
        $s = self::text($v);
        if ($s === null || self::isPlaceholder($s)) {
            return [null, false]; // "NO RENEWAL" bukan tahun, bukan error
        }
        return [null, true];
    }

    /** @return array{0: ?float, 1: bool} [nilai, apakah tidak valid] */
    public static function decimal(mixed $v): array
    {
        if (self::isBlank($v)) {
            return [null, false];
        }
        if (is_int($v) || is_float($v)) {
            return [(float) $v, false];
        }
        $s = self::text($v);
        if ($s === null || self::isPlaceholder($s)) {
            return [null, false];
        }
        $c = str_ireplace(['Rp', ' '], '', $s);
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $c)) {      // 1.500.000 atau 1.500.000,50
            $c = str_replace(['.', ','], ['', '.'], $c);
        } elseif (preg_match('/^-?\d+,\d+$/', $c)) {                 // 1,75
            $c = str_replace(',', '.', $c);
        } elseif (!preg_match('/^-?\d+(\.\d+)?$/', $c)) {
            return [null, true];
        }
        return [(float) $c, false];
    }

    /** Bulan: hanya nama bulan tunggal yang jelas yang diseragamkan, sisanya dibiarkan */
    public static function month(mixed $v): ?string
    {
        $s = self::text($v);
        if ($s === null) {
            return null;
        }
        $key = mb_strtolower(rtrim($s, '.'));
        if (isset(self::MONTHS[$key])) {
            return self::MONTH_LABELS[self::MONTHS[$key]];
        }
        return $s;
    }

    /**
     * @return array{date: ?string, raw: ?string, status: string}
     * status: blank | ok | placeholder | unparsed | invalid
     */
    public static function date(mixed $v): array
    {
        if (self::isBlank($v)) {
            return ['date' => null, 'raw' => null, 'status' => 'blank'];
        }

        if ($v instanceof \DateTimeInterface) {
            $year = (int) $v->format('Y');
            if ($year < 1900 || $year > 2100) {
                return ['date' => null, 'raw' => $v->format('Y-m-d'), 'status' => 'invalid'];
            }
            return ['date' => $v->format('Y-m-d'), 'raw' => null, 'status' => 'ok'];
        }

        if (is_int($v) || is_float($v)) {
            if ($v >= 20000 && $v <= 80000) { // nomor seri tanggal Excel
                $d = (new \DateTimeImmutable('1899-12-30'))->modify('+' . (int) $v . ' days');
                return ['date' => $d->format('Y-m-d'), 'raw' => null, 'status' => 'ok'];
            }
            return ['date' => null, 'raw' => self::text($v), 'status' => 'invalid'];
        }

        $s = self::text($v);
        if ($s === null) {
            return ['date' => null, 'raw' => null, 'status' => 'blank'];
        }
        if (self::isPlaceholder($s)) {
            return ['date' => null, 'raw' => $s, 'status' => 'placeholder'];
        }

        $parsed = self::parseDateString($s);
        if ($parsed !== null) {
            return ['date' => $parsed, 'raw' => null, 'status' => 'ok'];
        }
        // Contoh: "14 Sept - 2 Nov 2024" (rentang) -> tidak ditebak, teks asli disimpan
        return ['date' => null, 'raw' => $s, 'status' => 'unparsed'];
    }

    private static function parseDateString(string $s): ?string
    {
        $s = trim(rtrim($s, '.'));

        // 2025-06-30 (boleh dengan jam)
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$/', $s, $m)) {
            return self::buildDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 30/06/2025, 30-06-2025, 30.06.2025 (urutan hari/bulan/tahun)
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $s, $m)) {
            return self::buildDate(self::fullYear((int) $m[3]), (int) $m[2], (int) $m[1]);
        }

        // 07 Juli 2022, 22 Maret 2027, 29 Feb 2023
        if (preg_match('/^(\d{1,2})\s*[-,]?\s*([A-Za-z]+)\.?\s*[-,]?\s*(\d{2,4})$/', $s, $m)) {
            $month = self::monthNumber($m[2]);
            if ($month !== null) {
                return self::buildDate(self::fullYear((int) $m[3]), $month, (int) $m[1]);
            }
        }

        return null;
    }

    private static function monthNumber(string $name): ?int
    {
        $key = mb_strtolower(rtrim($name, '.'));
        if (isset(self::MONTHS[$key])) {
            return self::MONTHS[$key];
        }
        $short = substr($key, 0, 3);
        return self::MONTHS[$short] ?? null;
    }

    private static function fullYear(int $y): int
    {
        if ($y >= 100) {
            return $y;
        }
        return $y <= 69 ? 2000 + $y : 1900 + $y;
    }

    private static function buildDate(int $y, int $m, int $d): ?string
    {
        if ($y < 1900 || $y > 2100 || !checkdate($m, $d, $y)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}