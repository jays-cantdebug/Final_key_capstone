<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class AvatarService
{
    /**
     * Avatars only ever render up to h-20/w-20 (80px) in the UI, so 512px
     * comfortably covers even a high-DPI display without keeping multi-MB
     * originals around.
     */
    private const MAX_DIMENSION = 512;

    private const JPEG_QUALITY = 82;

    private readonly ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = ImageManager::gd();
    }

    /**
     * Resize/compress an uploaded avatar and store it, returning its storage path.
     */
    public function store(UploadedFile $file): string
    {
        $image = $this->imageManager->read($file->getRealPath());
        $image->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION);

        $encoded = $image->toJpeg(quality: self::JPEG_QUALITY);

        $path = 'avatars/'.Str::random(40).'.jpg';
        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
