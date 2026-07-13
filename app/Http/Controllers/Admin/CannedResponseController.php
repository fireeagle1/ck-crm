<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CannedResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CannedResponseController extends Controller
{
    public function index(): View
    {
        $responses = CannedResponse::orderBy('sort_order')->orderBy('title')->get();

        return view('admin.canned-responses.index', compact('responses'));
    }

    public function create(): View
    {
        return view('admin.canned-responses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'category' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        CannedResponse::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('admin.canned-responses.index')
            ->with('success', 'Canned response created.');
    }

    public function edit(CannedResponse $cannedResponse): View
    {
        return view('admin.canned-responses.edit', compact('cannedResponse'));
    }

    public function update(Request $request, CannedResponse $cannedResponse)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'category' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $cannedResponse->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('admin.canned-responses.index')
            ->with('success', 'Canned response updated.');
    }

    public function destroy(CannedResponse $cannedResponse)
    {
        $cannedResponse->delete();

        return redirect()->route('admin.canned-responses.index')
            ->with('success', 'Canned response deleted.');
    }
}
