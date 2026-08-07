<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $client = $request->user()->client();

        abort_unless($client, 403, 'No client account linked to this login.');

        $invoices = Invoice::where('client_id', $client->id)->with('payments')->latest()->paginate(10);

        return view('portal.invoices.index', compact('invoices'));
    }
}
