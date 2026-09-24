<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Small, dependency-free stand-in for Jetstream's HasProfilePhoto trait.
 * Falls back to <x-avatar>'s own initials rendering when no photo is set,
 * so (unlike Jetstream) this never calls out to an external avatar service.
 */
trait HasProfilePhoto
{
    public function updateProfilePhoto(UploadedFile $photo, string $storagePath = 'profile-photos'): void
    {
        $previous = $this->profile_photo_path;

        $this->forceFill([
            'profile_photo_path' => $photo->storePublicly($storagePath, ['disk' => $this->profilePhotoDisk()]),
        ])->save();

        if ($previous) {
            Storage::disk($this->profilePhotoDisk())->delete($previous);
        }
    }

    public function deleteProfilePhoto(): void
    {
        if (is_null($this->profile_photo_path)) {
            return;
        }

        $this->deleteProfilePhotoFile();

        $this->forceFill(['profile_photo_path' => null])->save();
    }

    /**
     * Delete only the stored file, without touching the model row — for when the
     * row is already gone (a force-delete), where saving it back would be wrong.
     * Callers that also need the column cleared use deleteProfilePhoto().
     */
    public function deleteProfilePhotoFile(): void
    {
        if (is_null($this->profile_photo_path)) {
            return;
        }

        Storage::disk($this->profilePhotoDisk())->delete($this->profile_photo_path);
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->profile_photo_path
            ? Storage::disk($this->profilePhotoDisk())->url($this->profile_photo_path)
            : null);
    }

    protected function profilePhotoDisk(): string
    {
        return config('filesystems.profile_photo_disk', 'public');
    }
}
