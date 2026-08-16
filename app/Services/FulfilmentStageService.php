<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class FulfilmentStageService
{
    /**
     * The ordered list of fulfilment stages a booking passes through.
     */
    const STAGES = ['ordered', 'packing', 'ready', 'checked_out', 'returned', 'inspected'];

    public function __construct(
        protected AssetAssignmentService $assetAssignmentService,
    ) {}

    /**
     * Advance a booking to the specified target stage.
     *
     * Validates sequential transition (no skipping), checks pre-conditions,
     * updates fulfilment_stage, and triggers side effects (asset status changes).
     *
     * @throws InvalidArgumentException if the transition is invalid or pre-conditions are unmet.
     */
    public function advance(Booking $booking, string $targetStage): void
    {
        $this->validateStage($targetStage);
        $this->validateTransition($booking, $targetStage);

        $unmetConditions = $this->checkPreConditions($booking, $targetStage);

        if (!empty($unmetConditions)) {
            throw new InvalidArgumentException(
                'Cannot advance to "' . $targetStage . '": ' . implode('; ', $unmetConditions)
            );
        }

        DB::transaction(function () use ($booking, $targetStage) {
            $booking->update(['fulfilment_stage' => $targetStage]);

            $this->applySideEffects($booking, $targetStage);
        });

        Log::info('FulfilmentStageService: Booking advanced', [
            'booking_id' => $booking->id,
            'new_stage' => $targetStage,
        ]);
    }

    /**
     * Get the next allowed stage for a booking, or null if at the final stage.
     */
    public function getNextStage(Booking $booking): ?string
    {
        $currentIndex = array_search($booking->fulfilment_stage, self::STAGES);

        if ($currentIndex === false || $currentIndex >= count(self::STAGES) - 1) {
            return null;
        }

        return self::STAGES[$currentIndex + 1];
    }

    /**
     * Check pre-conditions for advancing to a target stage.
     *
     * Returns an array of unmet condition descriptions. An empty array means
     * the booking is ready to advance.
     */
    public function checkPreConditions(Booking $booking, string $targetStage): array
    {
        $unmet = [];

        switch ($targetStage) {
            case 'packing':
                if (!$this->isBookingPaid($booking)) {
                    $unmet[] = 'Booking must be paid before packing can begin';
                }
                break;

            case 'ready':
                $booking->loadCount('assignedAssets');
                if ($booking->assigned_assets_count < 1) {
                    $unmet[] = 'At least one asset must be assigned before marking as ready';
                }
                break;

            case 'checked_out':
                $booking->loadMissing('checkoutInspection');
                if (!$booking->checkoutInspection) {
                    $unmet[] = 'A checkout inspection must be completed before checking out';
                }
                break;

            case 'returned':
                // No pre-conditions — just records the return
                break;

            case 'inspected':
                $booking->loadMissing('returnInspection');
                if (!$booking->returnInspection) {
                    $unmet[] = 'A return inspection must be completed before marking as inspected';
                }
                break;
        }

        return $unmet;
    }

    /**
     * Validate that the target stage is a recognized stage value.
     *
     * @throws InvalidArgumentException
     */
    protected function validateStage(string $stage): void
    {
        if (!in_array($stage, self::STAGES, true)) {
            throw new InvalidArgumentException(
                'Invalid fulfilment stage: "' . $stage . '". Valid stages: ' . implode(', ', self::STAGES)
            );
        }
    }

    /**
     * Validate that the transition from the booking's current stage to the target stage
     * is sequential (exactly one step forward, no skipping).
     *
     * @throws InvalidArgumentException
     */
    protected function validateTransition(Booking $booking, string $targetStage): void
    {
        $currentIndex = array_search($booking->fulfilment_stage, self::STAGES);
        $targetIndex = array_search($targetStage, self::STAGES);

        if ($currentIndex === false) {
            throw new InvalidArgumentException(
                'Booking is at an unrecognized stage: "' . $booking->fulfilment_stage . '"'
            );
        }

        if ($targetIndex <= $currentIndex) {
            throw new InvalidArgumentException(
                'Cannot move backward: booking is already at "' . $booking->fulfilment_stage . '"'
            );
        }

        if ($targetIndex !== $currentIndex + 1) {
            throw new InvalidArgumentException(
                'Cannot skip stages: must advance from "' . $booking->fulfilment_stage . '" to "' . self::STAGES[$currentIndex + 1] . '" before reaching "' . $targetStage . '"'
            );
        }
    }

    /**
     * Apply side effects when advancing to a specific stage.
     */
    protected function applySideEffects(Booking $booking, string $targetStage): void
    {
        switch ($targetStage) {
            case 'checked_out':
                // Update all assigned assets to 'Rented Out'
                $this->updateAssignedAssetsStatus($booking, 'Rented Out');
                break;

            case 'inspected':
                // Release assets via AssetAssignmentService
                $this->releaseAssetsOnInspection($booking);
                break;
        }
    }

    /**
     * Update the status of all assets assigned to this booking.
     */
    protected function updateAssignedAssetsStatus(Booking $booking, string $status): void
    {
        $assetIds = $booking->assignedAssets()
            ->whereNull('released_at')
            ->pluck('asset_id');

        if ($assetIds->isNotEmpty()) {
            Asset::whereIn('device_id', $assetIds)
                ->update(['asset_status' => $status]);
        }
    }

    /**
     * Release assets on inspection completion.
     * Determines damaged assets from the return inspection's damage flag.
     */
    protected function releaseAssetsOnInspection(Booking $booking): void
    {
        $booking->loadMissing('returnInspection');

        $damagedAssetIds = [];

        // If damage was flagged on the return inspection, mark all assigned assets as damaged
        if ($booking->returnInspection && $booking->returnInspection->damage_flagged) {
            $damagedAssetIds = $booking->assignedAssets()
                ->whereNull('released_at')
                ->pluck('asset_id')
                ->toArray();
        }

        $this->assetAssignmentService->releaseAssets($booking, $damagedAssetIds);
    }

    /**
     * Check if the booking's associated order has been paid.
     */
    protected function isBookingPaid(Booking $booking): bool
    {
        $booking->loadMissing('orderItem.order');

        $order = $booking->orderItem?->order;

        if (!$order) {
            return false;
        }

        return in_array($order->payment_status, ['paid', 'paid_offline'], true);
    }
}
