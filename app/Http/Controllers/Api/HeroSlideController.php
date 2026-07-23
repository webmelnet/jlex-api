<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Services\S3UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HeroSlideController extends Controller
{
    public function __construct(protected S3UploadService $s3)
    {
    }

    /** GET /api/website/hero-slides  (public) */
    public function index()
    {
        $slides = HeroSlide::active()->ordered()->get();
        return response()->json($slides);
    }

    /** GET /api/website/hero-slides/all  (admin) */
    public function adminIndex()
    {
        $slides = HeroSlide::ordered()->get();
        return response()->json($slides);
    }

    /** POST /api/website/hero-slides */
    public function store(Request $request)
    {
        $request->validate([
            'image'    => 'required|image|max:10240',
            'title'    => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'cta_text' => 'nullable|string|max:100',
            'cta_link' => 'nullable|string|max:500',
        ]);

        $upload = $this->s3->uploadFile($request->file('image'), 'hero-slides');

        $slide = HeroSlide::create([
            'image_path' => $upload['url'],
            'title'      => $request->input('title'),
            'subtitle'   => $request->input('subtitle'),
            'cta_text'   => $request->input('cta_text'),
            'cta_link'   => $request->input('cta_link'),
            'sort_order' => HeroSlide::max('sort_order') + 1,
            'is_active'  => true,
        ]);

        return response()->json($slide, 201);
    }

    /** PUT /api/website/hero-slides/{slide} */
    public function update(Request $request, HeroSlide $slide)
    {
        $request->validate([
            'image'     => 'nullable|image|max:10240',
            'title'     => 'nullable|string|max:255',
            'subtitle'  => 'nullable|string|max:255',
            'cta_text'  => 'nullable|string|max:100',
            'cta_link'  => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $data = $request->only(['title', 'subtitle', 'cta_text', 'cta_link', 'is_active']);

        if ($request->hasFile('image')) {
            $upload = $this->s3->uploadFile($request->file('image'), 'hero-slides');
            $data['image_path'] = $upload['url'];
        }

        $slide->update($data);

        return response()->json($slide->fresh());
    }

    /** DELETE /api/website/hero-slides/{slide} */
    public function destroy(HeroSlide $slide)
    {
        $slide->delete();
        return response()->json(['message' => 'Slide deleted']);
    }

    /** POST /api/website/hero-slides/reorder */
    public function reorder(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:hero_slides,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('ids') as $i => $id) {
                HeroSlide::where('id', $id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['message' => 'Reordered']);
    }
}
