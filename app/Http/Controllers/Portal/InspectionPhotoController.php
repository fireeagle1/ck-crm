<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionPhotoController extends Controller
{
    /**
     * Serve an inspection photo to an authenticated portal user.
     *
     * Photos are stored on the 'local' disk (not public), so they need
     * to be served through an authenticated route.
     */
    public function show(string $path): StreamedResponse
    {
        // Prevent path traversal — only allow access to inspection photos
        if (!str_starts_with($path, 'inspections/') || str_contains($path, '..')) {
            abort(403, 'Access denied.');
        }

        $disk = Storage::disk('local');

        if (!$disk->exists($path)) {
            abort(404, 'Photo not found.');
        }

        $mimeType = $disk->mimeType($path) ?: 'image/jpeg';

        return $disk->response($path, null, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
