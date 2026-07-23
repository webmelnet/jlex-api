<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function createCategory(array $data): Category
    {
        DB::beginTransaction();
        try {
            $image = $data['image'] ?? null;
            unset($data['image']);

            $category = Category::create($data);

            if ($image instanceof UploadedFile) {
                $this->imageService->uploadAndAttach(
                    $category,
                    $image,
                    true,
                    null,
                    'categories'
                );
            }

            DB::commit();
            return $category->load(['parent', 'children', 'image']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateCategory(Category $category, array $data): Category
    {
        DB::beginTransaction();
        try {
            $image = $data['image'] ?? null;
            unset($data['image']);

            $category->update($data);

            if ($image instanceof UploadedFile) {
                if ($category->image) {
                    $this->imageService->deleteImage($category->image);
                }
                $this->imageService->uploadAndAttach(
                    $category,
                    $image,
                    true,
                    null,
                    'categories'
                );
            }

            DB::commit();
            return $category->fresh(['parent', 'children', 'image']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function forceDeleteCategory(Category $category): void
    {
        DB::beginTransaction();
        try {
            if (!$category->relationLoaded('image')) {
                $category->load('image');
            }

            if ($category->image) {
                $this->imageService->deleteImage($category->image);
            }

            $category->forceDelete();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function removeImage(Category $category): bool
    {
        if ($category->image) {
            return $this->imageService->deleteImage($category->image);
        }
        return false;
    }
}
