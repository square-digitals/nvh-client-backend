<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')->where('currency', '!=', 'NGN')->update(['currency' => 'NGN']);

        Schema::table('invoices', function (Blueprint $table) {
            $table->char('currency', 3)->default('NGN')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->char('currency', 3)->default(null)->change();
        });
    }
};
