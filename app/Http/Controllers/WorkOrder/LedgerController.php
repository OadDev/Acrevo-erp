<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class LedgerController extends Controller
{
    public function index(WorkOrder $workOrder): RedirectResponse
    {
        return redirect()->route('work-orders.show', $workOrder);
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:credit,debit'],
            'category' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bill' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $previousBalance = (float) ($workOrder->ledgers()->latest('id')->value('balance') ?? 0);
        $balance = $data['type'] === 'credit' ? $previousBalance + $data['amount'] : $previousBalance - $data['amount'];

        $ledger = $workOrder->ledgers()->create([
            'type' => $data['type'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'entry_date' => now()->toDateString(),
            'balance' => $balance,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('bill')) {
            try {
                $ledger->addMediaFromRequest('bill')->toMediaCollection('bill');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['bill' => 'That file is too large (max 20MB).']);
            }
        }

        return back()->with('success', 'Ledger entry recorded.');
    }
}
