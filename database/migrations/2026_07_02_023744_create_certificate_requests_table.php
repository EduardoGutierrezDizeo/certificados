<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_request_id')->constrained('consultation_requests')->cascadeOnDelete();
            $table->enum('site', ['comptroller', 'judicial_police', 'rnmc', 'attorney_general']);
            $table->enum('status', ['pending', 'processing', 'success', 'failed'])
                ->default('pending');
            $table->text('error_message')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['consultation_request_id', 'site']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_requests');
    }
};
