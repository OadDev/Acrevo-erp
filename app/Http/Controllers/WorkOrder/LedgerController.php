<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        ]);

        $previousBalance = (float) ($workOrder->ledgers()->latest('id')->value('balance') ?? 0);
        $balance = $data['type'] === 'credit' ? $previousBalance + $data['amount'] : $previousBalance - $data['amount'];

        $workOrder->ledgers()->create($data + [
            'entry_date' => now()->toDateString(),
            'balance' => $balance,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Ledger entry recorded.');
    }
}
