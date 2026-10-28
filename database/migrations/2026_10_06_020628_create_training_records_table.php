<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('source_row')->nullable();   // nomor baris di Excel
            $table->unsignedInteger('source_no')->nullable();    // kolom "No"
            $table->char('row_hash', 64)->index();               // deteksi duplikat

            // Karyawan
            $table->string('status', 30)->nullable();
            $table->string('npk', 30)->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employee_level', 30)->nullable();    // EXECUTIVE / MANAGER / NON-EXEC / NULL
            $table->string('division', 50)->nullable();
            $table->string('unit', 100)->nullable();
            $table->string('position')->nullable();

            // Training
            $table->string('certificate_availability', 30)->nullable();
            $table->string('training_type', 100)->nullable();    // sudah dinormalisasi
            $table->string('training_type_raw', 100)->nullable();// tulisan asli di Excel
            $table->text('course_title')->nullable();
            $table->string('organizer')->nullable();

            // Tanggal (nilai terparse + teks asli)
            $table->date('training_date')->nullable();
            $table->string('training_date_raw')->nullable();
            $table->date('certificate_date')->nullable();
            $table->string('certificate_date_raw')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('expiry_date_raw')->nullable();       // termasuk "NO RENEWAL"

            $table->unsignedSmallInteger('renewal_year')->nullable();
            $table->date('expiry_date_helper')->nullable();      // kolom A
            $table->string('certificate_note', 50)->nullable();  // kolom B
            $table->string('training_month', 30)->nullable();
            $table->unsignedSmallInteger('training_year')->nullable();
            $table->decimal('training_days', 8, 2)->nullable();
            $table->decimal('training_hours', 8, 2)->nullable();
            $table->decimal('course_fee', 15, 2)->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();

            // Data karyawan tambahan
            $table->string('grade', 20)->nullable();
            $table->string('eselon', 20)->nullable();
            $table->string('group_leader')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('year_of_service', 30)->nullable();   // format "4,3"
            $table->string('age', 30)->nullable();               // format "29 Y 6 M"
            $table->date('pension_date')->nullable();
            $table->string('yos_balance', 30)->nullable();
            $table->string('education', 100)->nullable();
            $table->string('majoring')->nullable();
            $table->string('education_institution')->nullable();

            $table->json('extra_data')->nullable();              // kolom baru yang belum dikenal
            $table->timestamps();

            // Index untuk query dashboard
            $table->index(['import_batch_id', 'training_year']);
            $table->index(['import_batch_id', 'renewal_year']);
            $table->index('division');
            $table->index('training_type');
            $table->index('employee_level');
            $table->index('status');
            $table->index('training_date');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_records');
    }
};