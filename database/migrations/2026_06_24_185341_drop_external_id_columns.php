<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('external_admin_id');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('admin_service_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('external_admin_id')->nullable();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('admin_service_id')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique();
        });
    }
};
