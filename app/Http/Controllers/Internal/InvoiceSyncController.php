<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceSyncController extends Controller
{
    public function issued(Request $request): JsonResponse
    {
        Log::info('invoice-issued received', $request->all());

        $data = $request->validate([
            'external_id'        => ['required', 'string'],
            'client_external_id' => ['required', 'string'],
            'amount'             => ['required', 'numeric', 'min:0'],
            'status'             => ['required', 'string', 'in:unpaid,paid,overdue,void'],
            'due_date'           => ['required', 'date'],
            'period_start'       => ['nullable', 'date'],
            'period_end'         => ['nullable', 'date'],
        ]);

        $client = Client::where('external_admin_id', $data['client_external_id'])->first();

        if (! $client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        $invoice = Invoice::updateOrCreate(
            ['external_id' => $data['external_id']],
            [
                'id'           => $data['external_id'],
                'client_id'    => $client->id,
                'amount'       => $data['amount'],
                'currency'     => 'NGN',
                'status'       => $data['status'],
                'due_date'     => $data['due_date'],
                'period_start' => $data['period_start'] ?? null,
                'period_end'   => $data['period_end'] ?? null,
                'synced_at'    => now(),
            ]
        );

        return response()->json(['invoice' => $invoice]);
    }

    public function paid(Request $request): JsonResponse
    {
        Log::info('invoice-paid received', $request->all());

        $data = $request->validate([
            'external_id' => ['required', 'string'],
            'status'      => ['required', 'string', 'in:unpaid,paid,overdue,void'],
            'paid_at'     => ['required', 'string'],
        ]);

        $invoice = Invoice::where('external_id', $data['external_id'])->first();

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        $invoice->update([
            'status'     => $data['status'],
            'paid_at'    => Carbon::parse($data['paid_at']),
            'synced_at'  => now(),
        ]);

        return response()->json(['invoice' => $invoice]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id'    => ['required', 'string'],
            'external_id'  => ['required', 'string'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'status'       => ['required', 'string', 'in:unpaid,paid,overdue,void'],
            'due_date'     => ['required', 'date'],
            'paid_at'      => ['nullable', 'string'],
            'period_start' => ['nullable', 'date'],
            'period_end'   => ['nullable', 'date'],
        ]);

        $client = Client::find($data['client_id']);

        if (! $client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        $invoice = Invoice::updateOrCreate(
            ['external_id' => $data['external_id']],
            [
                'id'           => $data['external_id'],
                'client_id'    => $client->id,
                'amount'       => $data['amount'],
                'currency'     => 'NGN',
                'status'       => $data['status'],
                'due_date'     => $data['due_date'],
                'paid_at'      => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
                'period_start' => $data['period_start'] ?? null,
                'period_end'   => $data['period_end'] ?? null,
                'synced_at'    => now(),
            ]
        );

        return response()->json(['invoice' => $invoice]);
    }
}
