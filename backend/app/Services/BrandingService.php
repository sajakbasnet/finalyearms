<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class BrandingService
{
    public function current(): Branding
    {
        return Branding::current();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, ?UploadedFile $logo = null, ?UploadedFile $favicon = null): Branding
    {
        $branding = Branding::current();

        // Uploads are stored under generated names; the client-supplied
        // filename is never trusted or reused.
        if ($logo instanceof UploadedFile) {
            $this->replaceFile($branding->logo_path, $path = $logo->store('branding', 'public'));
            $data['logo_path'] = $path;
        }

        if ($favicon instanceof UploadedFile) {
            $this->replaceFile($branding->favicon_path, $path = $favicon->store('branding', 'public'));
            $data['favicon_path'] = $path;
        }

        unset($data['logo'], $data['favicon']);

        $branding->fill($data)->save();

        return $branding->refresh();
    }

    /** Removes the superseded asset so replaced logos don't accumulate. */
    private function replaceFile(?string $previousPath, string $newPath): void
    {
        if ($previousPath === null || $previousPath === $newPath) {
            return;
        }

        Storage::disk('public')->delete($previousPath);
    }
}
