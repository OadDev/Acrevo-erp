<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Communication" subtitle="Chat with your team, or start a group.">
            <x-slot name="actions">
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'new-chat')" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">New Chat</button>
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'new-group')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    <x-icon name="plus" class="mr-1 inline h-4 w-4" />New Group
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card :padded="false" class="lg:col-span-1">
            <div class="flex gap-1 border-b border-gray-100 p-2 dark:border-gray-800">
                @foreach (['' => 'All', 'direct' => 'Chats', 'group' => 'Groups', 'discussion' => 'Discussions'] as $value => $label)
                    <a href="{{ route('chat.index', array_filter(['scope' => $value ?: null])) }}" class="rounded-lg px-2.5 py-1 text-xs font-medium {{ $scope === $value || (! $scope && $value === '') ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="max-h-[70vh] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
                @forelse ($conversationList as $conversation)
                    @php
                        $unread = $conversation->unreadCountFor($user);
                        $preview = $conversation->latestMessage;
                    @endphp
                    <a href="{{ route('chat.index', array_filter(['scope' => $scope, 'conversation' => $conversation->id])) }}" class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/40 {{ $activeConversation?->id === $conversation->id ? 'bg-indigo-50 dark:bg-indigo-500/10' : '' }}">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                            @if ($conversation->type === 'group')
                                <x-icon name="users-round" class="h-4 w-4" />
                            @elseif ($conversation->type === 'discussion')
                                <x-icon name="message-circle" class="h-4 w-4" />
                            @else
                                {{ strtoupper(substr($conversation->displayNameFor($user), 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate font-medium text-gray-800 dark:text-gray-200">{{ $conversation->displayNameFor($user) }}</p>
                                @if ($preview)
                                    <span class="shrink-0 text-[10px] text-gray-400">{{ $preview->created_at->diffForHumans(null, true) }}</span>
                                @endif
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-xs text-gray-400">{{ $preview ? ($preview->body ?: 'Sent an attachment') : 'No messages yet' }}</p>
                                @if ($unread > 0)
                                    <span class="flex h-4 min-w-[1rem] shrink-0 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-semibold text-white">{{ $unread }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="p-6"><x-empty-state icon="message-circle" title="No conversations yet" description="Start a chat or create a group to get going." /></div>
                @endforelse
            </div>
        </x-card>

        <div class="lg:col-span-2">
            @if ($activeConversation)
                <x-card class="mb-3" x-data="{ managing: false }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $activeConversation->displayNameFor($user) }}</h3>
                            @if ($activeConversation->type !== 'direct')
                                <p class="text-xs text-gray-400">{{ $activeConversation->participants->count() }} member(s): {{ $activeConversation->participants->pluck('user.name')->filter()->join(', ') }}</p>
                            @endif
                            @if ($activeConversation->type === 'discussion' && $activeConversation->subject)
                                <p class="text-xs text-indigo-500">Linked to {{ class_basename($activeConversation->subject_type) }}</p>
                            @endif
                        </div>
                        @if ($activeConversation->type === 'group')
                            <div class="flex items-center gap-2">
                                <button type="button" @click="managing = !managing" class="text-xs font-medium text-indigo-600 hover:underline">Manage Members</button>
                                <form method="POST" action="{{ route('conversations.leave', $activeConversation) }}" onsubmit="return confirm('Leave this group?')">
                                    @csrf
                                    <button class="text-xs font-medium text-rose-600 hover:underline">Leave Group</button>
                                </form>
                            </div>
                        @endif
                    </div>

                    @if ($activeConversation->type === 'group')
                        <div x-show="managing" x-cloak class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($activeConversation->participants as $participant)
                                    <span class="flex items-center gap-1 rounded-full bg-gray-100 py-0.5 pl-2.5 pr-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $participant->user?->name }}
                                        @if ($activeConversation->created_by === $user->id || $user->hasRole('Admin'))
                                            <form method="POST" action="{{ route('conversations.participants.destroy', [$activeConversation, $participant->user_id]) }}" onsubmit="return confirm('Remove {{ $participant->user?->name }} from the group?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-full p-0.5 text-gray-400 hover:bg-rose-100 hover:text-rose-600"><x-icon name="x" class="h-3 w-3" /></button>
                                            </form>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                            @if ($activeConversation->created_by === $user->id || $user->hasRole('Admin'))
                                <form method="POST" action="{{ route('conversations.participants.store', $activeConversation) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <x-select-input name="user_id" class="flex-1 text-xs" required>
                                        <option value="">Add member…</option>
                                        @foreach ($assignableUsers->whereNotIn('id', $activeConversation->participants->pluck('user_id')) as $candidate)
                                            <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Add</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </x-card>

                <x-chat-thread :conversation="$activeConversation" :messages="$messages" />
            @else
                <x-card class="flex h-[65vh] items-center justify-center">
                    <x-empty-state icon="message-circle" title="Select a conversation" description="Pick a chat from the list, or start a new one." />
                </x-card>
            @endif
        </div>
    </div>

    <x-modal name="new-chat" max-width="sm">
        <form method="POST" action="{{ route('chat.direct') }}" class="p-6">
            @csrf
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Start a Chat</h2>
            <div class="mt-4">
                <x-input-label for="new_chat_user_id" value="Select Person" />
                <x-select-input id="new_chat_user_id" name="user_id" class="mt-1 block w-full" required>
                    <option value="">Choose a user…</option>
                    @foreach ($assignableUsers as $candidate)
                        <option value="{{ $candidate->id }}">{{ $candidate->name }} @if ($candidate->designation) ({{ $candidate->designation }}) @endif</option>
                    @endforeach
                </x-select-input>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                <x-primary-button>Start Chat</x-primary-button>
            </div>
        </form>
    </x-modal>

    <x-modal name="new-group">
        <form method="POST" action="{{ route('chat.groups.store') }}" class="p-6">
            @csrf
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Create Group</h2>
            <div class="mt-4">
                <x-input-label for="group_name" value="Group Name" />
                <x-text-input id="group_name" name="name" class="mt-1 block w-full" required />
            </div>
            <div class="mt-4">
                <x-input-label value="Members" />
                <div class="mt-1 grid max-h-56 grid-cols-2 gap-1.5 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    @foreach ($assignableUsers as $candidate)
                        <label class="flex items-center gap-1.5 text-sm">
                            <input type="checkbox" name="user_ids[]" value="{{ $candidate->id }}" class="rounded border-gray-300 text-indigo-600">
                            {{ $candidate->name }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                <x-primary-button>Create Group</x-primary-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
