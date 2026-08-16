<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingInspection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class BookingInspectionService
{
    /**
     * Maximum number of photos allowed per inspection.
     */
    private const MAX_PHOTOS = 10;

    /**
     * Maximum file size in bytes (10 MB).
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Maximum image width in pixels after resize.
     */
    private const MAX_WIDTH = 1920;

    /**
     * Allowed MIME types for photo uploads.
     */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];

    /**
     * Create a checkout inspection with uploaded photos.
     * Stores photos to storage/app/inspections/{booking_id}/checkout_{n}.ext
     *
     * @param Booking $booking
     * @param UploadedFile[] $photos
     * @param string|null $notes
     * @param int $adminId
     * @return BookingInspection
     *
     * @throws InvalidArgumentException
     */
    public function createCheckoutInspection(Booking $booking, array $photos, ?string $notes, int $adminId): BookingInspection
    {
        $this->validatePhotos($photos);

        $storedPaths = $this->storePhotos($booking->id, 'checkout', $photos);

        $inspection = BookingInspection::create([
            'booking_id' => $booking->id,
            'type' => 'checkout',
            'photos' => $storedPaths,
            'condition_notes' => $notes,
            'damage_flagged' => false,
            'inspected_by' => $adminId,
            'inspected_at' => now(),
        ]);

        Log::info('BookingInspectionService: Checkout inspection created', [
            'booking_id' => $booking->id,
            'inspection_id' => $inspection->id,
            'photo_count' => count($storedPaths),
            'admin_id' => $adminId,
        ]);

        return $inspection;
    }

    /**
     * Create a return inspection with uploaded photos.
     * Stores photos to storage/app/inspections/{booking_id}/return_{n}.ext
     *
     * @param Booking $booking
     * @param UploadedFile[] $photos
     * @param string|null $notes
     * @param bool $damageFlagged
     * @param int $adminId
     * @return BookingInspection
     *
     * @throws InvalidArgumentException
     */
    public function createReturnInspection(Booking $booking, array $photos, ?string $notes, bool $damageFlagged, int $adminId): BookingInspection
    {
        $this->validatePhotos($photos);

        $storedPaths = $this->storePhotos($booking->id, 'return', $photos);

        $inspection = BookingInspection::create([
            'booking_id' => $booking->id,
            'type' => 'return',
            'photos' => $storedPaths,
            'condition_notes' => $notes,
            'damage_flagged' => $damageFlagged,
            'inspected_by' => $adminId,
            'inspected_at' => now(),
        ]);

        Log::info('BookingInspectionService: Return inspection created', [
            'booking_id' => $booking->id,
            'inspection_id' => $inspection->id,
            'photo_count' => count($storedPaths),
            'damage_flagged' => $damageFlagged,
            'admin_id' => $adminId,
        ]);

        return $inspection;
    }

    /**
     * Validate the array of uploaded photos.
     *
     * @param UploadedFile[] $photos
     * @throws InvalidArgumentException
     */
    private function validatePhotos(array $photos): void
    {
        if (empty($photos)) {
            throw new InvalidArgumentException('At least one photo is required for an inspection.');
        }

        if (count($photos) > self::MAX_PHOTOS) {
            throw new InvalidArgumentException(
                'Maximum ' . self::MAX_PHOTOS . ' photos allowed per inspection.'
            );
        }

        foreach ($photos as $index => $photo) {
            if (!$photo instanceof UploadedFile) {
                throw new InvalidArgumentException(
                    "Item at index {$index} is not a valid uploaded file."
                );
            }

            if (!$photo->isValid()) {
                throw new InvalidArgumentException(
                    "Photo at index {$index} failed to upload correctly."
                );
            }

            if (!in_array($photo->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
                throw new InvalidArgumentException(
                    "Photo at index {$index} must be a JPEG or PNG image."
                );
            }

            if ($photo->getSize() > self::MAX_FILE_SIZE) {
                throw new InvalidArgumentException(
                    "Photo at index {$index} exceeds the maximum file size of 10 MB."
                );
            }
        }
    }

    /**
     * Store and resize photos, returning the array of relative paths.
     *
     * @param int $bookingId
     * @param string $type 'checkout' or 'return'
     * @param UploadedFile[] $photos
     * @return string[]
     */
    private function storePhotos(int $bookingId, string $type, array $photos): array
    {
        $storedPaths = [];
        $directory = "inspections/{$bookingId}";

        // Ensure directory exists
        Storage::disk('local')->makeDirectory($directory);

        foreach ($photos as $index => $photo) {
            $number = $index + 1;
            $extension = $this->getExtension($photo);
            $filename = "{$type}_{$number}.{$extension}";
            $relativePath = "{$directory}/{$filename}";

            // Resize the image using GD
            $resizedImageData = $this->resizeImage($photo);

            // Store the resized image
            Storage::disk('local')->put($relativePath, $resizedImageData);

            $storedPaths[] = $relativePath;
        }

        return $storedPaths;
    }

    /**
     * Resize an uploaded image to a maximum width of 1920px while maintaining aspect ratio.
     * Uses PHP GD extension.
     *
     * @param UploadedFile $photo
     * @return string The binary image data after resizing
     */
    private function resizeImage(UploadedFile $photo): string
    {
        $mimeType = $photo->getMimeType();
        $filePath = $photo->getRealPath();

        // Create GD image resource from file
        $sourceImage = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($filePath),
            'image/png' => imagecreatefrompng($filePath),
            default => throw new InvalidArgumentException("Unsupported image type: {$mimeType}"),
        };

        if ($sourceImage === false) {
            throw new InvalidArgumentException('Failed to read image file.');
        }

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Only resize if the image exceeds max width
        if ($originalWidth > self::MAX_WIDTH) {
            $newWidth = self::MAX_WIDTH;
            $newHeight = (int) round(($originalHeight / $originalWidth) * $newWidth);

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

            if ($resizedImage === false) {
                imagedestroy($sourceImage);
                throw new InvalidArgumentException('Failed to create resized image.');
            }

            // Preserve transparency for PNG
            if ($mimeType === 'image/png') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
                imagefill($resizedImage, 0, 0, $transparent);
            }

            imagecopyresampled(
                $resizedImage,
                $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $originalWidth, $originalHeight
            );

            imagedestroy($sourceImage);
            $sourceImage = $resizedImage;
        }

        // Output to buffer
        ob_start();

        match ($mimeType) {
            'image/jpeg' => imagejpeg($sourceImage, null, 85),
            'image/png' => imagepng($sourceImage, null, 6),
        };

        $imageData = ob_get_clean();
        imagedestroy($sourceImage);

        if ($imageData === false || $imageData === '') {
            throw new InvalidArgumentException('Failed to encode resized image.');
        }

        return $imageData;
    }

    /**
     * Get the file extension based on MIME type.
     *
     * @param UploadedFile $photo
     * @return string
     */
    private function getExtension(UploadedFile $photo): string
    {
        return match ($photo->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'jpg',
        };
    }
}
