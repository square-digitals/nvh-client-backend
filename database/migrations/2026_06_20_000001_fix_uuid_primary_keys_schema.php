<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $clientIdType = DB::selectOne(
            "SELECT data_type FROM information_schema.columns WHERE table_name = 'clients' AND column_name = 'id'"
        )?->data_type;

        if ($clientIdType === 'uuid') {
            return;
        }

        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('services');
        Schema::dropIfExists('clients');

        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\Models\Client')
            ->delete();

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('suspended_reason')->nullable();
            $table->string('plan')->nullable();
            $table->string('external_admin_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

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

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('external_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->enum('status', ['unpaid', 'paid', 'overdue', 'void'])->default('unpaid');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->enum('author_type', ['client', 'admin']);
            $table->string('author_id');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Irreversible — this migration corrects data loss from wrong column types
    }
};
