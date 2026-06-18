<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->enum('type', ['wordpress']);
            $table->string('name');
            $table->string('domain')->nullable();
            $table->enum('status', [
                'pending_approval',
                'provisioning',
                'active',
                'suspended',
                'failed',
                'rejected',
                'terminated',
            ])->default('pending_approval');
            $table->string('url')->nullable();
            $table->string('failed_reason')->nullable();
            $table->string('admin_service_id')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
