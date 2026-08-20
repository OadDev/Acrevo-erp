<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Generate Work Order" :subtitle="$quotation?->quotation_no" />
    </x-slot>

    <x-card class="max-w-5xl">
        <form method="POST" action="{{ route('work-orders.store') }}" x-data="{
            executionWay: '{{ old('execution_way') }}',
            materials: [{ material_name:'', brand:'', size:'', unit:'Nos', quantity:'', rate:'', vendor:'' }],
            labour: [{ labour_type:'', count:1, hours:'', wage_rate:'' }],
            equipment: [{ item_name:'', unit:'Nos', quantity:'', rate:'', vendor:'' }],
            transport: [{ item_name:'', unit:'Trip', quantity:'', rate:'', vendor:'' }],
            misc: [{ item_name:'', unit:'Nos', quantity:'', rate:'', vendor:'' }],
            procedures: [{ item_description:'', length:'', breadth:'', height:'', quantity:'', unit:'Sqft' }],
            timeSchedules: [{ time_to_finish:'', unit:'', remark:'' }],
            addMaterial() { this.materials.push({ material_name:'', brand:'', size:'', unit:'Nos', quantity:'', rate:'', vendor:'' }); },
            removeMaterial(i) { this.materials.splice(i, 1); },
            addLabour() { this.labour.push({ labour_type:'', count:1, hours:'', wage_rate:'' }); },
            removeLabour(i) { this.labour.splice(i, 1); },
            addEquipment() { this.equipment.push({ item_name:'', unit:'Nos', quantity:'', rate:'', vendor:'' }); },
            removeEquipment(i) { this.equipment.splice(i, 1); },
            addTransport() { this.transport.push({ item_name:'', unit:'Trip', quantity:'', rate:'', vendor:'' }); },
            removeTransport(i) { this.transport.splice(i, 1); },
            addMisc() { this.misc.push({ item_name:'', unit:'Nos', quantity:'', rate:'', vendor:'' }); },
            removeMisc(i) { this.misc.splice(i, 1); },
            addProcedure() { this.procedures.push({ item_description:'', length:'', breadth:'', height:'', quantity:'', unit:'Sqft' }); },
            removeProcedure(i) { this.procedures.splice(i, 1); },
            addTimeSchedule() { this.timeSchedules.push({ time_to_finish:'', unit:'', remark:'' }); },
            removeTimeSchedule(i) { this.timeSchedules.splice(i, 1); },
            get materialTotal() { return this.materials.reduce((sum, m) => sum + ((parseFloat(m.quantity) || 0) * (parseFloat(m.rate) || 0)), 0); },
            get labourTotal() { return this.labour.reduce((sum, l) => sum + ((parseFloat(l.count) || 0) * (parseFloat(l.wage_rate) || 0)), 0); },
            get equipmentTotal() { return this.equipment.reduce((sum, e) => sum + ((parseFloat(e.quantity) || 0) * (parseFloat(e.rate) || 0)), 0); },
            get transportTotal() { return this.transport.reduce((sum, t) => sum + ((parseFloat(t.quantity) || 0) * (parseFloat(t.rate) || 0)), 0); },
            get miscTotal() { return this.misc.reduce((sum, x) => sum + ((parseFloat(x.quantity) || 0) * (parseFloat(x.rate) || 0)), 0); },
            get totalBudget() { return this.materialTotal + this.labourTotal + this.equipmentTotal + this.transportTotal + this.miscTotal; }
        }">
            @csrf
            <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">
            <input type="hidden" name="client_id" value="{{ $quotation->client_id }}">
            <input type="hidden" name="site_id" value="{{ $site->id }}">
            @if ($parentWorkOrder)
                <input type="hidden" name="parent_work_order_id" value="{{ $parentWorkOrder->id }}">
                <div class="mb-6 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                    Next work order for <strong>{{ $parentWorkOrder->client?->name }}</strong>, continuing from <strong>{{ $parentWorkOrder->work_order_no }}</strong>.
                </div>
            @endif
            <div class="mb-6 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                Generating from approved quotation for <strong>{{ $quotation->client->name }}</strong> — Total ₹{{ number_format($quotation->total_amount, 2) }}
            </div>

            <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Site ID (auto-generated)</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $site->site_no }}</p>
            </div>

            <h3 class="mb-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Site Details</h3>
            <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="site_address" value="Address" />
                    <x-text-input id="site_address" name="site_address" class="mt-1 block w-full" value="{{ old('site_address', $site->address) }}" />
                </div>

                <div>
                    <x-input-label for="site_city" value="City" />
                    <x-text-input id="site_city" name="site_city" class="mt-1 block w-full" value="{{ old('site_city', $site->city) }}" />
                </div>

                <div>
                    <x-input-label for="site_state" value="State" />
                    <x-text-input id="site_state" name="site_state" class="mt-1 block w-full" value="{{ old('site_state', $site->state) }}" />
                </div>

                <div>
                    <x-input-label for="site_pincode" value="Pincode" />
                    <x-text-input id="site_pincode" name="site_pincode" class="mt-1 block w-full" value="{{ old('site_pincode', $site->pincode) }}" />
                </div>

                <div>
                    <x-input-label for="construction_site_location" value="Construction Site Location" />
                    <x-text-input id="construction_site_location" name="construction_site_location" class="mt-1 block w-full" placeholder="e.g. Maps link or landmark" value="{{ old('construction_site_location', $site->construction_site_location) }}" />
                </div>

                <div>
                    <x-input-label for="client_living_location" value="Client Living Location" />
                    <x-text-input id="client_living_location" name="client_living_location" class="mt-1 block w-full" placeholder="Where the client currently lives" value="{{ old('client_living_location', $site->client_living_location) }}" />
                </div>

                <div>
                    <x-input-label for="site_contact_name" value="Site Contact Name" />
                    <x-text-input id="site_contact_name" name="site_contact_name" class="mt-1 block w-full" value="{{ old('site_contact_name', $site->site_contact_name) }}" />
                </div>

                <div>
                    <x-input-label for="site_contact_phone" value="Site Contact Phone" />
                    <x-text-input id="site_contact_phone" name="site_contact_phone" class="mt-1 block w-full" value="{{ old('site_contact_phone', $site->site_contact_phone) }}" />
                </div>
            </div>

            <h3 class="mb-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Work Order Details</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="title" value="Work Order Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title', $quotation?->enquiry?->service_type) }}" required />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="scope" value="Scope of Work" />
                    <x-textarea-input id="scope" name="scope" rows="3" class="mt-1 block w-full">{{ old('scope') }}</x-textarea-input>
                </div>

                <div class="sm:col-span-2">
                    <h3 class="mb-2 text-sm font-semibold text-gray-500">Work Procedure &amp; Work Schedule M.Book</h3>
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-3 py-2">Work Name / Procedure</th>
                                    <th class="px-3 py-2 w-20">Length (L)</th>
                                    <th class="px-3 py-2 w-20">Breadth (B)</th>
                                    <th class="px-3 py-2 w-24">Dp/Tk/Ht</th>
                                    <th class="px-3 py-2 w-20">Total</th>
                                    <th class="px-3 py-2 w-28">Units (sft/cft/rft)</th>
                                    <th class="px-2 py-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(p, index) in procedures" :key="index">
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="px-3 py-2"><input type="text" :name="'procedures['+index+'][item_description]'" x-model="p.item_description" placeholder="Work name / procedure" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                        <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'procedures['+index+'][length]'" x-model.number="p.length" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                        <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'procedures['+index+'][breadth]'" x-model.number="p.breadth" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                        <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'procedures['+index+'][height]'" x-model.number="p.height" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                        <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'procedures['+index+'][quantity]'" x-model.number="p.quantity" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                        <td class="px-3 py-2"><input type="text" :name="'procedures['+index+'][unit]'" x-model="p.unit" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                        <td class="px-2 py-2 text-center">
                                            <button type="button" @click="removeProcedure(index)" class="text-gray-400 hover:text-rose-500">
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" @click="addProcedure" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        <x-icon name="plus" class="h-4 w-4" /> Add Work Procedure
                    </button>
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="execution_way" value="Execution Method" />
                    <x-select-input id="execution_way" name="execution_way" class="mt-1 block w-full" x-model="executionWay" required>
                        <option value="" disabled selected>Select how this work will be carried out</option>
                        @foreach (\App\Models\WorkOrder::EXECUTION_WAYS as $value => $label)
                            <option value="{{ $value }}" @selected(old('execution_way') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div class="sm:col-span-2" x-show="executionWay === 'way_1'" x-cloak>
                    <x-input-label for="team_leader_id" value="Assign Executive Team Leader" />
                    <x-select-input id="team_leader_id" name="team_leader_id" class="mt-1 block w-full">
                        <option value="">Assign later from the work order's Team tab</option>
                        @foreach ($teamLeaders as $leader)
                            <option value="{{ $leader->id }}" @selected(old('team_leader_id') == $leader->id)>{{ $leader->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="priority" value="Priority" />
                    <x-select-input id="priority" name="priority" class="mt-1 block w-full">
                        @foreach (['low', 'medium', 'high', 'urgent'] as $p)
                            <option value="{{ $p }}" @selected($p === 'medium')>{{ ucfirst($p) }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="start_date" value="Start Date" />
                    <x-text-input id="start_date" type="date" name="start_date" class="mt-1 block w-full" />
                </div>

                <div>
                    <x-input-label for="deadline" value="Deadline" />
                    <x-text-input id="deadline" type="date" name="deadline" class="mt-1 block w-full" />
                </div>
            </div>

            <div class="mt-8 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">Total Budget</h3>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white" x-text="'₹' + totalBudget.toFixed(2)"></p>
                <dl class="mt-2 flex flex-wrap gap-6 text-sm">
                    <div><dt class="text-gray-400">Material Specifications</dt><dd class="font-medium text-gray-800 dark:text-gray-200" x-text="'₹' + materialTotal.toFixed(2)"></dd></div>
                    <div><dt class="text-gray-400">Man Power Schedule</dt><dd class="font-medium text-gray-800 dark:text-gray-200" x-text="'₹' + labourTotal.toFixed(2)"></dd></div>
                    <div><dt class="text-gray-400">Equipment / Machinery</dt><dd class="font-medium text-gray-800 dark:text-gray-200" x-text="'₹' + equipmentTotal.toFixed(2)"></dd></div>
                    <div><dt class="text-gray-400">Transport</dt><dd class="font-medium text-gray-800 dark:text-gray-200" x-text="'₹' + transportTotal.toFixed(2)"></dd></div>
                    <div><dt class="text-gray-400">Miscellaneous / Contingency</dt><dd class="font-medium text-gray-800 dark:text-gray-200" x-text="'₹' + miscTotal.toFixed(2)"></dd></div>
                </dl>
            </div>

            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold text-gray-500">Using Material Specifications</h3>
                <p class="mb-2 text-xs text-gray-400">Allocated material budget for this work order — actual site purchases are recorded later in the Material Inward tab.</p>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">Material</th>
                                <th class="px-3 py-2">Brand</th>
                                <th class="px-3 py-2">Size</th>
                                <th class="px-3 py-2 w-20">Unit</th>
                                <th class="px-3 py-2 w-24">Qty</th>
                                <th class="px-3 py-2 w-28">Cost/Unit</th>
                                <th class="px-3 py-2">Vendor</th>
                                <th class="px-3 py-2 w-28 text-right">Amount</th>
                                <th class="px-2 py-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(m, index) in materials" :key="index">
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2"><input type="text" :name="'materials['+index+'][material_name]'" x-model="m.material_name" placeholder="Material name" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'materials['+index+'][brand]'" x-model="m.brand" placeholder="Optional" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'materials['+index+'][size]'" x-model="m.size" placeholder="Optional" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'materials['+index+'][unit]'" x-model="m.unit" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'materials['+index+'][quantity]'" x-model.number="m.quantity" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'materials['+index+'][rate]'" x-model.number="m.rate" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'materials['+index+'][vendor]'" x-model="m.vendor" placeholder="Optional" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300" x-text="'₹' + (((parseFloat(m.quantity) || 0) * (parseFloat(m.rate) || 0)).toFixed(2))"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeMaterial(index)" class="text-gray-400 hover:text-rose-500">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" @click="addMaterial" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    <x-icon name="plus" class="h-4 w-4" /> Add Material
                </button>
            </div>

            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold text-gray-500">Man Power Schedule Book</h3>
                <p class="mb-2 text-xs text-gray-400">Allocated man power budget for this work order — actual site usage is recorded later in the Used Man Power Budget tab.</p>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">Designation</th>
                                <th class="px-3 py-2 w-20">Nos</th>
                                <th class="px-3 py-2 w-28">Target Hrs</th>
                                <th class="px-3 py-2 w-28">Salary</th>
                                <th class="px-3 py-2 w-28 text-right">Amount</th>
                                <th class="px-2 py-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(l, index) in labour" :key="index">
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2"><input type="text" :name="'labour['+index+'][labour_type]'" x-model="l.labour_type" placeholder="Worker designation" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" min="1" :name="'labour['+index+'][count]'" x-model.number="l.count" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.5" min="0" :name="'labour['+index+'][hours]'" x-model.number="l.hours" placeholder="Optional" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'labour['+index+'][wage_rate]'" x-model.number="l.wage_rate" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300" x-text="'₹' + (((parseFloat(l.count) || 0) * (parseFloat(l.wage_rate) || 0)).toFixed(2))"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeLabour(index)" class="text-gray-400 hover:text-rose-500">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" @click="addLabour" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    <x-icon name="plus" class="h-4 w-4" /> Add Man Power
                </button>
            </div>

            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold text-gray-500">Equipment / Machinery</h3>
                <p class="mb-2 text-xs text-gray-400">Allocated equipment/machinery budget for this work order.</p>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">Equipment</th>
                                <th class="px-3 py-2 w-20">Unit</th>
                                <th class="px-3 py-2 w-24">Qty</th>
                                <th class="px-3 py-2 w-28">Cost/Unit</th>
                                <th class="px-3 py-2">Vendor</th>
                                <th class="px-3 py-2 w-28 text-right">Amount</th>
                                <th class="px-2 py-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(e, index) in equipment" :key="index">
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2"><input type="text" :name="'equipment['+index+'][item_name]'" x-model="e.item_name" placeholder="Equipment name" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'equipment['+index+'][unit]'" x-model="e.unit" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'equipment['+index+'][quantity]'" x-model.number="e.quantity" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'equipment['+index+'][rate]'" x-model.number="e.rate" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'equipment['+index+'][vendor]'" x-model="e.vendor" placeholder="Optional" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300" x-text="'₹' + (((parseFloat(e.quantity) || 0) * (parseFloat(e.rate) || 0)).toFixed(2))"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeEquipment(index)" class="text-gray-400 hover:text-rose-500">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" @click="addEquipment" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    <x-icon name="plus" class="h-4 w-4" /> Add Equipment
                </button>
            </div>

            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold text-gray-500">Transport</h3>
                <p class="mb-2 text-xs text-gray-400">Allocated transport budget for this work order.</p>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">Transport Item</th>
                                <th class="px-3 py-2 w-20">Unit</th>
                                <th class="px-3 py-2 w-24">Qty</th>
                                <th class="px-3 py-2 w-28">Cost/Unit</th>
                                <th class="px-3 py-2">Vendor</th>
                                <th class="px-3 py-2 w-28 text-right">Amount</th>
                                <th class="px-2 py-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(t, index) in transport" :key="index">
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2"><input type="text" :name="'transport['+index+'][item_name]'" x-model="t.item_name" placeholder="e.g. Material delivery" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'transport['+index+'][unit]'" x-model="t.unit" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'transport['+index+'][quantity]'" x-model.number="t.quantity" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'transport['+index+'][rate]'" x-model.number="t.rate" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'transport['+index+'][vendor]'" x-model="t.vendor" placeholder="Optional" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300" x-text="'₹' + (((parseFloat(t.quantity) || 0) * (parseFloat(t.rate) || 0)).toFixed(2))"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeTransport(index)" class="text-gray-400 hover:text-rose-500">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" @click="addTransport" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    <x-icon name="plus" class="h-4 w-4" /> Add Transport
                </button>
            </div>

            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold text-gray-500">Miscellaneous / Contingency</h3>
                <p class="mb-2 text-xs text-gray-400">Any other allocated budget for this work order — contingency, permits, site facilities, etc.</p>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">Item</th>
                                <th class="px-3 py-2 w-20">Unit</th>
                                <th class="px-3 py-2 w-24">Qty</th>
                                <th class="px-3 py-2 w-28">Cost/Unit</th>
                                <th class="px-3 py-2">Vendor</th>
                                <th class="px-3 py-2 w-28 text-right">Amount</th>
                                <th class="px-2 py-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(x, index) in misc" :key="index">
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2"><input type="text" :name="'misc['+index+'][item_name]'" x-model="x.item_name" placeholder="e.g. Contingency" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'misc['+index+'][unit]'" x-model="x.unit" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'misc['+index+'][quantity]'" x-model.number="x.quantity" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="number" step="0.01" min="0" :name="'misc['+index+'][rate]'" x-model.number="x.rate" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'misc['+index+'][vendor]'" x-model="x.vendor" placeholder="Optional" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300" x-text="'₹' + (((parseFloat(x.quantity) || 0) * (parseFloat(x.rate) || 0)).toFixed(2))"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeMisc(index)" class="text-gray-400 hover:text-rose-500">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" @click="addMisc" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    <x-icon name="plus" class="h-4 w-4" /> Add Item
                </button>
            </div>

            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold text-gray-500">Time Schedule</h3>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">Schedule Time to Finish</th>
                                <th class="px-3 py-2 w-28">Units</th>
                                <th class="px-3 py-2">Remarks</th>
                                <th class="px-2 py-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(t, index) in timeSchedules" :key="index">
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2"><input type="text" :name="'time_schedules['+index+'][time_to_finish]'" x-model="t.time_to_finish" placeholder="e.g. 10" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'time_schedules['+index+'][unit]'" x-model="t.unit" placeholder="Days" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-3 py-2"><input type="text" :name="'time_schedules['+index+'][remark]'" x-model="t.remark" placeholder="Optional" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeTimeSchedule(index)" class="text-gray-400 hover:text-rose-500">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" @click="addTimeSchedule" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    <x-icon name="plus" class="h-4 w-4" /> Add Time Schedule
                </button>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-primary-button>Generate Work Order</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
