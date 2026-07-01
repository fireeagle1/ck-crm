<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(): View
    {
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.communications.index', compact('customers'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $html = view('emails.broadcast', [
            'subject' => $request->input('subject'),
            'emailBody' => $request->input('body'),
            'recipientName' => 'Preview Customer',
        ])->render();

        return response($html);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'recipients' => 'required|in:all,selected',
            'customer_ids' => 'required_if:recipients,selected|array',
            'customer_ids.*' => 'exists:customers,company_id',
        ]);

        $query = User::whereNotNull('email');

        if ($validated['recipients'] === 'selected') {
            $query->whereIn('company_id', $validated['customer_ids']);
        }

        $users = $query->get();
        $sentCount = 0;

        foreach ($users as $user) {
            try {
                Mail::send('emails.broadcast', [
                    'subject' => $validated['subject'],
                    'emailBody' => $validated['body'],
                    'recipientName' => $user->first_name ?? 'there',
                ], function ($message) use ($user, $validated) {
                    $message->to($user->email, $user->full_name)
                            ->subject($validated['subject']);
                });

                $sentCount++;
            } catch (\Exception $e) {
                \Log::warning("Failed to send email to {$user->email}: {$e->getMessage()}");
            }
        }

        return back()->with('success', "Email sent to {$sentCount} recipient(s).");
    }
}
