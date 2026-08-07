<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($permissions as $group => $items)
        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ str_replace('_', ' ', $group) }}</p>
            <div class="space-y-1.5">
                @foreach ($items as $permission)
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $permission->name }}"
                            @checked(in_array($permission->name, old('permissions', $assigned ?? [])))
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        {{ $permission->name }}
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
