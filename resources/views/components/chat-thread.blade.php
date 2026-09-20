@props(['conversation', 'messages', 'compact' => false, 'storeUrl' => null, 'pollUrl' => null])

@php
    $lastId = optional($messages->last())->id ?? 0;
    $otherParticipants = $conversation->participants->pluck('user')->filter(fn ($u) => $u->id !== auth()->id());
    $storeUrl ??= route('conversations.messages.store', $conversation);
    $pollUrl ??= url('conversations').'/'.$conversation->id.'/poll';
@endphp

<div class="flex flex-col {{ $compact ? 'h-[420px]' : 'h-[65vh]' }} rounded-xl border border-gray-200 dark:border-gray-800">
    <div id="chat-messages-{{ $conversation->id }}" data-last-id="{{ $lastId }}" class="flex-1 overflow-y-auto p-4">
        @include('chat._message-list', ['messages' => $messages, 'user' => auth()->user()])
    </div>

    @error('files')<p class="border-t border-rose-100 bg-rose-50 px-4 py-2 text-xs text-rose-600 dark:border-rose-500/20 dark:bg-rose-500/10">{{ $message }}</p>@enderror

    <form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" class="border-t border-gray-200 p-3 dark:border-gray-800" x-data="{ files: 0 }">
        @csrf
        @if ($otherParticipants->isNotEmpty())
            <div class="mb-2 flex flex-wrap gap-1.5">
                @foreach ($otherParticipants as $participant)
                    <button type="button" class="chat-mention-chip rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-indigo-100 hover:text-indigo-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-indigo-500/20" data-mention-name="{{ $participant->name }}" data-mention-target="composer-{{ $conversation->id }}">{{ '@'.$participant->name }}</button>
                @endforeach
            </div>
        @endif
        <div class="flex items-end gap-2">
            <label class="cursor-pointer rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800" title="Attach files">
                <x-icon name="paperclip" class="h-5 w-5" />
                <input type="file" name="files[]" multiple class="hidden" @change="files = $event.target.files.length">
            </label>
            <textarea id="composer-{{ $conversation->id }}" name="body" rows="1" placeholder="Type a message..." class="max-h-28 flex-1 resize-none rounded-lg border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); this.form.requestSubmit();}"></textarea>
            <button type="submit" class="shrink-0 rounded-lg bg-indigo-600 p-2.5 text-white hover:bg-indigo-500">
                <x-icon name="send" class="h-4 w-4" />
            </button>
        </div>
        <p class="mt-1 text-[11px] text-gray-400" x-show="files > 0" x-text="files + ' file(s) selected'"></p>
    </form>
</div>

<script>
    (function () {
        var conversationId = {{ $conversation->id }};
        var box = document.getElementById('chat-messages-' + conversationId);
        if (!box || box.dataset.pollingBound) return;
        box.dataset.pollingBound = '1';
        box.scrollTop = box.scrollHeight;

        var pollUrl = @json($pollUrl);

        function poll() {
            var after = box.dataset.lastId || 0;
            fetch(pollUrl + '?after=' + after, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) { return r.ok ? r.text() : ''; })
                .then(function (html) {
                    if (!html || !html.trim()) return;
                    var wasAtBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;
                    box.insertAdjacentHTML('beforeend', html);
                    var rows = box.querySelectorAll('[data-message-id]');
                    if (rows.length) box.dataset.lastId = rows[rows.length - 1].dataset.messageId;
                    if (wasAtBottom) box.scrollTop = box.scrollHeight;
                })
                .catch(function () {});
        }

        var interval = setInterval(poll, 10000);
        window.addEventListener('beforeunload', function () { clearInterval(interval); });

        if (!document.body.dataset.mentionChipsBound) {
            document.body.dataset.mentionChipsBound = '1';
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.chat-mention-chip');
                if (!btn) return;
                var target = document.getElementById(btn.dataset.mentionTarget);
                if (!target) return;
                target.value = target.value.replace(/\s*$/, ' ') + '@' + btn.dataset.mentionName + ' ';
                target.focus();
            });
        }
    })();
</script>
