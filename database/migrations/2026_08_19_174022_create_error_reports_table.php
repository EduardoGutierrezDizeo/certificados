<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lawyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->enum('category', ['pago', 'certificado', 'otro']);
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->text('admin_comment')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
