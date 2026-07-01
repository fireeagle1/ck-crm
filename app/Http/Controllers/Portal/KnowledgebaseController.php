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
        $articles = Article::where(function ($q) use ($request) {
            $q->where('is_public', true)
              ->orWhere('company_id', $request->user()->company_id);
        })
        ->orderByDesc('created_at')
        ->paginate(12);

        return view('portal.knowledgebase.index', compact('articles'));
    }

    public function show(Article $article): View
    {
        return view('portal.knowledgebase.show', compact('article'));
    }
}
