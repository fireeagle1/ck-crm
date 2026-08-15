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
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private AvailabilityService $availabilityService,
        private NotificationService $notificationService,
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
     * Show booking detail with signature, agreement, and return action.
     *
     * Requirements: 15.2, 16.1
     */
    public function show(Booking $booking): View
    {
        $booking->load('product', 'customer', 'orderItem.order');

        return view('admin.shop.bookings.show', compact('booking'));
    }

    /**
     * Mark a booking as returned, trigger notification.
     *
     * Requirements: 7.3
     */
    public function markReturned(Booking $booking): RedirectResponse
    {
        $this->bookingService->markReturned($booking);
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

        // Create Order, OrderItem, and Booking within a single transaction (Req 16.7)
        DB::transaction(function () use ($validated, $product, $startDate, $endDate, $quantity, $paymentStatus, $totalPrice) {
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
            ]);

            // Link booking to order item
            $orderItem->update(['booking_id' => $booking->id]);
        });

        return redirect()->route('admin.shop.bookings.index')
            ->with('success', 'Booking created successfully.');
    }
}
