<?php

namespace Tests\Feature\Internal;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceIssuedTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-internal-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nvh_admin.secret' => $this->secret]);
    }

    private function callIssued(array $payload, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Internal-Secret', $secret ?? $this->secret)
            ->postJson('/api/internal/invoice-issued', $payload);
    }

    private function validPayload(string $clientExternalId, array $overrides = []): array
    {
        return array_merge([
            'external_id'        => 'admin-inv-001',
            'client_external_id' => $clientExternalId,
            'amount'             => '29.99',
            'currency'           => 'USD',
            'status'             => 'unpaid',
            'due_date'           => '2026-07-01',
            'period_start'       => '2026-07-01',
            'period_end'         => '2026-07-31',
        ], $overrides);
    }

    // --- Auth ---

    public function test_rejects_request_without_secret(): void
    {
        $this->postJson('/api/internal/invoice-issued', [])->assertUnauthorized();
    }

    public function test_rejects_request_with_wrong_secret(): void
    {
        $this->callIssued([], 'bad-secret')->assertUnauthorized();
    }

    // --- Validation ---

    public function test_rejects_missing_required_fields(): void
    {
        $this->callIssued([])->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id', 'client_external_id', 'amount', 'currency', 'status', 'due_date']);
    }

    public function test_rejects_invalid_status(): void
    {
        $client = Client::factory()->create(['external_admin_id' => 'ext-001']);

        $this->callIssued($this->validPayload('ext-001', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_rejects_invalid_currency_length(): void
    {
        $client = Client::factory()->create(['external_admin_id' => 'ext-001']);

        $this->callIssued($this->validPayload('ext-001', ['currency' => 'US']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_rejects_negative_amount(): void
    {
        $client = Client::factory()->create(['external_admin_id' => 'ext-001']);

        $this->callIssued($this->validPayload('ext-001', ['amount' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    // --- Not found ---

    public function test_returns_404_for_unknown_client_external_id(): void
    {
        $this->callIssued($this->validPayload('does-not-exist'))->assertNotFound();
    }

    // --- Create ---

    public function test_creates_invoice_from_admin_payload(): void
    {
        $client = Client::factory()->create(['external_admin_id' => 'ext-001']);

        $response = $this->callIssued($this->validPayload('ext-001'));

        $response->assertOk()
            ->assertJsonPath('invoice.external_id', 'admin-inv-001')
            ->assertJsonPath('invoice.client_id', $client->id)
            ->assertJsonPath('invoice.status', 'unpaid')
            ->assertJsonPath('invoice.currency', 'USD');

        $this->assertDatabaseHas('invoices', [
            'external_id' => 'admin-inv-001',
            'client_id'   => $client->id,
        ]);
    }

    public function test_currency_is_uppercased(): void
    {
        $client = Client::factory()->create(['external_admin_id' => 'ext-001']);

        $this->callIssued($this->validPayload('ext-001', ['currency' => 'usd']))
            ->assertOk()
            ->assertJsonPath('invoice.currency', 'USD');
    }

    public function test_synced_at_is_stamped(): void
    {
        $client = Client::factory()->create(['external_admin_id' => 'ext-001']);

        $this->callIssued($this->validPayload('ext-001'))->assertOk();

        $invoice = Invoice::where('external_id', 'admin-inv-001')->first();
        $this->assertNotNull($invoice->synced_at);
    }

    // --- Idempotency ---

    public function test_is_idempotent_on_duplicate_call(): void
    {
        $client  = Client::factory()->create(['external_admin_id' => 'ext-001']);
        $payload = $this->validPayload('ext-001');

        $this->callIssued($payload)->assertOk();
        $this->callIssued($payload)->assertOk();

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_updates_existing_invoice_by_external_id(): void
    {
        $client = Client::factory()->create(['external_admin_id' => 'ext-001']);
        Invoice::factory()->create([
            'client_id'   => $client->id,
            'external_id' => 'admin-inv-001',
            'status'      => 'unpaid',
        ]);

        $this->callIssued($this->validPayload('ext-001', ['status' => 'paid']))
            ->assertOk()
            ->assertJsonPath('invoice.status', 'paid');

        $this->assertEquals('paid', Invoice::where('external_id', 'admin-inv-001')->first()->status);
    }
}
