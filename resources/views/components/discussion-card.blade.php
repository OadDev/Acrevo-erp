@props(['conversation', 'title' => 'Discussion'])

<x-card :padded="false">
    <div class="border-b border-gray-100 p-4 dark:border-gray-800">
        <h3 class="text-sm font-semibold text-gray-500">{{ $title }}</h3>
        <p class="text-xs text-gray-400">Internal notes and conversation about this record - not visible to the client.</p>
    </div>
    <div class="p-3">
        <x-chat-thread :conversation="$conversation" :messages="$conversation->messages" compact />
    </div>
</x-card>
