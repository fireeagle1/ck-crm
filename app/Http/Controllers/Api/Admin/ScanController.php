<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class ScanController extends Controller
{
    /**
     * Resolve a scanned QR code to an entity type and ID.
     *
     * Supported formats:
     *   CMDB-{id}  → Asset
     *   ORD-{id}   → Order
     *   BKG-{id}   → Booking
     *
     * Returns the entity type, ID, and a summary for display.
     */
    public function resolve(string $code): JsonResponse
    {
        $code = trim($code);

        // Parse the code format
        if (preg_match('/^CMDB-(\d+)$/i', $code, $matches)) {
            return $this->resolveAsset((int) $matches[1]);
        }

        if (preg_match('/^ORD-(\d+)$/i', $code, $matches)) {
            return $this->resolveOrder((int) $matches[1]);
        }

        if (preg_match('/^BKG-(\d+)$/i', $code, $matches)) {
            return $this->resolveBooking((int) $matches[1]);
        }

        return response()->json([
            'resolved' => false,
            'message' => 'Unrecognised code format.',
        ], 404);
    }

    private function resolveAsset(int $id): JsonResponse
    {
        $asset = Asset::find($id);

        if (!$asset) {
            return response()->json([
                'resolved' => false,
                'message' => "Asset CMDB-{$id} not found.",
            ], 404);
        }

        return response()->json([
            'resolved' => true,
            'type' => 'asset',
            'id' => $asset->device_id,
            'summary' => [
                'title' => $asset->device_name,
                'subtitle' => $asset->serial_number ?: 'No serial',
                'status' => $asset->asset_status,
            ],
        ]);
    }

    private function resolveOrder(int $id): JsonResponse
    {
        $order = Order::with('customer')->find($id);

        if (!$order) {
            return response()->json([
                'resolved' => false,
                'message' => "Order ORD-{$id} not found.",
            ], 404);
        }

        return response()->json([
            'resolved' => true,
            'type' => 'order',
            'id' => $order->id,
            'summary' => [
                'title' => "Order #{$order->id}",
                'subtitle' => $order->customer?->company_name ?? 'Unknown customer',
                'status' => $order->fulfilment_status,
            ],
        ]);
    }

    private function resolveBooking(int $id): JsonResponse
    {
        $booking = Booking::with('product', 'customer')->find($id);

        if (!$booking) {
            return response()->json([
                'resolved' => false,
                'message' => "Booking BKG-{$id} not found.",
            ], 404);
        }

        return response()->json([
            'resolved' => true,
            'type' => 'booking',
            'id' => $booking->id,
            'summary' => [
                'title' => $booking->product?->name ?? 'Unknown product',
                'subtitle' => $booking->customer?->company_name ?? 'Unknown customer',
                'status' => $booking->fulfilment_stage,
            ],
        ]);
    }
}
