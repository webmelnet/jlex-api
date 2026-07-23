<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImageService
{
    protected $s3UploadService;

    public function __construct(S3UploadService $s3UploadService)
    {
        $this->s3UploadService = $s3UploadService;
    }

    /**
     * Upload and attach image to a model
     *
     * @param Model $model The model to attach image to (e.g., Product)
     * @param UploadedFile $file The uploaded file
     * @param bool $isPrimary Whether this is the primary image
     * @param string|null $altText Optional alt text for SEO
     * @param string $folder S3 folder (default: products)
     * @return Image
     */
    public function uploadAndAttach(
        Model $model,
        UploadedFile $file,
        bool $isPrimary = false,
        ?string $altText = null,
        string $folder = 'products'
    ): Image {
        try {
            // Upload to S3
            $uploadResult = $this->s3UploadService->uploadFile($file, $folder);

            // Prepare image data
            $imageData = [
                'url' => $uploadResult['url'],
                'path' => $uploadResult['s3_path'],
                'filename' => basename($uploadResult['s3_path']),
                'original_filename' => $uploadResult['file_name'],
                'file_size' => $uploadResult['final_size'],
                'mime_type' => $uploadResult['file_type'],
                'width' => $uploadResult['dimensions']['width'] ?? null,
                'height' => $uploadResult['dimensions']['height'] ?? null,
                'alt_text' => $altText,
                'metadata' => [
                    'was_optimized' => $uploadResult['was_optimized'],
                    'original_size' => $uploadResult['original_size'],
                    'final_size' => $uploadResult['final_size'],
                    'quality_used' => $uploadResult['quality_used'],
                    'optimization_time' => $uploadResult['optimization_time'],
                ],
            ];

            // If setting as primary, unset other primary images
            if ($isPrimary) {
                $model->images()->update(['is_primary' => false]);
            }

            // Get next position
            $nextPosition = $model->images()->max('position') + 1;

            // Create image record
            $image = $model->images()->create(array_merge($imageData, [
                'is_primary' => $isPrimary,
                'position' => $nextPosition,
            ]));

            Log::info('Image attached to model', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'image_id' => $image->id,
                'is_primary' => $isPrimary,
            ]);

            return $image;

        } catch (\Exception $e) {
            Log::error('Failed to upload and attach image', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Upload and attach multiple images to a model
     *
     * @param Model $model
     * @param array $files Array of UploadedFile
     * @param bool $firstIsPrimary Whether the first image should be primary
     * @param string $folder
     * @return array Array of Image models
     */
    public function uploadAndAttachMultiple(
        Model $model,
        array $files,
        bool $firstIsPrimary = true,
        string $folder = 'products'
    ): array {
        $images = [];

        DB::beginTransaction();
        try {
            foreach ($files as $index => $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $isPrimary = ($index === 0 && $firstIsPrimary);
                $images[] = $this->uploadAndAttach($model, $file, $isPrimary, null, $folder);
            }

            DB::commit();
            return $images;

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded images from S3
            foreach ($images as $image) {
                $this->s3UploadService->deleteFile($image->url);
                $image->delete();
            }

            throw $e;
        }
    }

    /**
     * Delete image and remove from S3
     *
     * @param Image $image
     * @return bool
     */
    public function deleteImage(Image $image): bool
    {
        try {
            // Delete from S3
            $this->s3UploadService->deleteFile($image->url);

            // If this was primary, set another image as primary
            if ($image->is_primary) {
                $nextImage = Image::where('imageable_type', $image->imageable_type)
                    ->where('imageable_id', $image->imageable_id)
                    ->where('id', '!=', $image->id)
                    ->ordered()
                    ->first();

                if ($nextImage) {
                    $nextImage->setAsPrimary();
                }
            }

            // Delete from database
            $image->delete();

            Log::info('Image deleted', [
                'image_id' => $image->id,
                'url' => $image->url,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to delete image', [
                'image_id' => $image->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete multiple images
     *
     * @param array $imageIds
     * @return int Number of images deleted
     */
    public function deleteMultiple(array $imageIds): int
    {
        $count = 0;

        foreach ($imageIds as $imageId) {
            $image = Image::find($imageId);
            if ($image && $this->deleteImage($image)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Replace model's primary image
     *
     * @param Model $model
     * @param UploadedFile $file
     * @param string|null $altText
     * @param string $folder
     * @return Image
     */
    public function replacePrimaryImage(
        Model $model,
        UploadedFile $file,
        ?string $altText = null,
        string $folder = 'products'
    ): Image {
        DB::beginTransaction();
        try {
            // Get current primary image
            $oldPrimary = $model->primaryImage;

            // Upload new image
            $newImage = $this->uploadAndAttach($model, $file, true, $altText, $folder);

            // Delete old primary image
            if ($oldPrimary) {
                $this->deleteImage($oldPrimary);
            }

            DB::commit();
            return $newImage;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update image metadata
     *
     * @param Image $image
     * @param array $data
     * @return Image
     */
    public function updateImage(Image $image, array $data): Image
    {
        $image->update($data);
        return $image->fresh();
    }

    /**
     * Set image as primary
     *
     * @param Image $image
     * @return Image
     */
    public function setAsPrimary(Image $image): Image
    {
        $image->setAsPrimary();
        return $image->fresh();
    }

    /**
     * Reorder images
     *
     * @param array $imageIds Array of image IDs in desired order
     * @return bool
     */
    public function reorder(array $imageIds): bool
    {
        try {
            Image::reorder($imageIds);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reorder images', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get all images for a model
     *
     * @param Model $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getModelImages(Model $model)
    {
        return $model->images()->ordered()->get();
    }

    /**
     * Get primary image for a model
     *
     * @param Model $model
     * @return Image|null
     */
    public function getPrimaryImage(Model $model): ?Image
    {
        return $model->primaryImage;
    }

    /**
     * Copy images from one model to another
     *
     * @param Model $sourceModel
     * @param Model $targetModel
     * @return array Array of new Image models
     */
    public function copyImages(Model $sourceModel, Model $targetModel): array
    {
        $newImages = [];
        $sourceImages = $sourceModel->images()->ordered()->get();

        foreach ($sourceImages as $sourceImage) {
            $newImage = $targetModel->images()->create([
                'url' => $sourceImage->url,
                'path' => $sourceImage->path,
                'filename' => $sourceImage->filename,
                'original_filename' => $sourceImage->original_filename,
                'file_size' => $sourceImage->file_size,
                'mime_type' => $sourceImage->mime_type,
                'width' => $sourceImage->width,
                'height' => $sourceImage->height,
                'is_primary' => $sourceImage->is_primary,
                'position' => $sourceImage->position,
                'alt_text' => $sourceImage->alt_text,
                'metadata' => $sourceImage->metadata,
            ]);

            $newImages[] = $newImage;
        }

        return $newImages;
    }

    /**
     * Delete all images for a model
     *
     * @param Model $model
     * @return int Number of images deleted
     */
    public function deleteAllModelImages(Model $model): int
    {
        $images = $model->images;
        $count = 0;

        foreach ($images as $image) {
            if ($this->deleteImage($image)) {
                $count++;
            }
        }

        return $count;
    }
}