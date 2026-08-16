<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\FulfilmentStageService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private AvailabilityService $availabilityService,
        private NotificationService $notificationService,
        private FulfilmentStageService $fulfilmentStageService,
    ) {}

    /**
     * List all bookings with customer, product, dates, status.
     * Filter by status.
     *
     * Requirements: 16.1, 19.1, 19.2
     */
    public function index(Request $request): View
    {
        $query = Booking::with('product', 'customer');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by customer
        if ($request->filled('customer')) {
            $query->where('company_id', $request->input('customer'));
        }

        // Filter by product
        if ($request->filled('product')) {
            $query->where('product_id', $request->input('product'));
        }

        $bookings = $query->orderByDesc('created_at')->paginate(20);

        $customers = Customer::orderBy('company_name')->get();
        $products = Product::where('product_type', 'equipment_rental')
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        return view('admin.shop.bookings.index', compact('bookings', 'customers', 'products'));
    }

    /**
     * Show booking detail — redirects to the unified order page.
     */
    public function show(Booking $booking): RedirectResponse
    {
        $booking->loadMissing('orderItem.order');

        if ($booking->orderItem?->order) {
            return redirect()->route('admin.shop.orders.show', $booking->orderItem->order);
        }

        // Fallback: if no linked order, show the legacy view
        $booking->load('product', 'customer', 'orderItem.order');

        return redirect()->route('admin.shop.bookings.index');
    }

    /**
     * Mark a booking as returned, trigger notification, and advance fulfilment stage.
     *
     * Requirements: 7.3, 3.9
     */
    public function markReturned(Booking $booking): RedirectResponse
    {
        $this->bookingService->markReturned($booking);

        // Advance fulfilment stage to 'returned' if booking is at 'checked_out' stage
        if ($booking->fulfilment_stage === 'checked_out') {
            try {
                $this->fulfilmentStageService->advance($booking, 'returned');
            } catch (\InvalidArgumentException $e) {
                // Log but don't block the return — the booking status is already updated
                Log::warning('Could not advance fulfilment stage on markReturned', [
                    'booking_id' => $booking->id,
                    'current_stage' => $booking->fulfilment_stage,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->notificationService->notifyCustomerReturnConfirmed($booking);

        return redirect()->route('admin.shop.bookings.show', $booking)
            ->with('success', 'Booking marked as returned. Customer has been notified.');
    }

    /**
     * Show form for manual booking creation.
     *
     * Requirements: 16.1, 16.2
     */
    public function create(): View
    {
        $customers = Customer::orderBy('company_name')->get();
        $products = Product::where('product_type', 'equipment_rental')
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        return view('admin.shop.bookings.create', compact('customers', 'products'));
    }

    /**
     * Validate and create a booking manually without a payment/order.
     * Creates Order, OrderItem, and Booking in a single transaction.
     *
     * Requirements: 16.3, 16.4, 16.5, 16.6, 16.7
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:customers,company_id',
            'product_id' => 'required|exists:products,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'quantity' => 'required|integer|min:1',
            'paid_offline' => 'nullable|boolean',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $quantity = (int) $validated['quantity'];

        // Validate minimum rental period
        $days = $startDate->diffInDays($endDate) + 1;
        if ($product->min_rental_days && $days < $product->min_rental_days) {
            return redirect()->back()->withInput()->withErrors([
                'start_date' => "Minimum rental period is {$product->min_rental_days} days.",
            ]);
        }

        // Validate availability using same rules as portal checkout (Req 16.3)
        if (!$this->availabilityService->isAvailable($product, $startDate, $endDate, $quantity)) {
            return redirect()->back()->withInput()->withErrors([
                'start_date' => 'The selected dates are not available for the requested quantity.',
            ]);
        }

        $paymentStatus = $request->boolean('paid_offline') ? 'paid_offline' : 'pending';
        $totalPrice = $this->bookingService->calculateTotal($product, $startDate, $endDate, $quantity);

        // Create Order, OrderItem, and Booking within a single transaction with pessimistic lock
        try {
            DB::transaction(function () use ($validated, $product, $startDate, $endDate, $quantity, $paymentStatus, $totalPrice) {
                // Pessimistic lock: prevent concurrent overbookings
                Booking::forProduct($product->id)
                    ->where(function ($q) {
                        $q->where('status', 'confirmed')
                          ->orWhere('status', 'active');
                    })
                    ->overlapping($startDate, $endDate)
                    ->lockForUpdate()
                    ->get();

                // Re-check availability inside the lock
                if (!$this->availabilityService->isAvailable($product, $startDate, $endDate, $quantity)) {
                    throw new \InvalidArgumentException('The selected dates are no longer available for the requested quantity.');
                }

                $order = Order::create([
                    'company_id' => $validated['company_id'],
                    'payment_status' => $paymentStatus,
                    'fulfilment_status' => 'completed',
                    'total_amount' => $totalPrice,
                ]);

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_type' => $product->product_type,
                    'price' => $totalPrice,
                    'billing_frequency' => null,
                    'quantity' => $quantity,
                    'rental_start_date' => $startDate,
                    'rental_end_date' => $endDate,
                ]);

                $booking = Booking::create([
                    'order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'company_id' => $validated['company_id'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'quantity' => $quantity,
                    'total_price' => $totalPrice,
                    'status' => 'confirmed',
                    'fulfilment_stage' => 'ordered',
                ]);

                // Link booking to order item
                $orderItem->update(['booking_id' => $booking->id]);
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->withErrors([
                'start_date' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.shop.bookings.index')
            ->with('success', 'Booking created successfully.');
    }

    /**
     * Display the calendar/grid view of rental bookings.
     */
    public function calendar(Request $request): View
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $products = Product::where('product_type', 'equipment_rental')
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        $bookings = Booking::with('customer')
            ->whereIn('product_id', $products->pluck('id'))
            ->where('start_date', '<=', $endOfMonth)
            ->where('end_date', '>=', $startOfMonth)
            ->get();

        // Group bookings by product_id
        $bookingsByProduct = $bookings->groupBy('product_id');

        $daysInMonth = $startOfMonth->daysInMonth;

        return view('admin.shop.bookings.calendar', compact(
            'products',
            'bookingsByProduct',
            'startOfMonth',
            'endOfMonth',
            'daysInMonth',
            'month',
            'year'
        ));
    }

    /**
     * Block dates for a product (mark as unavailable).
     */
    public function blockDates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        // Check for conflicts before blocking
        $conflicts = Booking::forProduct($product->id)
            ->where(function ($q) {
                $q->where('status', 'confirmed')
                  ->orWhere('status', 'active');
            })
            ->overlapping($startDate, $endDate)
            ->where('company_id', '!=', null) // exclude other blocks
            ->exists();

        if ($conflicts) {
            return redirect()->back()->withErrors([
                'start_date' => 'There are existing bookings in this date range. Cannot block these dates.',
            ]);
        }

        DB::transaction(function () use ($product, $startDate, $endDate, $validated) {
            Booking::create([
                'order_item_id' => null,
                'product_id' => $product->id,
                'company_id' => null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'quantity' => $product->stock_quantity ?? 1,
                'total_price' => 0,
                'status' => 'confirmed',
            ]);
        });

        return redirect()->route('admin.shop.bookings.calendar', [
            'month' => $startDate->month,
            'year' => $startDate->year,
        ])->with('success', 'Dates blocked successfully.' . ($validated['reason'] ? ' Reason: ' . $validated['reason'] : ''));
    }

    /**
     * Update a blocked date range.
     */
    public function updateBlock(Request $request, Booking $booking): RedirectResponse
    {
        // Only allow editing blocks (bookings with no company_id)
        if ($booking->company_id !== null) {
            abort(403, 'Only blocked dates can be edited here.');
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        // Check for conflicts with real bookings (exclude the current block)
        $conflicts = Booking::forProduct($booking->product_id)
            ->where('id', '!=', $booking->id)
            ->where(function ($q) {
                $q->where('status', 'confirmed')
                  ->orWhere('status', 'active');
            })
            ->overlapping($startDate, $endDate)
            ->where('company_id', '!=', null)
            ->exists();

        if ($conflicts) {
            return redirect()->back()->withErrors([
                'start_date' => 'There are existing bookings in this date range. Cannot block these dates.',
            ]);
        }

        $booking->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return redirect()->route('admin.shop.bookings.calendar', [
            'month' => $startDate->month,
            'year' => $startDate->year,
        ])->with('success', 'Block updated successfully.');
    }

    /**
     * Delete a blocked date range.
     */
    public function deleteBlock(Booking $booking): RedirectResponse
    {
        // Only allow deleting blocks (bookings with no company_id)
        if ($booking->company_id !== null) {
            abort(403, 'Only blocked dates can be deleted here.');
        }

        $month = $booking->start_date->month;
        $year = $booking->start_date->year;

        $booking->delete();

        return redirect()->route('admin.shop.bookings.calendar', [
            'month' => $month,
            'year' => $year,
        ])->with('success', 'Block removed successfully.');
    }
}
