<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/80 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/80 sm:px-6">
    <button @click="$dispatch('toggle-sidebar')" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:hidden">
        <x-icon name="menu" class="h-5 w-5" />
    </button>

    <form action="{{ route('search') }}" method="GET" class="hidden flex-1 max-w-md sm:block">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
                type="search"
                name="q"
                placeholder="Search clients, work orders, tickets..."
                class="w-full rounded-lg border-gray-200 bg-gray-50 py-2 pl-9 text-sm focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:focus:bg-gray-800"
            >
        </div>
    </form>

    <div class="ml-auto flex items-center gap-2">
        <button
            x-data
            @click="
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            "
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
            title="Toggle dark mode"
        >
            <x-icon name="sun" class="h-5 w-5 dark:hidden" />
            <x-icon name="moon" class="hidden h-5 w-5 dark:block" />
        </button>

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" @click.outside="open = false" class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800" title="Notifications">
                <x-icon name="bell" class="h-5 w-5" />
                @php($unread = auth()->user()->unreadNotifications()->count())
                @if ($unread > 0)
                    <span class="absolute -right-1 -top-1 flex h-4.5 min-w-[1.125rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-white dark:ring-gray-900">
                        {{ $unread > 9 ? '9+' : $unread }}
                    </span>
                @endif
            </button>
            <div x-show="open" x-cloak x-transition class="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        Notifications
                        @if ($unread > 0)
                            <span class="ml-1 text-xs font-medium text-gray-400">&middot; {{ $unread }} new</span>
                        @endif
                    </p>
                    @if ($unread > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Mark all read</button>
                        </form>
                    @endif
                </div>

                <div class="max-h-80 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                    @forelse (auth()->user()->notifications()->latest()->take(8)->get() as $notification)
                        @php($isUnread = is_null($notification->read_at))
                        <a href="{{ route('notifications.read', $notification->id) }}"
                            class="flex items-start gap-2.5 px-4 py-3 text-sm transition hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $isUnread ? 'bg-indigo-600' : 'bg-transparent' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block {{ $isUnread ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-300' }}">
                                    {{ $notification->data['message'] ?? 'Notification' }}
                                </span>
                                <span class="mt-0.5 block text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="flex flex-col items-center gap-2 px-4 py-10 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-700/60">
                                <x-icon name="bell" class="h-5 w-5" />
                            </span>
                            <p class="text-sm text-gray-400">You're all caught up.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 rounded-lg p-1.5 pr-2.5 hover:bg-gray-100 dark:hover:bg-gray-800">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <span class="hidden text-sm font-medium text-gray-700 dark:text-gray-200 sm:block">{{ auth()->user()->name }}</span>
                <x-icon name="chevron-down" class="hidden h-4 w-4 text-gray-400 sm:block" />
            </button>
            <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-52 rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <p class="px-2.5 py-2 text-xs text-gray-400">{{ auth()->user()->getRoleNames()->join(', ') }}</p>
                <a href="{{ route('profile.edit') }}" class="block rounded-lg px-2.5 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Profile Settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-lg px-2.5 py-2 text-left text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Log Out</button>
                </form>
            </div>
        </div>
    </div>
</header>
