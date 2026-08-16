<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscountCodeController extends Controller
{
    public function index(): View
    {
        $discountCodes = DiscountCode::orderByDesc('created_at')->paginate(20);

        return view('admin.shop.discount-codes.index', compact('discountCodes'));
    }

    public function create(): View
    {
        return view('admin.shop.discount-codes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:discount_codes,code',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->boolean('is_active', true);

        DiscountCode::create($validated);

        return redirect()->route('admin.shop.discount-codes.index')
            ->with('success', 'Discount code created successfully.');
    }

    public function edit(DiscountCode $discountCode): View
    {
        return view('admin.shop.discount-codes.edit', compact('discountCode'));
    }

    public function update(Request $request, DiscountCode $discountCode): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:discount_codes,code,' . $discountCode->id,
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0.01',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->boolean('is_active', true);

        $discountCode->update($validated);

        return redirect()->route('admin.shop.discount-codes.index')
            ->with('success', 'Discount code updated successfully.');
    }

    public function destroy(DiscountCode $discountCode): RedirectResponse
    {
        $discountCode->delete();

        return redirect()->route('admin.shop.discount-codes.index')
            ->with('success', 'Discount code deleted.');
    }
}
