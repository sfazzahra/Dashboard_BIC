<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->char('file_hash', 64)->index();          // SHA-256, untuk tolak file yang sama
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sheet_name')->nullable();         // sheet yang terdeteksi sebagai sumber
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('status', 20)->default('pending'); // pending, processing, success, partial, failed
            $table->boolean('is_active')->default(false)->index(); // snapshot yang dipakai dashboard
            $table->json('detected_columns')->nullable();     // header yang dikenali & tidak dikenali
            $table->longText('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};