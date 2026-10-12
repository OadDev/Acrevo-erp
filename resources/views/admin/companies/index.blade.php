<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Companies" subtitle="Company profiles used when issuing Proforma Invoices, Tax Invoices, and Delivery Challans.">
            <x-slot name="actions">
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'add-company')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add Company</button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Tax Regime</th>
                        <th class="px-4 py-3">Tax ID</th>
                        <th class="px-4 py-3">Currency</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($companies as $company)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $company->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $company->code }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ strtoupper($company->tax_regime) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $company->taxId() ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $company->currency }}</td>
                            <td class="px-4 py-3"><x-badge :status="$company->is_active ? 'active' : 'inactive'" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button" x-data x-on:click="$dispatch('open-modal', 'edit-company-{{ $company->id }}')" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" class="inline" onsubmit="return confirm('Remove this company?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No companies yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @php($companyFields = fn ($company = null) => [
        'name' => $company->name ?? '', 'code' => $company->code ?? '', 'legal_name' => $company->legal_name ?? '',
        'email' => $company->email ?? '', 'phone' => $company->phone ?? '', 'address' => $company->address ?? '',
        'city' => $company->city ?? '', 'state' => $company->state ?? '', 'pincode' => $company->pincode ?? '', 'country' => $company->country ?? 'India',
        'gstin' => $company->gstin ?? '', 'vatin' => $company->vatin ?? '', 'pan' => $company->pan ?? '', 'website' => $company->website ?? '',
        'currency' => $company->currency ?? 'INR', 'tax_regime' => $company->tax_regime ?? 'gst',
        'bank_name' => $company->bank_name ?? '', 'bank_account_name' => $company->bank_account_name ?? '', 'bank_account_no' => $company->bank_account_no ?? '',
        'bank_ifsc_code' => $company->bank_ifsc_code ?? '', 'bank_swift_code' => $company->bank_swift_code ?? '', 'bank_branch' => $company->bank_branch ?? '',
    ])

    @foreach ([null, ...$companies->all()] as $company)
        <x-modal :name="$company ? 'edit-company-'.$company->id : 'add-company'" max-width="2xl">
            <form method="POST" action="{{ $company ? route('admin.companies.update', $company) : route('admin.companies.store') }}" class="p-6" enctype="multipart/form-data">
                @csrf
                @if ($company) @method('PUT') @endif
                <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $company ? 'Edit — '.$company->name : 'Add Company' }}</h3>

                @php($f = $companyFields($company))
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><x-input-label value="Name" /><x-text-input name="name" class="mt-1 block w-full" required value="{{ $f['name'] }}" /></div>
                    <div><x-input-label value="Code (used in document numbers)" /><x-text-input name="code" class="mt-1 block w-full" required value="{{ $f['code'] }}" /></div>
                    <div class="sm:col-span-2"><x-input-label value="Legal Name" /><x-text-input name="legal_name" class="mt-1 block w-full" value="{{ $f['legal_name'] }}" /></div>
                    <div><x-input-label value="Email" /><x-text-input type="email" name="email" class="mt-1 block w-full" value="{{ $f['email'] }}" /></div>
                    <div><x-input-label value="Phone" /><x-text-input name="phone" class="mt-1 block w-full" value="{{ $f['phone'] }}" /></div>
                    <div class="sm:col-span-2"><x-input-label value="Address" /><x-textarea-input name="address" rows="2" class="mt-1 block w-full">{{ $f['address'] }}</x-textarea-input></div>
                    <div><x-input-label value="City" /><x-text-input name="city" class="mt-1 block w-full" value="{{ $f['city'] }}" /></div>
                    <div><x-input-label value="State" /><x-text-input name="state" class="mt-1 block w-full" value="{{ $f['state'] }}" /></div>
                    <div><x-input-label value="Pincode" /><x-text-input name="pincode" class="mt-1 block w-full" value="{{ $f['pincode'] }}" /></div>
                    <div><x-input-label value="Country" /><x-text-input name="country" class="mt-1 block w-full" value="{{ $f['country'] }}" /></div>
                    <div>
                        <x-input-label value="Tax Regime" />
                        <x-select-input name="tax_regime" class="mt-1 block w-full">
                            <option value="gst" @selected($f['tax_regime'] === 'gst')>GST (India)</option>
                            <option value="vat" @selected($f['tax_regime'] === 'vat')>VAT (e.g. Oman)</option>
                        </x-select-input>
                    </div>
                    <div><x-input-label value="Currency" /><x-text-input name="currency" placeholder="INR, OMR, ..." class="mt-1 block w-full" required value="{{ $f['currency'] }}" /></div>
                    <div><x-input-label value="GSTIN" /><x-text-input name="gstin" class="mt-1 block w-full" value="{{ $f['gstin'] }}" /></div>
                    <div><x-input-label value="VATIN" /><x-text-input name="vatin" class="mt-1 block w-full" value="{{ $f['vatin'] }}" /></div>
                    <div><x-input-label value="PAN" /><x-text-input name="pan" class="mt-1 block w-full" value="{{ $f['pan'] }}" /></div>
                    <div><x-input-label value="Website" /><x-text-input name="website" class="mt-1 block w-full" value="{{ $f['website'] }}" /></div>
                    <div class="sm:col-span-2"><x-input-label value="Logo" /><input type="file" name="logo" accept="image/*" class="mt-1 block w-full text-sm"></div>
                </div>

                <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="mb-3 text-sm font-semibold text-gray-500">Bank Details (for PDF footer, optional)</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-input name="bank_name" placeholder="Bank Name" class="w-full text-sm" value="{{ $f['bank_name'] }}" />
                        <x-text-input name="bank_account_name" placeholder="Account Name" class="w-full text-sm" value="{{ $f['bank_account_name'] }}" />
                        <x-text-input name="bank_account_no" placeholder="Account No." class="w-full text-sm" value="{{ $f['bank_account_no'] }}" />
                        <x-text-input name="bank_branch" placeholder="Branch" class="w-full text-sm" value="{{ $f['bank_branch'] }}" />
                        <x-text-input name="bank_ifsc_code" placeholder="IFSC Code" class="w-full text-sm" value="{{ $f['bank_ifsc_code'] }}" />
                        <x-text-input name="bank_swift_code" placeholder="SWIFT Code" class="w-full text-sm" value="{{ $f['bank_swift_code'] }}" />
                    </div>
                </div>

                @if ($company)
                    <label class="mt-4 flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" @checked($company->is_active) class="rounded border-gray-300 text-indigo-600">
                        Active
                    </label>
                @endif

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-data x-on:click="$dispatch('close-modal', '{{ $company ? 'edit-company-'.$company->id : 'add-company' }}')" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                    <x-primary-button type="submit">{{ $company ? 'Save Changes' : 'Add Company' }}</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endforeach
</x-app-layout>
