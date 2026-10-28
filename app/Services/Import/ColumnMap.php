<?php

namespace App\Services\Import;

/**
 * Peta header Excel -> field database.
 * Pencocokan berdasarkan NAMA header (bukan posisi kolom),
 * jadi kolom boleh bergeser atau bertambah di file versi berikutnya.
 */
class ColumnMap
{
    /** field DB => daftar nama header yang dikenali (sudah dinormalisasi) */
    public const ALIASES = [
        'source_no'                => ['no'],
        'status'                   => ['status'],
        'npk'                      => ['npk'],
        'employee_name'            => ['name of staff', 'name', 'nama'],
        'employee_level'           => ['group', 'level'],
        'division'                 => ['division/department', 'division', 'department'],
        'unit'                     => ['unit'],
        'position'                 => ['position'],
        'certificate_availability' => ['certificate availability'],
        'training_type'            => ['training type', 'type of training'],
        'course_title'             => ['course title'],
        'organizer'                => ['organizer'],
        'training_date'            => ['date of training'],
        'certificate_date'         => ['date cert issued'],
        'expiry_date'              => ['date expired'],
        'renewal_year'             => ['year renewal'],
        'expiry_date_helper'       => ['a'],
        'certificate_note'         => ['b'],
        'training_month'           => ['month'],
        'training_year'            => ['years', 'year'],
        'training_days'            => ['ttl day', 'ttl days', 'total day'],
        'training_hours'           => ['ttl hrs', 'ttl hours', 'total hours'],
        'course_fee'               => ['course fee'],
        'total_cost'               => ['ttl cost', 'total cost'],
        'grade'                    => ['grade'],
        'eselon'                   => ['eselon'],
        'group_leader'             => ['group leader (atasan langsung)', 'group leader'],
        'date_of_birth'            => ['date of birth'],
        'year_of_service'          => ['year of service'],
        'age'                      => ['age'],
        'pension_date'             => ['pension date'],
        'yos_balance'              => ['yos bal'],
        'education'                => ['education'],
        'majoring'                 => ['majoring'],
        'education_institution'    => ['institut pendidikan'],
    ];

    /** Jenis pembersihan untuk tiap field */
    public const KINDS = [
        'source_no'                => 'int',
        'status'                   => 'status',
        'npk'                      => 'text',
        'employee_name'            => 'text',
        'employee_level'           => 'level',
        'division'                 => 'category',
        'unit'                     => 'text',
        'position'                 => 'text',
        'certificate_availability' => 'lower',
        'training_type'            => 'category',
        'course_title'             => 'text',
        'organizer'                => 'text',
        'training_date'            => 'date',
        'certificate_date'         => 'date',
        'expiry_date'              => 'date',
        'renewal_year'             => 'year',
        'expiry_date_helper'       => 'date_only',
        'certificate_note'         => 'text',
        'training_month'           => 'month',
        'training_year'            => 'year',
        'training_days'            => 'decimal',
        'training_hours'           => 'decimal',
        'course_fee'               => 'decimal',
        'total_cost'               => 'decimal',
        'grade'                    => 'text0',
        'eselon'                   => 'text0',
        'group_leader'             => 'text',
        'date_of_birth'            => 'date_only',
        'year_of_service'          => 'text',
        'age'                      => 'text',
        'pension_date'             => 'date_only',
        'yos_balance'              => 'text',
        'education'                => 'text0',
        'majoring'                 => 'text0',
        'education_institution'    => 'text0',
    ];

    /** Kolom wajib: tanpa ini baris tidak bisa menjadi data training */
    public const REQUIRED = ['course_title'];

    /** Kolom yang sangat dibutuhkan dashboard: jika hilang, tampilkan peringatan */
    public const RECOMMENDED = [
        'status', 'employee_level', 'division', 'training_type',
        'training_year', 'renewal_year', 'training_days', 'training_hours',
    ];

    private static ?array $reverse = null;

    /** Samakan bentuk header: huruf kecil, newline/spasi ganda dirapikan */
    public static function normalizeHeader(?string $header): string
    {
        $h = mb_strtolower((string) $header);
        $h = preg_replace('/[\s\x{00A0}]+/u', ' ', $h);
        $h = preg_replace('/\s*\/\s*/u', '/', $h);
        return trim($h);
    }

    /** Cari nama field dari header yang sudah dinormalisasi */
    public static function fieldFor(string $normalizedHeader): ?string
    {
        if (self::$reverse === null) {
            self::$reverse = [];
            foreach (self::ALIASES as $field => $aliases) {
                foreach ($aliases as $alias) {
                    self::$reverse[self::normalizeHeader($alias)] = $field;
                }
            }
        }
        return self::$reverse[$normalizedHeader] ?? null;
    }
}