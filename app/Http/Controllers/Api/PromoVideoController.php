<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoVideoController extends Controller
{
    /** GET /api/website/promo-videos  (public) */
    public function index()
    {
        $videos = PromoVideo::active()->ordered()->get();
        return response()->json($videos);
    }

    /** GET /api/website/promo-videos/all  (admin) */
    public function adminIndex()
    {
        $videos = PromoVideo::ordered()->get();
        return response()->json($videos);
    }

    /** POST /api/website/promo-videos */
    public function store(Request $request)
    {
        $request->validate([
            'embed_url' => 'required|string|max:1000',
            'title'     => 'nullable|string|max:255',
            'subtitle'  => 'nullable|string|max:255',
        ]);

        $video = PromoVideo::create([
            'embed_url'  => $request->input('embed_url'),
            'title'      => $request->input('title'),
            'subtitle'   => $request->input('subtitle'),
            'sort_order' => PromoVideo::max('sort_order') + 1,
            'is_active'  => true,
        ]);

        return response()->json($video, 201);
    }

    /** PUT /api/website/promo-videos/{video} */
    public function update(Request $request, PromoVideo $video)
    {
        $request->validate([
            'embed_url' => 'nullable|string|max:1000',
            'title'     => 'nullable|string|max:255',
            'subtitle'  => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $video->update($request->only(['embed_url', 'title', 'subtitle', 'is_active']));

        return response()->json($video->fresh());
    }

    /** DELETE /api/website/promo-videos/{video} */
    public function destroy(PromoVideo $video)
    {
        $video->delete();
        return response()->json(['message' => 'Video deleted']);
    }

    /** POST /api/website/promo-videos/reorder */
    public function reorder(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:promo_videos,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('ids') as $i => $id) {
                PromoVideo::where('id', $id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['message' => 'Reordered']);
    }
}
