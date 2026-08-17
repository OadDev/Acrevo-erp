<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\CompanyLedger;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class CompanyLedgerController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorizeFinanceOrAdmin();

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'type' => ['required', 'in:credit,debit,borrow,lended'],
            'category' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bill' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $previousBalance = (float) ($workOrder->companyLedgers()->latest('id')->value('balance') ?? 0);
        $increasesBalance = in_array($data['type'], ['credit', 'borrow'], true);
        $balance = $increasesBalance ? $previousBalance + $data['amount'] : $previousBalance - $data['amount'];

        $ledger = $workOrder->companyLedgers()->create([
            'entry_date' => $data['entry_date'],
            'type' => $data['type'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'remark' => $data['remark'] ?? null,
            'amount' => $data['amount'],
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

        return back()->with('success', 'Company ledger entry recorded.');
    }

    public function update(Request $request, WorkOrder $workOrder, CompanyLedger $companyLedger): RedirectResponse
    {
        $this->authorizeFinanceOrAdmin();

        abort_unless($companyLedger->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'type' => ['required', 'in:credit,debit,borrow,lended'],
            'category' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bill' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $companyLedger->update([
            'entry_date' => $data['entry_date'],
            'type' => $data['type'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'remark' => $data['remark'] ?? null,
            'amount' => $data['amount'],
        ]);

        if ($request->hasFile('bill')) {
            try {
                $companyLedger->addMediaFromRequest('bill')->toMediaCollection('bill');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['bill' => 'That file is too large (max 20MB).']);
            }
        }

        $this->recalculateBalances($workOrder);

        return back()->with('success', 'Company ledger entry updated.');
    }

    public function destroy(WorkOrder $workOrder, CompanyLedger $companyLedger): RedirectResponse
    {
        $this->authorizeFinanceOrAdmin();

        abort_unless($companyLedger->work_order_id === $workOrder->id, 404);

        $companyLedger->delete();

        $this->recalculateBalances($workOrder);

        return back()->with('success', 'Company ledger entry removed.');
    }

    private function recalculateBalances(WorkOrder $workOrder): void
    {
        $balance = 0;

        $workOrder->companyLedgers()->orderBy('entry_date')->orderBy('id')->get()->each(function (CompanyLedger $ledger) use (&$balance) {
            $increasesBalance = in_array($ledger->type, ['credit', 'borrow'], true);
            $balance = $increasesBalance ? $balance + $ledger->amount : $balance - $ledger->amount;
            $ledger->updateQuietly(['balance' => $balance]);
        });
    }

    public function export(Request $request, WorkOrder $workOrder): StreamedResponse
    {
        $this->authorizeFinanceOrAdmin();

        $entries = $workOrder->companyLedgers()
            ->when($request->get('from'), fn ($q, $from) => $q->whereDate('entry_date', '>=', $from))
            ->when($request->get('to'), fn ($q, $to) => $q->whereDate('entry_date', '<=', $to))
            ->when($request->get('category'), fn ($q, $category) => $q->where('category', $category))
            ->when($request->get('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $filename = 'company-ledger-'.$workOrder->work_order_no.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Category', 'Description', 'Borrow', 'Credit', 'Debit', 'Lended', 'Balance', 'Bill', 'Remark']);
            foreach ($entries as $entry) {
                fputcsv($out, [
                    $entry->entry_date->format('Y-m-d'),
                    $entry->category,
                    $entry->description,
                    $entry->type === 'borrow' ? $entry->amount : '',
                    $entry->type === 'credit' ? $entry->amount : '',
                    $entry->type === 'debit' ? $entry->amount : '',
                    $entry->type === 'lended' ? $entry->amount : '',
                    $entry->balance,
                    $entry->getFirstMedia('bill') ? 'Yes' : '',
                    $entry->remark,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
