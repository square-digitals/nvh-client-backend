<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceSyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id'    => ['required', 'string'],
            'external_id'  => ['required', 'string'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'currency'     => ['required', 'string', 'size:3'],
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
                'client_id'    => $client->id,
                'amount'       => $data['amount'],
                'currency'     => strtoupper($data['currency']),
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
