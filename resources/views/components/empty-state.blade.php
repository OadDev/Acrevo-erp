@props(['icon' => 'inbox', 'title' => 'Nothing here yet', 'description' => null])

<div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
        <x-icon :name="$icon" class="h-6 w-6" />
    </div>
    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-400">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-4">{{ $actions }}</div>
    @endisset
</div>
