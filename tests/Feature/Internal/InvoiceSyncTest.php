<?php

namespace Tests\Feature\Internal;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-internal-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nvh_admin.secret' => $this->secret]);
    }

    private function sync(array $payload, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Internal-Secret', $secret ?? $this->secret)
            ->postJson('/api/internal/invoices/sync', $payload);
    }

    private function validPayload(string $clientId, array $overrides = []): array
    {
        return array_merge([
            'client_id'    => $clientId,
            'id'           => 'admin-inv-001',
            'amount'       => 15000.00,
            'currency'     => 'NGN',
            'status'       => 'unpaid',
            'due_date'     => '2026-07-18',
            'paid_at'      => null,
            'period_start' => '2026-06-18',
            'period_end'   => '2026-07-18',
        ], $overrides);
    }

    // --- Auth ---

    public function test_rejects_request_without_secret(): void
    {
        $this->postJson('/api/internal/invoices/sync', [])->assertUnauthorized();
    }

    public function test_rejects_request_with_wrong_secret(): void
    {
        $this->sync([], 'bad-secret')->assertUnauthorized();
    }

    // --- Validation ---

    public function test_rejects_missing_required_fields(): void
    {
        $this->sync([])->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id', 'id', 'amount', 'currency', 'status', 'due_date']);
    }

    public function test_rejects_invalid_status(): void
    {
        $client = Client::factory()->create();

        $this->sync($this->validPayload($client->id, ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_rejects_invalid_currency_length(): void
    {
        $client = Client::factory()->create();

        $this->sync($this->validPayload($client->id, ['currency' => 'US']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_rejects_negative_amount(): void
    {
        $client = Client::factory()->create();

        $this->sync($this->validPayload($client->id, ['amount' => -100]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    // --- Not found ---

    public function test_returns_404_for_unknown_client(): void
    {
        $this->sync($this->validPayload('non-existent-uuid'))->assertNotFound();
    }

    // --- Create ---

    public function test_creates_invoice_for_client(): void
    {
        $client = Client::factory()->create();

        $response = $this->sync($this->validPayload($client->id));

        $response->assertOk()
            ->assertJsonPath('invoice.id', 'admin-inv-001')
            ->assertJsonPath('invoice.client_id', $client->id)
            ->assertJsonPath('invoice.status', 'unpaid')
            ->assertJsonPath('invoice.currency', 'NGN');

        $this->assertDatabaseHas('invoices', [
            'id'        => 'admin-inv-001',
            'client_id' => $client->id,
        ]);
    }

    // --- Update (idempotent upsert) ---

    public function test_updates_existing_invoice_by_id(): void
    {
        $client  = Client::factory()->create();
        $invoice = Invoice::factory()->create([
            'id'        => 'admin-inv-001',
            'client_id' => $client->id,
            'status'    => 'unpaid',
        ]);

        $this->sync($this->validPayload($client->id, [
            'status'  => 'paid',
            'paid_at' => '2026-06-20T09:00:00Z',
        ]))->assertOk()->assertJsonPath('invoice.status', 'paid');

        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_synced_at_is_stamped(): void
    {
        $client = Client::factory()->create();

        $this->sync($this->validPayload($client->id))->assertOk();

        $invoice = Invoice::find('admin-inv-001');
        $this->assertNotNull($invoice->synced_at);
    }

    public function test_creates_invoice_with_paid_status(): void
    {
        $client = Client::factory()->create();

        $this->sync($this->validPayload($client->id, [
            'id'      => 'admin-inv-002',
            'status'  => 'paid',
            'paid_at' => '2026-06-18T12:00:00Z',
        ]))->assertOk()
           ->assertJsonPath('invoice.status', 'paid');

        $this->assertNotNull(Invoice::find('admin-inv-002')->paid_at);
    }

    public function test_sync_is_idempotent(): void
    {
        $client  = Client::factory()->create();
        $payload = $this->validPayload($client->id);

        $this->sync($payload)->assertOk();
        $this->sync($payload)->assertOk();

        $this->assertDatabaseCount('invoices', 1);
    }
}
