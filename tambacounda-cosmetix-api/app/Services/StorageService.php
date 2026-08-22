<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Single point of indirection between the application and the underlying
 * filesystem disk used for product assets. Nothing else in the codebase
 * should call Storage::disk() with a hardcoded disk name — this service
 * resolves it from config('filesystems.product_disk'), so switching from
 * local storage to Supabase Storage later is a config change, not a code
 * change.
 */
class StorageService
{
    public function disk(): string
    {
        return config('filesystems.product_disk', 'public');
    }

    public function filesystem(): Filesystem
    {
        return Storage::disk($this->disk());
    }

    public function putProductFile(UploadedFile $file, string $directory = 'products'): string
    {
        return $file->store($directory, $this->disk());
    }

    public function url(string $path): string
    {
        return $this->filesystem()->url($path);
    }

    public function delete(string $path): bool
    {
        return $this->filesystem()->delete($path);
    }
}
