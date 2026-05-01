<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee\EmployeeFeedback;
use Illuminate\Http\Request;

class FeedbackAdminController extends Controller
{
    public function update(Request $request, EmployeeFeedback $feedback)
    {
        $data = $request->validate([
            'status'     => 'required|in:open,in_progress,done,wontfix',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $feedback->update($data);

        return back()->with('success', 'Feedback aktualisiert.');
    }
}
