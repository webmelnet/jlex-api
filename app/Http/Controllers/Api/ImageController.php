<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImageController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Upload image to a product
     * POST /api/products/{product}/images
     */
    public function store(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:10240', // Max 10MB
            'is_primary' => 'nullable|boolean',
            'alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $image = $this->imageService->uploadAndAttach(
                $product,
                $request->file('image'),
                $request->boolean('is_primary', false),
                $request->input('alt_text'),
                'products'
            );

            return response()->json([
                'message' => 'Image uploaded successfully',
                'image' => $image
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload multiple images to a product
     * POST /api/products/{product}/images/bulk
     */
    public function bulkStore(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|max:10240',
            'first_is_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $images = $this->imageService->uploadAndAttachMultiple(
                $product,
                $request->file('images'),
                $request->boolean('first_is_primary', true)
            );

            return response()->json([
                'message' => 'Images uploaded successfully',
                'images' => $images,
                'count' => count($images)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload images',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all images for a product
     * GET /api/products/{product}/images
     */
    public function index(Product $product)
    {
        $images = $product->images()->ordered()->get();

        return response()->json([
            'images' => $images,
            'count' => $images->count()
        ]);
    }

    /**
     * Get a specific image
     * GET /api/images/{image}
     */
    public function show(Image $image)
    {
        return response()->json($image);
    }

    /**
     * Update image metadata
     * PUT/PATCH /api/images/{image}
     */
    public function update(Request $request, Image $image)
    {
        $validator = Validator::make($request->all(), [
            'alt_text' => 'nullable|string|max:255',
            'position' => 'nullable|integer|min:0',
            'is_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only(['alt_text', 'position']);

            // Handle is_primary separately
            if ($request->has('is_primary') && $request->boolean('is_primary')) {
                $this->imageService->setAsPrimary($image);
            }

            if (!empty($data)) {
                $image = $this->imageService->updateImage($image, $data);
            }

            return response()->json([
                'message' => 'Image updated successfully',
                'image' => $image
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an image
     * DELETE /api/images/{image}
     */
    public function destroy(Image $image)
    {
        try {
            $this->imageService->deleteImage($image);

            return response()->json([
                'message' => 'Image deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple images
     * DELETE /api/images/bulk
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_ids' => 'required|array|min:1',
            'image_ids.*' => 'required|integer|exists:images,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $count = $this->imageService->deleteMultiple($request->input('image_ids'));

            return response()->json([
                'message' => "Successfully deleted {$count} images",
                'count' => $count
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete images',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set image as primary
     * POST /api/images/{image}/set-primary
     */
    public function setPrimary(Image $image)
    {
        try {
            $image = $this->imageService->setAsPrimary($image);

            return response()->json([
                'message' => 'Image set as primary successfully',
                'image' => $image
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to set image as primary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder images for a product
     * POST /api/products/{product}/images/reorder
     */
    public function reorder(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'image_ids' => 'required|array|min:1',
            'image_ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify all images belong to this product
            $imageIds = $request->input('image_ids');
            $productImageIds = $product->images->pluck('id')->toArray();
            
            $invalidIds = array_diff($imageIds, $productImageIds);
            
            if (!empty($invalidIds)) {
                return response()->json([
                    'message' => 'Some images do not belong to this product',
                    'invalid_ids' => $invalidIds
                ], 422);
            }

            $this->imageService->reorder($imageIds);

            return response()->json([
                'message' => 'Images reordered successfully',
                'images' => $product->images()->ordered()->get()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reorder images',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}