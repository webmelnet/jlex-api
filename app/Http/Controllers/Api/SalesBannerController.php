<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesBanner;
use App\Services\S3UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesBannerController extends Controller
{
    public function __construct(protected S3UploadService $s3)
    {
    }

    /** GET /api/website/sales-banners  (public) */
    public function index()
    {
        $banners = SalesBanner::active()->ordered()->get();
        return response()->json($banners);
    }

    /** GET /api/website/sales-banners/all  (admin) */
    public function adminIndex()
    {
        $banners = SalesBanner::ordered()->get();
        return response()->json($banners);
    }

    /** POST /api/website/sales-banners */
    public function store(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'image'      => 'nullable|image|max:10240',
            'subtitle'   => 'nullable|string|max:255',
            'badge_text' => 'nullable|string|max:50',
            'cta_text'   => 'nullable|string|max:100',
            'cta_link'   => 'nullable|string|max:500',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
        ]);

        $data = [
            'title'      => $request->input('title'),
            'subtitle'   => $request->input('subtitle'),
            'badge_text' => $request->input('badge_text'),
            'cta_text'   => $request->input('cta_text'),
            'cta_link'   => $request->input('cta_link'),
            'bg_color'   => $request->input('bg_color', '#1e293b'),
            'text_color' => $request->input('text_color', '#ffffff'),
            'sort_order' => SalesBanner::max('sort_order') + 1,
            'is_active'  => true,
        ];

        if ($request->hasFile('image')) {
            $upload = $this->s3->uploadFile($request->file('image'), 'sales-banners');
            $data['image_path'] = $upload['url'];
        }

        $banner = SalesBanner::create($data);

        return response()->json($banner, 201);
    }

    /** PUT /api/website/sales-banners/{banner} */
    public function update(Request $request, SalesBanner $banner)
    {
        $request->validate([
            'title'      => 'nullable|string|max:255',
            'image'      => 'nullable|image|max:10240',
            'subtitle'   => 'nullable|string|max:255',
            'badge_text' => 'nullable|string|max:50',
            'cta_text'   => 'nullable|string|max:100',
            'cta_link'   => 'nullable|string|max:500',
            'bg_color'   => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'is_active'  => 'nullable|boolean',
        ]);

        $data = $request->only(['title', 'subtitle', 'badge_text', 'cta_text', 'cta_link', 'bg_color', 'text_color', 'is_active']);

        if ($request->hasFile('image')) {
            $upload = $this->s3->uploadFile($request->file('image'), 'sales-banners');
            $data['image_path'] = $upload['url'];
        }

        $banner->update($data);

        return response()->json($banner->fresh());
    }

    /** DELETE /api/website/sales-banners/{banner} */
    public function destroy(SalesBanner $banner)
    {
        $banner->delete();
        return response()->json(['message' => 'Banner deleted']);
    }

    /** POST /api/website/sales-banners/reorder */
    public function reorder(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:sales_banners,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('ids') as $i => $id) {
                SalesBanner::where('id', $id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['message' => 'Reordered']);
    }
}
