<?php

namespace Tests\Feature\Internal;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaidTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-internal-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nvh_admin.secret' => $this->secret]);
    }

    private function callPaid(array $payload, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Internal-Secret', $secret ?? $this->secret)
            ->postJson('/api/internal/invoice-paid', $payload);
    }

    private function makeInvoice(array $attrs = []): Invoice
    {
        $client = Client::factory()->create();

        return Invoice::factory()->create(array_merge([
            'client_id'   => $client->id,
            'external_id' => 'admin-inv-001',
            'status'      => 'unpaid',
            'paid_at'     => null,
        ], $attrs));
    }

    // --- Auth ---

    public function test_rejects_request_without_secret(): void
    {
        $this->postJson('/api/internal/invoice-paid', [])->assertUnauthorized();
    }

    public function test_rejects_request_with_wrong_secret(): void
    {
        $this->callPaid([], 'bad-secret')->assertUnauthorized();
    }

    // --- Validation ---

    public function test_rejects_missing_fields(): void
    {
        $this->callPaid([])->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id', 'status', 'paid_at']);
    }

    public function test_rejects_invalid_status(): void
    {
        $this->callPaid([
            'external_id' => 'admin-inv-001',
            'status'      => 'unknown',
            'paid_at'     => '2026-06-22T13:45:00Z',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    // --- Not found ---

    public function test_returns_404_for_unknown_external_id(): void
    {
        $this->callPaid([
            'external_id' => 'does-not-exist',
            'status'      => 'paid',
            'paid_at'     => '2026-06-22T13:45:00Z',
        ])->assertNotFound();
    }

    // --- Mark paid ---

    public function test_marks_invoice_as_paid(): void
    {
        $invoice = $this->makeInvoice();

        $this->callPaid([
            'external_id' => 'admin-inv-001',
            'status'      => 'paid',
            'paid_at'     => '2026-06-22T13:45:00Z',
        ])->assertOk()
          ->assertJsonPath('invoice.status', 'paid');

        $fresh = $invoice->fresh();
        $this->assertEquals('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertNotNull($fresh->synced_at);
    }

    public function test_paid_at_is_stored_correctly(): void
    {
        $this->makeInvoice();

        $this->callPaid([
            'external_id' => 'admin-inv-001',
            'status'      => 'paid',
            'paid_at'     => '2026-06-22T13:45:00.000000Z',
        ])->assertOk();

        $invoice = Invoice::where('external_id', 'admin-inv-001')->first();
        $this->assertEquals('2026-06-22 13:45:00', $invoice->paid_at->format('Y-m-d H:i:s'));
    }

    public function test_idempotent_on_duplicate_call(): void
    {
        $this->makeInvoice();

        $payload = [
            'external_id' => 'admin-inv-001',
            'status'      => 'paid',
            'paid_at'     => '2026-06-22T13:45:00Z',
        ];

        $this->callPaid($payload)->assertOk();
        $this->callPaid($payload)->assertOk();

        $this->assertDatabaseCount('invoices', 1);
    }
}
