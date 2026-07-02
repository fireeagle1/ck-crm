<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        $articles = Article::with('customer')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.articles.index', compact('articles'));
    }

    public function create(): View
    {
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.articles.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string|max:100',
            'company_id' => 'nullable|exists:customers,company_id',
            'is_public' => 'boolean',
        ]);

        // Sanitize HTML content - allow safe formatting tags only
        $validated['content'] = strip_tags(
            $validated['content'],
            '<p><br><strong><b><em><i><u><a><ul><ol><li><h1><h2><h3><blockquote><pre><code><del><sub><sup><span><div>'
        );

        Article::create($validated);

        return redirect()->route('admin.articles.index')
            ->with('success', 'Article created.');
    }

    public function edit(Article $article): View
    {
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.articles.edit', compact('article', 'customers'));
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string|max:100',
            'company_id' => 'nullable|exists:customers,company_id',
            'is_public' => 'boolean',
        ]);

        // Sanitize HTML content - allow safe formatting tags only
        $validated['content'] = strip_tags(
            $validated['content'],
            '<p><br><strong><b><em><i><u><a><ul><ol><li><h1><h2><h3><blockquote><pre><code><del><sub><sup><span><div>'
        );

        $article->update($validated);

        return redirect()->route('admin.articles.index')
            ->with('success', 'Article updated.');
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return redirect()->route('admin.articles.index')
            ->with('success', 'Article deleted.');
    }
}
