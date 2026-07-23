<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BrandService
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function createBrand(array $data): Brand
    {
        DB::beginTransaction();
        try {
            $image = $data['image'] ?? null;
            unset($data['image']);

            $brand = Brand::create($data);

            if ($image instanceof UploadedFile) {
                $this->imageService->uploadAndAttach(
                    $brand,
                    $image,
                    true,    // is_primary
                    null,    // alt_text
                    'brands' // S3 folder
                );
            }

            DB::commit();
            return $brand->load('image');

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateBrand(Brand $brand, array $data): Brand
    {
        DB::beginTransaction();
        try {
            $image = $data['image'] ?? null;
            unset($data['image']);

            $brand->update($data);

            if ($image instanceof UploadedFile) {
                // Brand has only 1 image — always replace it
                if ($brand->image) {
                    $this->imageService->deleteImage($brand->image);
                }
                $this->imageService->uploadAndAttach(
                    $brand,
                    $image,
                    true,
                    null,
                    'brands'
                );
            }

            DB::commit();
            return $brand->fresh('image');

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteBrand(Brand $brand): void
    {
        DB::beginTransaction();
        try {
            $brand->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function forceDeleteBrand(Brand $brand): void
    {
        DB::beginTransaction();
        try {
            if (!$brand->relationLoaded('image')) {
                $brand->load('image');
            }

            if ($brand->image) {
                $this->imageService->deleteImage($brand->image);
            }

            $brand->forceDelete();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function removeImage(Brand $brand): bool
    {
        if ($brand->image) {
            return $this->imageService->deleteImage($brand->image);
        }
        return false;
    }
}