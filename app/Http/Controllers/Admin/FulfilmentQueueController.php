<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\AssetAssignmentService;
use App\Services\BookingInspectionService;
use App\Services\FulfilmentStageService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class FulfilmentQueueController extends Controller
{
    public function __construct(
        private FulfilmentStageService $fulfilmentStageService,
        private AssetAssignmentService $assetAssignmentService,
        private BookingInspectionService $bookingInspectionService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Display the fulfilment queue, grouped by stage with tab/filter support.
     *
     * Requirements: 3.2
     */
    public function index(Request $request): View
    {
        $stages = FulfilmentStageService::STAGES;
        $activeStage = $request->input('stage', 'ordered');

        // Validate the requested stage filter
        if (!in_array($activeStage, $stages, true)) {
            $activeStage = 'ordered';
        }

        // Count bookings per stage for tab badges
        $stageCounts = Booking::selectRaw('fulfilment_stage, COUNT(*) as count')
            ->groupBy('fulfilment_stage')
            ->pluck('count', 'fulfilment_stage')
            ->toArray();

        // Query bookings for the active stage tab
        $query = Booking::with('product', 'customer')
            ->atStage($activeStage);

        // Search by customer name or product name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('company_name', 'like', "%{$search}%");
                })->orWhereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        $bookings = $query->orderBy('created_at', 'asc')->paginate(20);

        return view('admin.fulfilment.index', compact(
            'stages',
            'activeStage',
            'stageCounts',
            'bookings',
        ));
    }

    /**
     * Display booking detail — redirects to the unified order page.
     * The fulfilment pipeline is now embedded in the order show view.
     */
    public function show(Booking $booking): RedirectResponse
    {
        $booking->loadMissing('orderItem.order');

        if ($booking->orderItem?->order) {
            return redirect()->route('admin.shop.orders.show', $booking->orderItem->order);
        }

        return redirect()->route('admin.fulfilment.index');
    }

    /**
     * Assign selected assets to a booking.
     * Advances stage to 'packing' if currently at 'ordered'.
     *
     * Requirements: 3.4, 3.5
     */
    public function assignAssets(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'required|integer|exists:cmdb,device_id',
        ]);

        try {
            $this->assetAssignmentService->assignAssets($booking, $validated['asset_ids']);

            // Auto-advance to packing if still at ordered stage
            if ($booking->fulfilment_stage === 'ordered') {
                $this->fulfilmentStageService->advance($booking, 'packing');
            }

            return redirect()->route('admin.fulfilment.show', $booking)
                ->with('success', 'Assets assigned successfully.');
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.fulfilment.show', $booking)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Advance a booking to the next fulfilment stage.
     *
     * Requirements: 3.6, 3.7, 3.9, 3.12
     */
    public function advance(Request $request, Booking $booking): RedirectResponse
    {
        $nextStage = $this->fulfilmentStageService->getNextStage($booking);

        if (!$nextStage) {
            return redirect()->route('admin.fulfilment.show', $booking)
                ->with('error', 'This booking is already at the final stage.');
        }

        try {
            $this->fulfilmentStageService->advance($booking, $nextStage);

            return redirect()->route('admin.fulfilment.show', $booking)
                ->with('success', 'Booking advanced to "' . str_replace('_', ' ', $nextStage) . '" stage.');
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.fulfilment.show', $booking)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Store an inspection (checkout or return) with photo uploads and advance the stage.
     *
     * Determines inspection type based on current fulfilment stage:
     * - checked_out or returned → return inspection
     * - packing or ready → checkout inspection
     *
     * Requirements: 3.7, 3.10, 4.1, 4.2, 4.3
     */
    public function inspect(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'photos' => 'required|array|min:1|max:10',
            'photos.*' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'condition_notes' => 'nullable|string|max:2000',
            'damage_flagged' => 'nullable|boolean',
        ]);

        $photos = $request->file('photos');
        $notes = $validated['condition_notes'] ?? null;
        $damageFlagged = (bool) ($validated['damage_flagged'] ?? false);
        $adminId = $request->user()->id;

        // Determine inspection type from current stage
        $isReturnInspection = in_array($booking->fulfilment_stage, ['checked_out', 'returned']);

        try {
            if ($isReturnInspection) {
                $inspection = $this->bookingInspectionService->createReturnInspection(
                    $booking,
                    $photos,
                    $notes,
                    $damageFlagged,
                    $adminId
                );

                // Advance stage: if at 'returned', advance to 'inspected'
                // if at 'checked_out', first advance to 'returned', then to 'inspected'
                if ($booking->fulfilment_stage === 'checked_out') {
                    $this->fulfilmentStageService->advance($booking, 'returned');
                    $booking->refresh();
                }
                $this->fulfilmentStageService->advance($booking, 'inspected');

                // Send return inspection report email to customer and admin
                $this->notificationService->notifyReturnInspectionComplete($booking, $inspection);

                return redirect()->route('admin.fulfilment.show', $booking)
                    ->with('success', 'Return inspection recorded and booking marked as inspected.');
            } else {
                $this->bookingInspectionService->createCheckoutInspection(
                    $booking,
                    $photos,
                    $notes,
                    $adminId
                );

                // Advance to checked_out if at ready stage
                if ($booking->fulfilment_stage === 'ready') {
                    $this->fulfilmentStageService->advance($booking, 'checked_out');
                }

                return redirect()->route('admin.fulfilment.show', $booking)
                    ->with('success', 'Checkout inspection recorded.');
            }
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.fulfilment.show', $booking)
                ->with('error', $e->getMessage());
        }
    }
}
