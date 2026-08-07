<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\VendorPayment;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $income = Payment::whereMonth('payment_date', $month)->whereYear('payment_date', $year)->sum('amount');
        $expenses = Expense::whereMonth('expense_date', $month)->whereYear('expense_date', $year)->sum('amount');
        $vendorPayments = VendorPayment::whereMonth('payment_date', $month)->whereYear('payment_date', $year)->sum('amount');

        $invoices = Invoice::with('client')->latest()->limit(10)->get();
        $payments = Payment::with('client')->latest()->limit(10)->get();
        $recentExpenses = Expense::latest()->limit(10)->get();
        $clients = Client::orderBy('name')->get();
        $workOrders = WorkOrder::orderByDesc('created_at')->limit(50)->get();

        return view('finance.index', compact(
            'income', 'expenses', 'vendorPayments', 'invoices', 'payments',
            'recentExpenses', 'clients', 'workOrders', 'month', 'year'
        ));
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_order_id' => ['required', 'exists:work_orders,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        Invoice::create($data + [
            'total_amount' => $data['amount'] + ($data['tax_amount'] ?? 0),
            'status' => 'sent',
            'issued_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Invoice created.');
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
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

    public function storeVendorPayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'mode' => ['required', 'in:cash,bank_transfer,upi,cheque,card'],
            'category' => ['nullable', 'string', 'max:150'],
        ]);

        VendorPayment::create($data + ['paid_by' => $request->user()->id]);

        return back()->with('success', 'Vendor payment recorded.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'category' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
        ]);

        Expense::create($data + ['paid_by' => $request->user()->id]);

        return back()->with('success', 'Expense recorded.');
    }
}
