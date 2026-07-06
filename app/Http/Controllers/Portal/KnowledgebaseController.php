<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgebaseController extends Controller
{
    public function index(Request $request): View
    {
        $query = Article::where(function ($q) use ($request) {
            $q->where('is_public', true)
              ->orWhere('company_id', $request->user()->company_id);
        });

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $articles = $query->orderByDesc('created_at')->paginate(12)->withQueryString();

        // Get distinct categories for the filter tabs
        $categories = Article::where(function ($q) use ($request) {
            $q->where('is_public', true)
              ->orWhere('company_id', $request->user()->company_id);
        })
        ->whereNotNull('category')
        ->distinct()
        ->pluck('category')
        ->sort()
        ->values();

        return view('portal.knowledgebase.index', compact('articles', 'categories'));
    }

    public function show(Article $article): View
    {
        return view('portal.knowledgebase.show', compact('article'));
    }
}
