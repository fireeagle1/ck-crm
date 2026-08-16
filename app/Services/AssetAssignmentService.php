<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\BookingAsset;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssetAssignmentService
{
    /**
     * Automatically assign available assets to a booking based on the required quantity.
     *
     * Picks the first N available assets (sorted by device_name) linked to the product.
     * If the product doesn't track individual assets, this is a no-op.
     * If insufficient assets are available, assigns as many as possible (partial assignment).
     *
     * @return int Number of assets assigned (0 if product doesn't track assets or none available)
     */
    public function autoAssign(Booking $booking): int
    {
        $booking->loadMissing('product');
        $product = $booking->product;

        if (!$product) {
            return 0;
        }

        // Only auto-assign if this product has linked assets in the CMDB
        if ($product->assets()->count() === 0) {
            return 0;
        }

        $quantity = $booking->quantity;

        return DB::transaction(function () use ($booking, $product, $quantity) {
            // Lock and fetch available assets for this product
            $availableAssets = Asset::where('product_id', $product->id)
                ->where('asset_status', 'Available')
                ->lockForUpdate()
                ->orderBy('device_name')
                ->limit($quantity)
                ->get();

            if ($availableAssets->isEmpty()) {
                return 0;
            }

            $now = now();
            $assignedCount = 0;

            foreach ($availableAssets as $asset) {
                BookingAsset::create([
                    'booking_id' => $booking->id,
                    'asset_id' => $asset->device_id,
                    'assigned_at' => $now,
                ]);
                $assignedCount++;
            }

            // Update assigned assets to Reserved
            $assignedIds = $availableAssets->pluck('device_id')->toArray();
            Asset::whereIn('device_id', $assignedIds)
                ->update(['asset_status' => 'Reserved']);

            // Auto-advance fulfilment stage: ordered → packing → ready
            if ($booking->fulfilment_stage === 'ordered') {
                $booking->update(['fulfilment_stage' => 'packing']);
            }
            if ($booking->fulfilment_stage === 'packing' && $assignedCount >= $quantity) {
                $booking->update(['fulfilment_stage' => 'ready']);
            }

            return $assignedCount;
        });
    }

    /**
     * Assign specific assets to a booking.
     *
     * Validates that all assets belong to the booking's product and are Available.
     * Creates BookingAsset records and updates asset status to Reserved.
     * Uses a database transaction with pessimistic locking for race condition prevention.
     *
     * @param Booking $booking
     * @param array $assetIds Array of asset device_id values to assign
     * @return void
     *
     * @throws InvalidArgumentException if any asset is not available or doesn't belong to the product
     */
    public function assignAssets(Booking $booking, array $assetIds): void
    {
        if (empty($assetIds)) {
            throw new InvalidArgumentException('At least one asset must be provided for assignment.');
        }

        DB::transaction(function () use ($booking, $assetIds) {
            // Lock the assets for update to prevent race conditions
            $assets = Asset::whereIn('device_id', $assetIds)
                ->lockForUpdate()
                ->get();

            // Validate all requested assets were found
            if ($assets->count() !== count($assetIds)) {
                $foundIds = $assets->pluck('device_id')->toArray();
                $missingIds = array_diff($assetIds, $foundIds);
                throw new InvalidArgumentException(
                    'The following asset IDs were not found: ' . implode(', ', $missingIds)
                );
            }

            // Validate all assets belong to the booking's product
            $invalidProductAssets = $assets->filter(function (Asset $asset) use ($booking) {
                return $asset->product_id !== $booking->product_id;
            });

            if ($invalidProductAssets->isNotEmpty()) {
                $invalidIds = $invalidProductAssets->pluck('device_id')->toArray();
                throw new InvalidArgumentException(
                    'The following assets do not belong to the booking\'s product: ' . implode(', ', $invalidIds)
                );
            }

            // Validate all assets are Available
            $unavailableAssets = $assets->filter(function (Asset $asset) {
                return !$asset->isAvailableForRental();
            });

            if ($unavailableAssets->isNotEmpty()) {
                $unavailableDetails = $unavailableAssets->map(function (Asset $asset) {
                    return $asset->device_id . ' (' . $asset->asset_status . ')';
                })->toArray();
                throw new InvalidArgumentException(
                    'The following assets are not available for assignment: ' . implode(', ', $unavailableDetails)
                );
            }

            // Create BookingAsset records
            $now = now();
            foreach ($assetIds as $assetId) {
                BookingAsset::create([
                    'booking_id' => $booking->id,
                    'asset_id' => $assetId,
                    'assigned_at' => $now,
                ]);
            }

            // Update asset status to Reserved
            Asset::whereIn('device_id', $assetIds)
                ->update(['asset_status' => 'Reserved']);
        });
    }

    /**
     * Release all assets from a booking.
     *
     * Sets released_at on BookingAsset records and updates asset status back to
     * 'Available' (or 'In Repair' for assets in the damagedAssetIds array).
     * Uses a database transaction with pessimistic locking.
     *
     * @param Booking $booking
     * @param array $damagedAssetIds Asset IDs that should be marked as 'In Repair'
     * @return void
     */
    public function releaseAssets(Booking $booking, array $damagedAssetIds = []): void
    {
        DB::transaction(function () use ($booking, $damagedAssetIds) {
            $now = now();

            // Get all active (unreleased) booking assets
            $bookingAssets = $booking->assignedAssets()
                ->whereNull('released_at')
                ->get();

            if ($bookingAssets->isEmpty()) {
                return;
            }

            $allAssetIds = $bookingAssets->pluck('asset_id')->toArray();

            // Lock the assets for update
            Asset::whereIn('device_id', $allAssetIds)
                ->lockForUpdate()
                ->get();

            // Mark all booking assets as released
            $booking->assignedAssets()
                ->whereNull('released_at')
                ->update(['released_at' => $now]);

            // Update healthy assets to Available
            $healthyAssetIds = array_diff($allAssetIds, $damagedAssetIds);
            if (!empty($healthyAssetIds)) {
                Asset::whereIn('device_id', $healthyAssetIds)
                    ->update(['asset_status' => 'Available']);
            }

            // Update damaged assets to In Repair
            if (!empty($damagedAssetIds)) {
                $validDamagedIds = array_intersect($allAssetIds, $damagedAssetIds);
                if (!empty($validDamagedIds)) {
                    Asset::whereIn('device_id', $validDamagedIds)
                        ->update(['asset_status' => 'In Repair']);
                }
            }
        });
    }
}
