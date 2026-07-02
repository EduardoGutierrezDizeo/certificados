<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lawyer_id')->constrained('users')->restrictOnDelete();
            $table->enum('document_type', ['CC', 'CE', 'PA', 'NIT']);
            $table->string('document_number');
            $table->string('full_name')->nullable();
            $table->string('company_name')->nullable();
            $table->date('issuance_date')->nullable();
            $table->timestamps();

            $table->unique(['lawyer_id', 'document_type', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};