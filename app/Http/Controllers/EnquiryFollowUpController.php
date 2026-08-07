<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnquiryFollowUpController extends Controller
{
    public function store(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string'],
            'next_follow_up_date' => ['nullable', 'date'],
        ]);

        $enquiry->followUps()->create($data + [
            'user_id' => $request->user()->id,
            'status' => 'logged',
        ]);

        if (! empty($data['next_follow_up_date'])) {
            $enquiry->update(['follow_up_date' => $data['next_follow_up_date']]);
        }

        if ($enquiry->status === 'new') {
            $enquiry->update(['status' => 'contacted']);
        }

        return back()->with('success', 'Follow-up logged.');
    }
}
