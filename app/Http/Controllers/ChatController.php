<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChatController extends Controller
{
    private const FILE_RULES = ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,dwg,zip'];

    public function __construct(private readonly ConversationService $conversations) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $scope = in_array($request->get('scope'), ['direct', 'group', 'discussion'], true) ? $request->get('scope') : null;

        $conversationList = Conversation::query()
            ->whereIn('id', $user->conversationParticipations()->pluck('conversation_id'))
            ->when($scope, fn ($q) => $q->where('type', $scope))
            ->with(['participants.user', 'latestMessage'])
            ->get()
            ->sortByDesc(fn (Conversation $c) => $c->latestMessage?->created_at ?? $c->created_at)
            ->values();

        $activeConversation = null;
        $messages = collect();

        if ($request->filled('conversation')) {
            $activeConversation = Conversation::find($request->get('conversation'));
            if ($activeConversation && $activeConversation->hasParticipant($user)) {
                $activeConversation->load(['participants.user', 'subject']);
                $messages = $activeConversation->messages()->with(['user', 'media'])->get();
                $activeConversation->markReadFor($user);
            } else {
                $activeConversation = null;
            }
        }

        $assignableUsers = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Client'))
            ->orderBy('name')
            ->get();

        return view('chat.index', compact('conversationList', 'activeConversation', 'messages', 'assignableUsers', 'scope', 'user'));
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['count' => $request->user()->unreadConversationCount()]);
    }

    public function poll(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 403);

        $after = (int) $request->get('after', 0);
        $messages = $conversation->messages()->with(['user', 'media'])->where('id', '>', $after)->get();
        $conversation->markReadFor($user);

        return view('chat._message-list', ['messages' => $messages, 'user' => $user]);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => self::FILE_RULES,
        ]);

        abort_if(blank($data['body'] ?? null) && empty($request->file('files', [])), 422, 'Write a message or attach a file.');

        try {
            $this->conversations->postMessage($conversation, $user, $data['body'] ?? null, $request->file('files', []));
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return back();
    }

    public function clear(Conversation $conversation): RedirectResponse
    {
        foreach ($conversation->messages as $message) {
            $message->clearMediaCollection('attachments');
        }
        $conversation->messages()->delete();

        return back()->with('success', 'Discussion cleared.');
    }

    public function destroyMedia(Request $request, Conversation $conversation, Media $media): RedirectResponse
    {
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 403);

        $message = Message::find($media->model_id);
        abort_unless($message && (string) $media->model_id === (string) $message->id && $media->model_type === Message::class, 404);
        abort_unless($message->user_id === $user->id || $user->hasRole('Admin'), 403);

        $media->delete();

        return back()->with('success', 'Attachment removed.');
    }

    public function startDirect(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);

        $other = User::findOrFail($data['user_id']);
        $conversation = $this->conversations->directBetween($request->user(), $other);

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $conversation = Conversation::create([
            'type' => 'group',
            'name' => $data['name'],
            'created_by' => $request->user()->id,
        ]);

        $this->conversations->addParticipant($conversation, $request->user());
        foreach (User::whereIn('id', $data['user_ids'])->get() as $participant) {
            $this->conversations->addParticipant($conversation, $participant);
        }

        return redirect()->route('chat.index', ['conversation' => $conversation->id])->with('success', 'Group created.');
    }

    public function addParticipant(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeGroupManager($request, $conversation);

        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);
        $this->conversations->addParticipant($conversation, User::findOrFail($data['user_id']));

        return back()->with('success', 'Member added.');
    }

    public function removeParticipant(Request $request, Conversation $conversation, User $participant): RedirectResponse
    {
        $this->authorizeGroupManager($request, $conversation);

        $conversation->participants()->where('user_id', $participant->id)->delete();

        return back()->with('success', 'Member removed.');
    }

    public function leaveGroup(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->type === 'group', 404);

        $conversation->participants()->where('user_id', $request->user()->id)->delete();

        return redirect()->route('chat.index')->with('success', 'You left the group.');
    }

    private function authorizeGroupManager(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->type === 'group', 404);
        abort_unless(
            $conversation->created_by === $request->user()->id || $request->user()->hasRole('Admin'),
            403
        );
    }
}
