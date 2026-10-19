<?php

namespace App\Services\Catalog;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Local public-disk product image storage with path-safety checks.
 */
class ProductImageService
{
    public const DISK = 'public';

    public const DIRECTORY = 'products';

    /**
     * @var array<string, string>
     */
    protected const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(UploadedFile|TemporaryUploadedFile $file): string
    {
        $mime = $this->detectMime($file);

        if (! isset(self::MIME_EXTENSIONS[$mime])) {
            throw new InvalidArgumentException('Unsupported image type.');
        }

        if (@getimagesize($file->getRealPath()) === false) {
            throw new InvalidArgumentException('The uploaded file is not a valid image.');
        }

        $filename = Str::uuid()->toString().'.'.self::MIME_EXTENSIONS[$mime];
        $path = self::DIRECTORY.'/'.$filename;

        $stored = Storage::disk(self::DISK)->putFileAs(self::DIRECTORY, $file, $filename);

        if ($stored === false || ! $this->isManagedPath((string) $stored)) {
            throw new InvalidArgumentException('Unable to store the product image.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (! $this->isManagedPath($path)) {
            return;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '' || ! $this->isManagedPath($path)) {
            return null;
        }

        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    public function exists(?string $path): bool
    {
        return $path !== null
            && $path !== ''
            && $this->isManagedPath($path)
            && Storage::disk(self::DISK)->exists($path);
    }

    public function isManagedPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        if ($normalized === '' || str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
            return false;
        }

        return preg_match(
            '#^'.preg_quote(self::DIRECTORY, '#').'/[a-f0-9-]{36}\.(jpg|png|webp)$#i',
            $normalized,
        ) === 1;
    }

    protected function detectMime(UploadedFile|TemporaryUploadedFile $file): string
    {
        $mime = (string) ($file->getMimeType() ?: '');

        if ($mime === '' && is_string($file->getRealPath())) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file($file->getRealPath());
        }

        return strtolower($mime);
    }
}
