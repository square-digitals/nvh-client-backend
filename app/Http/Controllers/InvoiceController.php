<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = $request->user()
            ->invoices()
            ->latest()
            ->get();

        return response()->json(['invoices' => $invoices]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $invoice = $request->user()->invoices()->findOrFail($id);

        return response()->json(['invoice' => $invoice]);
    }
}
