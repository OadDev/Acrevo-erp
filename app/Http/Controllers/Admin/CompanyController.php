<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $companies = Company::orderBy('name')->get();

        return view('admin.companies.index', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        Company::create($data + ['is_active' => true]);

        return redirect()->route('admin.companies.index')->with('success', 'Company added.');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validated($request, $company);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.companies.index')->with('success', 'Company updated.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $inUse = $company->proformaInvoices()->exists() || $company->taxInvoices()->exists() || $company->deliveryChallans()->exists();
        abort_if($inUse, 422, 'This company has documents issued under it. Mark it inactive instead of removing it.');

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $company->delete();

        return redirect()->route('admin.companies.index')->with('success', 'Company removed.');
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('companies', 'code')->ignore($company?->id)],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_regime' => ['required', 'in:'.implode(',', Company::TAX_REGIMES)],
            'gstin' => ['nullable', 'string', 'max:30'],
            'vatin' => ['nullable', 'string', 'max:30'],
            'pan' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'bank_ifsc_code' => ['nullable', 'string', 'max:20'],
            'bank_swift_code' => ['nullable', 'string', 'max:20'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
