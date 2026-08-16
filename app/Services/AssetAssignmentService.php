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
