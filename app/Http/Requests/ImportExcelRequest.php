<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi (Admin) ditambahkan saat fase login
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,zip', 'max:51200'], // maks 50 MB
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $file = $this->file('file');
            if ($file && !$v->errors()->has('file')
                && strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
                $v->errors()->add('file', 'File harus berekstensi .xlsx.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file Excel terlebih dahulu.',
            'file.mimes'    => 'File harus berformat .xlsx. Jika file Anda .xls, buka di Excel lalu Save As > Excel Workbook (.xlsx).',
            'file.max'      => 'Ukuran file maksimal 50 MB.',
            'file.uploaded' => 'Upload gagal. Kemungkinan ukuran file melebihi batas PHP (upload_max_filesize / post_max_size di php.ini).',
        ];
    }
}