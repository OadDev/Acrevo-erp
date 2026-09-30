<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(): View
    {
        $items = Item::orderBy('name')->get();

        return view('admin.items.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hsn_sac_code' => ['nullable', 'string', 'max:30'],
            'unit' => ['required', 'string', 'max:30'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        Item::create($data + ['is_active' => true]);

        return redirect()->route('admin.items.index')->with('success', 'Item added.');
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hsn_sac_code' => ['nullable', 'string', 'max:30'],
            'unit' => ['required', 'string', 'max:30'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.items.index')->with('success', 'Item updated.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('admin.items.index')->with('success', 'Item removed.');
    }
}
