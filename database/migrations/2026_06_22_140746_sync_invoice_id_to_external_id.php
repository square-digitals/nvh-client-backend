<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE invoices SET id = external_id::uuid WHERE id != external_id::uuid');
    }

    public function down(): void
    {
        // Irreversible — original auto-generated IDs are not recoverable
    }
};
