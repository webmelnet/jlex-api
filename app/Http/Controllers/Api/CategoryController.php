<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(Request $request)
    {
        $query = Category::with(['parent', 'children', 'image'])->ordered();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('parent_id')) {
            if ($request->parent_id === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        $categories = $query->get();

        return response()->json($categories);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:categories,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('ids') as $i => $id) {
                Category::where('id', $id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['message' => 'Reordered']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'image'       => 'nullable|image|max:2048',
            'is_active'   => 'nullable|boolean',
        ]);

        $category = $this->categoryService->createCategory($validated);

        return response()->json([
            'status'   => 'Category created successfully',
            'category' => $category,
        ], 201);
    }

    public function show(Category $category)
    {
        return response()->json($category->load(['parent', 'children', 'products', 'image']));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'image'       => 'nullable|image|max:2048',
            'is_active'   => 'nullable|boolean',
        ]);

        $category = $this->categoryService->updateCategory($category, $validated);

        return response()->json([
            'status'   => 'Category updated successfully',
            'category' => $category,
        ]);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $category = Category::withTrashed()->findOrFail($id);
        $category->restore();
        return response()->json($category->load('image'), 200);
    }

    public function forceDelete($id)
    {
        $category = Category::withTrashed()->findOrFail($id);
        $this->categoryService->forceDeleteCategory($category);
        return response()->json(null, 204);
    }

    public function trashedCategories()
    {
        $categories = Category::onlyTrashed()->with('image')->get();
        return response()->json($categories);
    }

    public function removeImage(Category $category)
    {
        $this->categoryService->removeImage($category);
        return response()->json(['status' => 'Image removed successfully']);
    }
}
