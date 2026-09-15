<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reemplaza pdf_generated_at por created_at en los certificados heredados que
     * se marcaron como success antes de que existiera la columna pdf_generated_at.
     */
    public function up(): void
    {
        DB::table('certificate_requests')
            ->where('status', 'success')
            ->whereNotNull('pdf_path')
            ->whereNull('pdf_generated_at')
            ->update(['pdf_generated_at' => DB::raw('created_at')]);
    }

    /**
     * Revierte el backfill.
     */
    public function down(): void
    {
        // No revertir: no hay forma de distinguir cuáles NULLs eran legítimos antes
        // del backfill vs cuáles se llenaron con created_at, así que una reversión
        // falsa solo reintroduciría los NULLs que distorsionan el orden de borrado.
    }
};
