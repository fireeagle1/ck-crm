<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $query = Asset::with('customer');

        // Search by device name, serial number, or customer name
        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('device_name', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', function ($cq) use ($q) {
                        $cq->where('company_name', 'like', "%{$q}%");
                    });
            });
        }

        $assets = $query->orderByDesc('device_id')->paginate(20);

        return view('admin.assets.index', compact('assets'));
    }

    public function create(Request $request): View
    {
        $customers = Customer::orderBy('company_name')->get();
        $products = Product::where('product_type', 'equipment_rental')
            ->orderBy('name')
            ->get();

        // Support pre-filling product_id when coming from product edit page
        $prefilledProductId = $request->input('product_id');

        return view('admin.assets.create', compact('customers', 'products', 'prefilledProductId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,company_id',
            'device_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'asset_status' => 'in:Active,Available,Rented Out,Reserved,In Repair,Decommissioned',
            'device_type' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
        ]);

        Asset::create($validated);

        return redirect()->route('admin.assets.index')
            ->with('success', 'Asset created.');
    }

    public function show(Asset $asset): View
    {
        $asset->load(['customer', 'tickets']);

        return view('admin.assets.show', compact('asset'));
    }

    public function edit(Asset $asset): View
    {
        $customers = Customer::orderBy('company_name')->get();
        $products = Product::where('product_type', 'equipment_rental')
            ->orderBy('name')
            ->get();

        return view('admin.assets.edit', compact('asset', 'customers', 'products'));
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,company_id',
            'device_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'asset_status' => 'in:Active,Available,Rented Out,Reserved,In Repair,Decommissioned',
            'device_type' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
        ]);

        $asset->update($validated);

        return back()->with('success', 'Asset updated.');
    }

    /**
     * Display a printable QR label for an asset (Brother QL-700 compatible).
     */
    public function label(Request $request, Asset $asset): View
    {
        $size = $request->input('size', 'standard'); // standard or compact

        return view('admin.assets.label', compact('asset', 'size'));
    }

    /**
     * Generate and download a PNG label image sized for Brother QL-700 (62mm continuous roll).
     */
    public function labelDownload(Request $request, Asset $asset): Response
    {
        $size = $request->input('size', 'standard');

        // Brother QL-700 prints at 300 DPI. 62mm wide = ~732px at 300 DPI
        $labelWidth = 732;
        $labelHeight = $size === 'compact' ? 350 : 460;
        $padding = 24;
        $qrSize = $size === 'compact' ? 180 : 220;

        // Font - use system Helvetica or fall back to GD built-in
        $fontBold = '/System/Library/Fonts/Helvetica.ttc';
        $fontRegular = '/System/Library/Fonts/Helvetica.ttc';
        $useFreetype = file_exists($fontBold);

        // Create image
        $img = imagecreatetruecolor($labelWidth, $labelHeight);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 17, 24, 39);
        $gray = imagecolorallocate($img, 107, 114, 128);
        $lightGray = imagecolorallocate($img, 229, 231, 235);

        // Fill white background
        imagefill($img, 0, 0, $white);

        // Draw border
        imagerectangle($img, 0, 0, $labelWidth - 1, $labelHeight - 1, $lightGray);

        $x = $padding;
        $y = $padding;

        // --- QR Code ---
        $qrUrl = 'https://quickchart.io/qr?text=' . urlencode(url('/admin/assets/' . $asset->device_id)) . '&size=' . ($qrSize * 2) . '&margin=0';
        $qrData = @file_get_contents($qrUrl);
        if ($qrData) {
            $qrImg = @imagecreatefromstring($qrData);
            if ($qrImg) {
                $qrW = imagesx($qrImg);
                $qrH = imagesy($qrImg);
                imagecopyresampled($img, $qrImg, $x, $y, 0, 0, $qrSize, $qrSize, $qrW, $qrH);
                imagedestroy($qrImg);
            }
        }

        // --- Info area (right of QR code) ---
        $infoX = $x + $qrSize + 20;
        $infoY = $y;
        $maxTextWidth = $labelWidth - $infoX - $padding;

        // Logo
        $logoPath = Setting::get('logo_dark_path') ?? Setting::get('logo_path');
        $logoDrawn = false;
        if ($logoPath) {
            $fullLogoPath = public_path($logoPath);
            if (file_exists($fullLogoPath)) {
                $logoInfo = @getimagesize($fullLogoPath);
                if ($logoInfo) {
                    $logoImg = $this->loadImage($fullLogoPath, $logoInfo[2]);
                    if ($logoImg) {
                        $logoOrigW = imagesx($logoImg);
                        $logoOrigH = imagesy($logoImg);
                        $logoMaxH = $size === 'compact' ? 40 : 50;
                        $logoMaxW = $maxTextWidth;
                        $scale = min($logoMaxW / $logoOrigW, $logoMaxH / $logoOrigH);
                        $logoW = (int) ($logoOrigW * $scale);
                        $logoH = (int) ($logoOrigH * $scale);
                        imagecopyresampled($img, $logoImg, $infoX, $infoY, 0, 0, $logoW, $logoH, $logoOrigW, $logoOrigH);
                        imagedestroy($logoImg);
                        $infoY += $logoH + 10;
                        $logoDrawn = true;
                    }
                }
            }
        }

        if (!$logoDrawn) {
            // Fallback: text "CK Enterprises"
            if ($useFreetype) {
                imagettftext($img, 14, 0, $infoX, $infoY + 16, $black, $fontBold, 'CK Enterprises');
            } else {
                imagestring($img, 5, $infoX, $infoY, 'CK Enterprises', $black);
            }
            $infoY += 28;
        }

        // CMDB ID
        $cmdbId = 'CMDB-' . $asset->device_id;
        if ($useFreetype) {
            $fontSize = $size === 'compact' ? 13 : 15;
            imagettftext($img, $fontSize, 0, $infoX, $infoY + $fontSize + 2, $black, $fontBold, $cmdbId);
            $infoY += $fontSize + 14;
        } else {
            imagestring($img, 5, $infoX, $infoY, $cmdbId, $black);
            $infoY += 20;
        }

        // Device name
        $deviceName = $asset->device_name;
        if (strlen($deviceName) > 28) {
            $deviceName = substr($deviceName, 0, 26) . '...';
        }
        if ($useFreetype) {
            $fontSize = $size === 'compact' ? 10 : 12;
            imagettftext($img, $fontSize, 0, $infoX, $infoY + $fontSize + 2, $black, $fontRegular, $deviceName);
            $infoY += $fontSize + 12;
        } else {
            imagestring($img, 4, $infoX, $infoY, $deviceName, $black);
            $infoY += 18;
        }

        // Serial number
        if ($asset->serial_number) {
            $serial = 'S/N: ' . $asset->serial_number;
            if (strlen($serial) > 32) {
                $serial = substr($serial, 0, 30) . '...';
            }
            if ($useFreetype) {
                $fontSize = $size === 'compact' ? 8 : 9;
                imagettftext($img, $fontSize, 0, $infoX, $infoY + $fontSize + 2, $gray, $fontRegular, $serial);
                $infoY += $fontSize + 10;
            } else {
                imagestring($img, 3, $infoX, $infoY, $serial, $gray);
                $infoY += 14;
            }
        }

        // Device type
        if ($asset->device_type) {
            if ($useFreetype) {
                $fontSize = $size === 'compact' ? 8 : 9;
                imagettftext($img, $fontSize, 0, $infoX, $infoY + $fontSize + 2, $gray, $fontRegular, $asset->device_type);
            } else {
                imagestring($img, 2, $infoX, $infoY, $asset->device_type, $gray);
            }
        }

        // --- Footer: support contact ---
        $footerY = $labelHeight - $padding - 16;
        imageline($img, $padding, $footerY - 8, $labelWidth - $padding, $footerY - 8, $lightGray);

        $supportText = 'For support contact CKEnterprises.co.uk';
        if ($useFreetype) {
            $fontSize = $size === 'compact' ? 7 : 8;
            $bbox = imagettfbbox($fontSize, 0, $fontRegular, $supportText);
            $textWidth = $bbox[2] - $bbox[0];
            $textX = (int) (($labelWidth - $textWidth) / 2);
            imagettftext($img, $fontSize, 0, $textX, $footerY + 4, $gray, $fontRegular, $supportText);
        } else {
            $textWidth = strlen($supportText) * imagefontwidth(2);
            $textX = (int) (($labelWidth - $textWidth) / 2);
            imagestring($img, 2, $textX, $footerY - 4, $supportText, $gray);
        }

        // Output as PNG
        ob_start();
        imagepng($img);
        $pngData = ob_get_clean();
        imagedestroy($img);

        $filename = 'label-CMDB-' . $asset->device_id . '.png';

        return response($pngData, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($pngData),
        ]);
    }

    /**
     * Load an image resource from a file path based on its type.
     */
    private function loadImage(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        };
    }
}
