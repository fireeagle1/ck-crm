<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainController extends Controller
{
    public function index(Request $request): View
    {
        $domains = Domain::where('company_id', $request->user()->company_id)
            ->orderBy('expiry_date')
            ->paginate(10);

        return view('portal.domains.index', compact('domains'));
    }
}
