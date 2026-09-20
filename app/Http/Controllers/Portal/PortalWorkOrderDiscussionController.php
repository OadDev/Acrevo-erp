<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class PortalWorkOrderDiscussionController extends Controller
{
    private const FILE_RULES = ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,dwg,zip'];

    public function __construct(private readonly ConversationService $conversations) {}

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorizeDiscussionAccess($request, $workOrder);

        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => self::FILE_RULES,
        ]);

        abort_if(blank($data['body'] ?? null) && empty($request->file('files', [])), 422, 'Write a message or attach a file.');

        $discussion = $this->conversations->discussionFor($workOrder, $request->user());

        try {
            $this->conversations->postMessage($discussion, $request->user(), $data['body'] ?? null, $request->file('files', []));
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return back();
    }

    public function poll(Request $request, WorkOrder $workOrder): View
    {
        $this->authorizeDiscussionAccess($request, $workOrder);

        $discussion = $this->conversations->discussionFor($workOrder, $request->user());

        $after = (int) $request->get('after', 0);
        $messages = $discussion->messages()->with(['user', 'media'])->where('id', '>', $after)->get();
        $discussion->markReadFor($request->user());

        return view('chat._message-list', ['messages' => $messages, 'user' => $request->user()]);
    }

    private function authorizeDiscussionAccess(Request $request, WorkOrder $workOrder): void
    {
        $this->authorize('view', $workOrder);

        $client = $request->user()->client();
        abort_unless($client && $workOrder->client_id === $client->id && $client->canAccessWorkOrderDiscussion(), 403);
    }
}
