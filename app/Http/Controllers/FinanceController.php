<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LedgerCategory;
use App\Models\Payment;
use App\Models\Site;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\WorkOrder;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $income = Payment::whereMonth('payment_date', $month)->whereYear('payment_date', $year)->sum('amount');
        $expenses = Expense::whereMonth('expense_date', $month)->whereYear('expense_date', $year)->sum('amount');
        $vendorPayments = VendorPayment::whereMonth('payment_date', $month)->whereYear('payment_date', $year)->sum('amount');

        $invoices = Invoice::with(['client', 'workOrder', 'payments'])->latest()->paginate(10, ['*'], 'invoices_page');

        $payments = Payment::with(['client', 'site', 'workOrder', 'invoice'])
            ->when($request->get('payment_client_id'), fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->get('payment_from'), fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($request->get('payment_to'), fn ($q, $v) => $q->whereDate('payment_date', '<=', $v))
            ->latest('payment_date')->latest('id')
            ->paginate(10, ['*'], 'payments_page');

        $vendorPaymentEntries = VendorPayment::with(['subContractor', 'workOrder'])
            ->when($request->get('vendor_user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->get('vendor_work_order_id'), fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($request->get('vendor_from'), fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($request->get('vendor_to'), fn ($q, $v) => $q->whereDate('payment_date', '<=', $v))
            ->latest('payment_date')->latest('id')
            ->paginate(10, ['*'], 'vendor_page');

        $recentExpenses = Expense::with(['paidBy', 'workOrder'])
            ->when($request->get('expense_category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->get('expense_type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->get('expense_work_order_id'), fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($request->get('expense_from'), fn ($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($request->get('expense_to'), fn ($q, $v) => $q->whereDate('expense_date', '<=', $v))
            ->latest('expense_date')->latest('id')
            ->paginate(10, ['*'], 'expenses_page');

        $clientUsers = User::whereHas('clientLogin')->with('clientLogin.client')->orderBy('name')->get();
        $clients = Client::orderBy('name')->get();
        $workOrders = WorkOrder::orderByDesc('created_at')->limit(100)->get();
        $sites = Site::orderBy('site_no')->get();
        $subContractors = User::role('Sub Contractor')->where('is_active', true)->orderBy('name')->get();
        $expenseCategories = Expense::query()->pluck('category')->filter()->unique()->sort()->values();
        $currentExpenseBalance = Expense::orderByDesc('expense_date')->orderByDesc('id')->value('balance') ?? 0;
        $ledgerCategories = LedgerCategory::orderBy('name')->get();

        return view('finance.index', compact(
            'income', 'expenses', 'vendorPayments', 'invoices', 'payments', 'vendorPaymentEntries',
            'recentExpenses', 'clientUsers', 'clients', 'workOrders', 'sites', 'subContractors',
            'expenseCategories', 'currentExpenseBalance', 'ledgerCategories', 'month', 'year'
        ));
    }

    public function myPayments(Request $request): View
    {
        $user = $request->user();

        $payments = VendorPayment::where('user_id', $user->id)
            ->with('workOrder')
            ->latest('payment_date')
            ->paginate(15);

        $totalReceived = VendorPayment::where('user_id', $user->id)->sum('amount');

        return view('finance.subcontractor', compact('payments', 'totalReceived'));
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $taxAmount = $data['tax_amount'] ?? 0;

        Invoice::create([
            'client_id' => $data['client_id'],
            'work_order_id' => $data['work_order_id'] ?? null,
            'amount' => $data['amount'],
            'tax_amount' => $taxAmount,
            'total_amount' => $data['amount'] + $taxAmount,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'sent',
            'issued_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Invoice created and sent to the client as a payment request.');
    }

    public function updateInvoiceStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,sent,paid,partial,overdue,cancelled'],
        ]);

        $invoice->update($data);

        return back()->with('success', 'Invoice status updated.');
    }

    public function destroyInvoice(Invoice $invoice): RedirectResponse
    {
        $this->authorizeAdminOnly();

        $invoice->delete();

        return back()->with('success', 'Invoice removed.');
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'mode' => ['required', 'in:cash,bank_transfer,upi,cheque,card'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ]);

        Payment::create($data + ['received_by' => $request->user()->id]);

        if (! empty($data['invoice_id'])) {
            $invoice = Invoice::find($data['invoice_id']);
            $paid = $invoice->payments()->sum('amount');
            $invoice->update(['status' => $paid >= $invoice->total_amount ? 'paid' : 'partial']);
        }

        return back()->with('success', 'Payment recorded.');
    }

    public function updatePayment(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'mode' => ['required', 'in:cash,bank_transfer,upi,cheque,card'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ]);

        $payment->update($data);

        if ($payment->invoice) {
            $paid = $payment->invoice->payments()->sum('amount');
            $payment->invoice->update(['status' => $paid >= $payment->invoice->total_amount ? 'paid' : 'partial']);
        }

        return back()->with('success', 'Payment updated.');
    }

    public function destroyPayment(Payment $payment): RedirectResponse
    {
        $invoice = $payment->invoice;

        $payment->delete();

        if ($invoice) {
            $paid = $invoice->payments()->sum('amount');
            $invoice->update(['status' => $paid >= $invoice->total_amount ? 'paid' : ($paid > 0 ? 'partial' : 'sent')]);
        }

        return back()->with('success', 'Payment removed.');
    }

    public function storeVendorPayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'mode' => ['required', 'in:cash,bank_transfer,upi,cheque,card'],
            'category' => ['nullable', 'string', 'max:150'],
            'remark' => ['nullable', 'string'],
        ]);

        $subContractor = User::findOrFail($data['user_id']);

        VendorPayment::create($data + [
            'vendor_name' => $subContractor->name,
            'paid_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Sub-contractor payment recorded.');
    }

    public function updateVendorPayment(Request $request, VendorPayment $vendorPayment): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'mode' => ['required', 'in:cash,bank_transfer,upi,cheque,card'],
            'category' => ['nullable', 'string', 'max:150'],
            'remark' => ['nullable', 'string'],
        ]);

        $subContractor = User::findOrFail($data['user_id']);

        $vendorPayment->update($data + ['vendor_name' => $subContractor->name]);

        return back()->with('success', 'Sub-contractor payment updated.');
    }

    public function destroyVendorPayment(VendorPayment $vendorPayment): RedirectResponse
    {
        $vendorPayment->delete();

        return back()->with('success', 'Sub-contractor payment removed.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'type' => ['required', 'in:credit,debit,borrow,lended'],
            'category' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'bill' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $expense = Expense::create([
            'work_order_id' => $data['work_order_id'] ?? null,
            'type' => $data['type'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'remark' => $data['remark'] ?? null,
            'amount' => $data['amount'],
            'balance' => 0,
            'expense_date' => $data['expense_date'],
            'paid_by' => $request->user()->id,
        ]);

        if ($request->hasFile('bill')) {
            try {
                $expense->addMediaFromRequest('bill')->toMediaCollection('bill');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['bill' => 'That file is too large (max 20MB).']);
            }
        }

        // A backdated entry shifts every running balance after it, not just
        // its own row, so recalculate the whole chain in date order rather
        // than assuming this entry is always the latest one.
        $this->recalculateExpenseBalances();

        return back()->with('success', 'Expense recorded.');
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'type' => ['required', 'in:credit,debit,borrow,lended'],
            'category' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'bill' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $expense->update([
            'work_order_id' => $data['work_order_id'] ?? null,
            'type' => $data['type'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'remark' => $data['remark'] ?? null,
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
        ]);

        if ($request->hasFile('bill')) {
            try {
                $expense->addMediaFromRequest('bill')->toMediaCollection('bill');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['bill' => 'That file is too large (max 20MB).']);
            }
        }

        $this->recalculateExpenseBalances();

        return back()->with('success', 'Expense updated.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $expense->delete();

        $this->recalculateExpenseBalances();

        return back()->with('success', 'Expense removed.');
    }

    public function invoicesPdf(Request $request)
    {
        $invoices = Invoice::with(['client', 'workOrder', 'payments'])->latest()->get();

        $pdf = Pdf::loadView('finance.invoices-pdf', compact('invoices'));

        return $pdf->download('invoices-'.now()->format('Y-m-d').'.pdf');
    }

    public function invoicesCsv(Request $request): StreamedResponse
    {
        $invoices = Invoice::with(['client', 'workOrder', 'payments'])->latest()->get();

        return response()->streamDownload(function () use ($invoices) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice No', 'Client', 'Work Order', 'Amount', 'Paid', 'Due', 'Status']);
            foreach ($invoices as $invoice) {
                fputcsv($out, [
                    $invoice->invoice_no,
                    $invoice->client?->name,
                    $invoice->workOrder?->work_order_no,
                    $invoice->total_amount,
                    $invoice->paidAmount(),
                    $invoice->balanceDue(),
                    $invoice->status,
                ]);
            }
            fclose($out);
        }, 'invoices-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function paymentsPdf(Request $request)
    {
        $filters = [
            'client_id' => $request->get('payment_client_id'),
            'from' => $request->get('payment_from'),
            'to' => $request->get('payment_to'),
        ];

        $payments = Payment::with(['client', 'site', 'workOrder'])
            ->when($filters['client_id'], fn ($q, $v) => $q->where('client_id', $v))
            ->when($filters['from'], fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($filters['to'], fn ($q, $v) => $q->whereDate('payment_date', '<=', $v))
            ->orderBy('payment_date')->orderBy('id')
            ->get();

        $client = $filters['client_id'] ? Client::find($filters['client_id']) : null;

        $pdf = Pdf::loadView('finance.payments-pdf', compact('payments', 'filters', 'client'));

        return $pdf->download('client-payments-'.now()->format('Y-m-d').'.pdf');
    }

    public function paymentsCsv(Request $request): StreamedResponse
    {
        $payments = Payment::with(['client', 'site', 'workOrder'])
            ->when($request->get('payment_client_id'), fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->get('payment_from'), fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($request->get('payment_to'), fn ($q, $v) => $q->whereDate('payment_date', '<=', $v))
            ->orderBy('payment_date')->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Client', 'Site', 'Work Order', 'Mode', 'Amount']);
            foreach ($payments as $payment) {
                fputcsv($out, [
                    $payment->payment_date->format('Y-m-d'),
                    $payment->client?->name,
                    $payment->site?->site_no,
                    $payment->workOrder?->work_order_no,
                    Str::title(str_replace('_', ' ', $payment->mode)),
                    $payment->amount,
                ]);
            }
            fclose($out);
        }, 'client-payments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function vendorPaymentsPdf(Request $request)
    {
        $filters = [
            'user_id' => $request->get('vendor_user_id'),
            'work_order_id' => $request->get('vendor_work_order_id'),
            'from' => $request->get('vendor_from'),
            'to' => $request->get('vendor_to'),
        ];

        $vendorPayments = VendorPayment::with(['subContractor', 'workOrder'])
            ->when($filters['user_id'], fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['work_order_id'], fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($filters['from'], fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($filters['to'], fn ($q, $v) => $q->whereDate('payment_date', '<=', $v))
            ->orderBy('payment_date')->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('finance.vendor-payments-pdf', compact('vendorPayments', 'filters'));

        return $pdf->download('sub-contractor-payments-'.now()->format('Y-m-d').'.pdf');
    }

    public function vendorPaymentsCsv(Request $request): StreamedResponse
    {
        $vendorPayments = VendorPayment::with(['subContractor', 'workOrder'])
            ->when($request->get('vendor_user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->get('vendor_work_order_id'), fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($request->get('vendor_from'), fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($request->get('vendor_to'), fn ($q, $v) => $q->whereDate('payment_date', '<=', $v))
            ->orderBy('payment_date')->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($vendorPayments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Sub Contractor', 'Work Order', 'Category', 'Amount', 'Remark']);
            foreach ($vendorPayments as $vp) {
                fputcsv($out, [
                    $vp->payment_date->format('Y-m-d'),
                    $vp->subContractor?->name ?? $vp->vendor_name,
                    $vp->workOrder?->work_order_no,
                    $vp->category,
                    $vp->amount,
                    $vp->remark,
                ]);
            }
            fclose($out);
        }, 'sub-contractor-payments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function expensesPdf(Request $request)
    {
        $filters = [
            'from' => $request->get('expense_from'),
            'to' => $request->get('expense_to'),
            'category' => $request->get('expense_category'),
            'type' => $request->get('expense_type'),
            'work_order_id' => $request->get('expense_work_order_id'),
        ];

        $expenses = Expense::with(['media', 'workOrder'])
            ->when($filters['category'], fn ($q, $v) => $q->where('category', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('type', $v))
            ->when($filters['work_order_id'], fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($filters['from'], fn ($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($filters['to'], fn ($q, $v) => $q->whereDate('expense_date', '<=', $v))
            ->orderBy('expense_date')->orderBy('id')
            ->get();

        $workOrder = $filters['work_order_id'] ? WorkOrder::find($filters['work_order_id']) : null;

        $pdf = Pdf::loadView('finance.expenses-pdf', compact('expenses', 'filters', 'workOrder'));

        return $pdf->download('expenses-'.now()->format('Y-m-d').'.pdf');
    }

    public function expensesCsv(Request $request): StreamedResponse
    {
        $expenses = Expense::with('workOrder')
            ->when($request->get('expense_category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->get('expense_type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->get('expense_work_order_id'), fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($request->get('expense_from'), fn ($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($request->get('expense_to'), fn ($q, $v) => $q->whereDate('expense_date', '<=', $v))
            ->orderBy('expense_date')->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($expenses) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Category', 'Description', 'Work Order', 'Borrow', 'Credit', 'Debit', 'Lended', 'Balance', 'Bill', 'Remark']);
            foreach ($expenses as $expense) {
                fputcsv($out, [
                    $expense->expense_date->format('Y-m-d'),
                    $expense->category,
                    $expense->description,
                    $expense->workOrder?->work_order_no,
                    $expense->type === 'borrow' ? $expense->amount : '',
                    $expense->type === 'credit' ? $expense->amount : '',
                    $expense->type === 'debit' ? $expense->amount : '',
                    $expense->type === 'lended' ? $expense->amount : '',
                    $expense->balance,
                    $expense->getFirstMedia('bill') ? 'Yes' : '',
                    $expense->remark,
                ]);
            }
            fclose($out);
        }, 'expenses-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function recalculateExpenseBalances(): void
    {
        $balance = 0;

        Expense::orderBy('expense_date')->orderBy('id')->get()->each(function (Expense $expense) use (&$balance) {
            $increasesBalance = in_array($expense->type, ['credit', 'borrow'], true);
            $balance = $increasesBalance ? $balance + $expense->amount : $balance - $expense->amount;
            $expense->updateQuietly(['balance' => $balance]);
        });
    }
}
