<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$subcontractor->name" :subtitle="$subcontractor->email">
            <x-slot name="actions">
                @if ($subcontractor->subcontractorProfile?->is_verified)
                    <x-badge color="emerald" class="text-sm">Authorised Subcontractor</x-badge>
                    @can('subcontractors.manage')
                        <form method="POST" action="{{ route('subcontractors.unverify', $subcontractor) }}" onsubmit="return confirm('Revoke the Authorised Subcontractor badge?')">
                            @csrf
                            @method('POST')
                            <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Revoke Authorisation</button>
                        </form>
                    @endcan
                @else
                    <x-badge color="gray" class="text-sm">Not Verified</x-badge>
                    @can('subcontractors.manage')
                        <form method="POST" action="{{ route('subcontractors.verify', $subcontractor) }}" onsubmit="return confirm('Mark this subcontractor as an Authorised Subcontractor?')">
                            @csrf
                            <x-primary-button>Mark as Authorised Subcontractor</x-primary-button>
                        </form>
                    @endcan
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Subcontractor Details</h3>
            @can('subcontractors.manage')
                <form method="POST" action="{{ route('subcontractors.update', $subcontractor) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    @php($profile = $subcontractor->subcontractorProfile)

                    <div>
                        <x-input-label for="company_name" value="Company / Firm Name" />
                        <x-text-input id="company_name" name="company_name" class="mt-1 block w-full" value="{{ old('company_name', $profile?->company_name) }}" />
                    </div>
                    <div>
                        <x-input-label for="contact_person" value="Contact Person" />
                        <x-text-input id="contact_person" name="contact_person" class="mt-1 block w-full" value="{{ old('contact_person', $profile?->contact_person) }}" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" class="mt-1 block w-full" value="{{ old('phone', $profile?->phone ?? $subcontractor->phone) }}" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" type="email" name="email" class="mt-1 block w-full" value="{{ old('email', $profile?->email ?? $subcontractor->email) }}" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Address" />
                        <x-text-input id="address" name="address" class="mt-1 block w-full" value="{{ old('address', $profile?->address) }}" />
                    </div>
                    <div>
                        <x-input-label for="city" value="City" />
                        <x-text-input id="city" name="city" class="mt-1 block w-full" value="{{ old('city', $profile?->city) }}" />
                    </div>
                    <div>
                        <x-input-label for="state" value="State" />
                        <x-text-input id="state" name="state" class="mt-1 block w-full" value="{{ old('state', $profile?->state) }}" />
                    </div>
                    <div>
                        <x-input-label for="pincode" value="Pincode" />
                        <x-text-input id="pincode" name="pincode" class="mt-1 block w-full" value="{{ old('pincode', $profile?->pincode) }}" />
                    </div>
                    <div>
                        <x-input-label for="specialization" value="Specialization / Trade" />
                        <x-text-input id="specialization" name="specialization" placeholder="e.g. Electrical, Plumbing, RCC" class="mt-1 block w-full" value="{{ old('specialization', $profile?->specialization) }}" />
                    </div>
                    <div>
                        <x-input-label for="gst_number" value="GST Number" />
                        <x-text-input id="gst_number" name="gst_number" class="mt-1 block w-full" value="{{ old('gst_number', $profile?->gst_number) }}" />
                    </div>
                    <div>
                        <x-input-label for="pan_number" value="PAN Number" />
                        <x-text-input id="pan_number" name="pan_number" class="mt-1 block w-full" value="{{ old('pan_number', $profile?->pan_number) }}" />
                    </div>
                    <div>
                        <x-input-label for="bank_name" value="Bank Name" />
                        <x-text-input id="bank_name" name="bank_name" class="mt-1 block w-full" value="{{ old('bank_name', $profile?->bank_name) }}" />
                    </div>
                    <div>
                        <x-input-label for="bank_account_no" value="Bank Account No." />
                        <x-text-input id="bank_account_no" name="bank_account_no" class="mt-1 block w-full" value="{{ old('bank_account_no', $profile?->bank_account_no) }}" />
                    </div>
                    <div>
                        <x-input-label for="bank_ifsc" value="Bank IFSC" />
                        <x-text-input id="bank_ifsc" name="bank_ifsc" class="mt-1 block w-full" value="{{ old('bank_ifsc', $profile?->bank_ifsc) }}" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Verification Notes" />
                        <x-textarea-input id="notes" name="notes" rows="3" class="mt-1 block w-full">{{ old('notes', $profile?->notes) }}</x-textarea-input>
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label value="{{ $profile ? 'Add More Documents' : 'Documents (KYC, ID proof, agreements, etc.)' }}" />
                        <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="mt-1 block w-full text-sm">
                        @error('files')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    @if ($profile && $profile->media->isNotEmpty())
                        <div class="sm:col-span-2">
                            <x-input-label value="Uploaded Documents" />
                            <div class="mt-1 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                                @foreach ($profile->media as $file)
                                    <div class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                        <a href="{{ $file->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                                            <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $file->file_name }}</span>
                                        </a>
                                        <form method="POST" action="{{ route('subcontractors.media.destroy', [$subcontractor, $file]) }}" onsubmit="return confirm('Remove this file?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="shrink-0 text-xs font-medium text-rose-600 hover:underline">Remove</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="sm:col-span-2 flex justify-end">
                        <x-primary-button>Save Details</x-primary-button>
                    </div>
                </form>
            @else
                @php($profile = $subcontractor->subcontractorProfile)
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-400">Company</dt><dd class="text-gray-800 dark:text-gray-200">{{ $profile?->company_name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Contact Person</dt><dd class="text-gray-800 dark:text-gray-200">{{ $profile?->contact_person ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Phone</dt><dd class="text-gray-800 dark:text-gray-200">{{ $profile?->phone ?? $subcontractor->phone ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Specialization</dt><dd class="text-gray-800 dark:text-gray-200">{{ $profile?->specialization ?? '—' }}</dd></div>
                </dl>

                @if ($profile && $profile->media->isNotEmpty())
                    <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <p class="mb-2 text-sm font-medium text-gray-500">Documents</p>
                        <div class="divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                            @foreach ($profile->media as $file)
                                <a href="{{ $file->getUrl() }}" target="_blank" class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                    <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                                    <span class="truncate text-gray-700 dark:text-gray-300">{{ $file->file_name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endcan

            @if ($profile?->is_verified)
                <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-400 dark:border-gray-800">
                    Verified by {{ $profile->verifiedBy?->name ?? '—' }} on {{ optional($profile->verified_at)->format('d M Y') }}
                </p>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card :padded="false">
                <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Assigned Sites</h3></div>
                @can('subcontractors.manage')
                    <form method="POST" action="{{ route('subcontractors.assign-site', $subcontractor) }}" class="flex gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
                        @csrf
                        <x-select-input name="site_id" class="flex-1 text-sm" required>
                            <option value="">Select a site…</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->site_no }} — {{ $site->client?->name }}</option>
                            @endforeach
                        </x-select-input>
                        <x-primary-button>Assign</x-primary-button>
                    </form>
                @endcan
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($assignedSites as $assignment)
                        <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                            <div>
                                <p class="text-gray-800 dark:text-gray-200">{{ $assignment->site?->site_no ?? 'Removed site' }}</p>
                                <p class="text-xs text-gray-400">{{ $assignment->site?->client?->name }}</p>
                            </div>
                            @can('subcontractors.manage')
                                <form method="POST" action="{{ route('subcontractors.unassign-site', [$subcontractor, $assignment]) }}" onsubmit="return confirm('Unassign this site?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-medium text-rose-600 hover:underline">Unassign</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-400">No sites assigned yet.</p>
                    @endforelse
                </div>
            </x-card>

            <x-card :padded="false">
                <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Assigned Work Orders</h3></div>
                @can('subcontractors.manage')
                    <form method="POST" action="{{ route('subcontractors.assign-work-order', $subcontractor) }}" class="flex gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
                        @csrf
                        <x-select-input name="work_order_id" class="flex-1 text-sm" required>
                            <option value="">Select a work order…</option>
                            @foreach ($workOrders as $wo)
                                <option value="{{ $wo->id }}">{{ $wo->work_order_no }} — {{ $wo->title }}</option>
                            @endforeach
                        </x-select-input>
                        <x-primary-button>Assign</x-primary-button>
                    </form>
                @endcan
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($assignedWorkOrders as $assignment)
                        <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                            <div>
                                <p class="text-gray-800 dark:text-gray-200">{{ $assignment->workOrder?->work_order_no ?? 'Removed WO' }}</p>
                                <p class="text-xs text-gray-400">{{ $assignment->workOrder?->title }}</p>
                            </div>
                            @can('subcontractors.manage')
                                <form method="POST" action="{{ route('subcontractors.unassign-work-order', [$subcontractor, $assignment]) }}" onsubmit="return confirm('Unassign this work order?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-medium text-rose-600 hover:underline">Unassign</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-400">No work orders assigned yet.</p>
                    @endforelse
                </div>
            </x-card>

            <x-card :padded="false">
                <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Recent Payments</h3></div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($payments as $payment)
                        <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                            <span class="text-gray-500">{{ $payment->payment_date->format('d M Y') }}</span>
                            <span class="font-medium text-emerald-600">₹{{ number_format($payment->amount, 2) }}</span>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-400">No payments recorded yet.</p>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
