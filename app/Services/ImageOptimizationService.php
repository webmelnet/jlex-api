<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Log;

/**
 * Image Optimization Service
 * 
 * This service optimizes images before uploading to S3.
 * It reduces file size while maintaining acceptable quality.
 * 
 * Requirements:
 * - composer require intervention/image
 * - PHP GD extension or Imagick extension
 */
class ImageOptimizationService
{
    /**
     * Image Manager instance
     */
    protected ImageManager $manager;

    /**
     * Maximum width for optimized images
     */
    protected $maxWidth = 1920;

    /**
     * Maximum height for optimized images
     */
    protected $maxHeight = 1920;

    /**
     * Quality for JPEG compression (0-100)
     */
    protected $jpegQuality = 85;

    /**
     * Quality for PNG compression (0-100)
     */
    protected $pngQuality = 90;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize with GD driver (or use Imagick if you have it installed)
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Optimize image if it exceeds size threshold
     *
     * @param UploadedFile $file
     * @return array
     */
    public function optimizeIfNeeded(UploadedFile $file): array
    {
        $startTime = microtime(true);
        $originalSize = $file->getSize();
        
        // Threshold: optimize images larger than 500KB
        $sizeThreshold = 500 * 1024; // 500KB

        $result = [
            'file' => $file,
            'was_optimized' => false,
            'original_size' => $originalSize,
            'optimized_size' => $originalSize,
            'dimensions' => null,
            'quality_used' => null,
            'optimization_time' => null
        ];

        try {
            // Get image dimensions
            $imageInfo = getimagesize($file->getRealPath());
            
            if ($imageInfo) {
                $result['dimensions'] = [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1]
                ];

                // Only optimize if file is larger than threshold
                if ($originalSize > $sizeThreshold || 
                    $imageInfo[0] > $this->maxWidth || 
                    $imageInfo[1] > $this->maxHeight) {
                    
                    $optimizedFile = $this->optimizeImage($file);
                    
                    $result['file'] = $optimizedFile;
                    $result['was_optimized'] = true;
                    $result['optimized_size'] = $optimizedFile->getSize();
                    $result['quality_used'] = $this->getQualityUsed($file->getMimeType());
                    
                    // Get new dimensions
                    $newImageInfo = getimagesize($optimizedFile->getRealPath());
                    if ($newImageInfo) {
                        $result['dimensions'] = [
                            'width' => $newImageInfo[0],
                            'height' => $newImageInfo[1]
                        ];
                    }
                }
            }

        } catch (\Exception $e) {
            // If optimization fails, return original file
            Log::warning('Image optimization failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $result['optimization_time'] = round((microtime(true) - $startTime) * 1000, 2);

        return $result;
    }

    /**
     * Optimize image using Intervention Image v3
     *
     * @param UploadedFile $file
     * @return UploadedFile
     */
    protected function optimizeImage(UploadedFile $file): UploadedFile
    {
        $image = $this->manager->read($file->getRealPath());

        // Resize if needed
        if ($image->width() > $this->maxWidth || $image->height() > $this->maxHeight) {
            $image->scale(
                width: $this->maxWidth,
                height: $this->maxHeight
            );
        }

        // Determine format and quality
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();

        // Create temporary file
        $tempPath = sys_get_temp_dir() . '/' . uniqid() . '.' . $extension;

        // Encode based on mime type
        if (str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg')) {
            $image->toJpeg($this->jpegQuality)->save($tempPath);
        } elseif (str_contains($mimeType, 'png')) {
            $image->toPng()->save($tempPath);
        } elseif (str_contains($mimeType, 'webp')) {
            $image->toWebp($this->jpegQuality)->save($tempPath);
        } else {
            // For other formats, save as is
            $image->save($tempPath);
        }

        // Create new UploadedFile from optimized image
        return new UploadedFile(
            $tempPath,
            $file->getClientOriginalName(),
            $mimeType,
            null,
            true // Mark as test file to avoid validation errors
        );
    }

    /**
     * Get quality used for optimization based on mime type
     *
     * @param string $mimeType
     * @return int|null
     */
    protected function getQualityUsed(string $mimeType): ?int
    {
        if (str_contains($mimeType, 'jpeg') || 
            str_contains($mimeType, 'jpg') || 
            str_contains($mimeType, 'webp')) {
            return $this->jpegQuality;
        } elseif (str_contains($mimeType, 'png')) {
            return $this->pngQuality;
        }
        
        return null;
    }

    /**
     * Set maximum dimensions for optimization
     *
     * @param int $width
     * @param int $height
     * @return self
     */
    public function setMaxDimensions(int $width, int $height): self
    {
        $this->maxWidth = $width;
        $this->maxHeight = $height;
        return $this;
    }

    /**
     * Set JPEG quality
     *
     * @param int $quality (0-100)
     * @return self
     */
    public function setJpegQuality(int $quality): self
    {
        $this->jpegQuality = max(0, min(100, $quality));
        return $this;
    }

    /**
     * Set PNG quality
     *
     * @param int $quality (0-100)
     * @return self
     */
    public function setPngQuality(int $quality): self
    {
        $this->pngQuality = max(0, min(100, $quality));
        return $this;
    }
}