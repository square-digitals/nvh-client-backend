<?php

namespace Tests\Feature\Invoices;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    // --- List ---

    public function test_unauthenticated_cannot_list_invoices(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
    }

    public function test_unverified_client_cannot_list_invoices(): void
    {
        $client = Client::factory()->unverified()->create();

        $this->actingAs($client)->getJson('/api/invoices')->assertForbidden();
    }

    public function test_client_can_list_own_invoices(): void
    {
        $client = Client::factory()->create();
        Invoice::factory()->count(3)->create(['client_id' => $client->id]);

        $response = $this->actingAs($client)->getJson('/api/invoices');

        $response->assertOk()
            ->assertJsonCount(3, 'invoices')
            ->assertJsonStructure([
                'invoices' => [[
                    'id', 'client_id', 'amount', 'currency',
                    'status', 'due_date', 'paid_at', 'period_start',
                    'period_end', 'synced_at', 'created_at', 'updated_at',
                ]],
            ]);
    }

    public function test_client_only_sees_own_invoices(): void
    {
        $client = Client::factory()->create();
        $other  = Client::factory()->create();

        Invoice::factory()->count(2)->create(['client_id' => $client->id]);
        Invoice::factory()->count(3)->create(['client_id' => $other->id]);

        $response = $this->actingAs($client)->getJson('/api/invoices');

        $response->assertOk()->assertJsonCount(2, 'invoices');
    }

    public function test_returns_empty_array_when_no_invoices(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($client)->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonCount(0, 'invoices');
    }

    public function test_invoices_returned_latest_first(): void
    {
        $client  = Client::factory()->create();
        $older   = Invoice::factory()->create(['client_id' => $client->id, 'created_at' => now()->subDay()]);
        $newer   = Invoice::factory()->create(['client_id' => $client->id, 'created_at' => now()]);

        $ids = $this->actingAs($client)
            ->getJson('/api/invoices')
            ->assertOk()
            ->json('invoices.*.id');

        $this->assertEquals([$newer->id, $older->id], $ids);
    }

    // --- Show ---

    public function test_unauthenticated_cannot_view_invoice(): void
    {
        $invoice = Invoice::factory()->create();

        $this->getJson("/api/invoices/{$invoice->id}")->assertUnauthorized();
    }

    public function test_client_can_view_own_invoice(): void
    {
        $client  = Client::factory()->create();
        $invoice = Invoice::factory()->paid()->create(['client_id' => $client->id]);

        $this->actingAs($client)
            ->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('invoice.id', $invoice->id)
            ->assertJsonPath('invoice.status', 'paid');
    }

    public function test_client_cannot_view_another_clients_invoice(): void
    {
        $client  = Client::factory()->create();
        $other   = Client::factory()->create();
        $invoice = Invoice::factory()->create(['client_id' => $other->id]);

        $this->actingAs($client)
            ->getJson("/api/invoices/{$invoice->id}")
            ->assertNotFound();
    }

    public function test_returns_404_for_nonexistent_invoice(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($client)
            ->getJson('/api/invoices/non-existent-uuid')
            ->assertNotFound();
    }

    public function test_paid_invoice_has_paid_at_timestamp(): void
    {
        $client  = Client::factory()->create();
        $invoice = Invoice::factory()->paid()->create(['client_id' => $client->id]);

        $this->actingAs($client)
            ->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('invoice.status', 'paid')
            ->assertJsonStructure(['invoice' => ['paid_at']]);

        $this->assertNotNull($invoice->paid_at);
    }
}
