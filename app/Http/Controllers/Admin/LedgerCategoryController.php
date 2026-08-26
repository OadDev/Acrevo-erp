<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LedgerCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:ledger_categories,name'],
        ]);

        LedgerCategory::create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Category added.');
    }

    public function destroy(LedgerCategory $ledgerCategory): RedirectResponse
    {
        $ledgerCategory->delete();

        return back()->with('success', 'Category removed.');
    }
}
