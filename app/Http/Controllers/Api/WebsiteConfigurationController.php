<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebsiteConfiguration;
use Illuminate\Http\Request;

class WebsiteConfigurationController extends Controller
{
    /**
     * GET /api/website/settings  (public)
     * Returns all configurations as a flat key => value JSON object.
     */
    public function index()
    {
        return response()->json(WebsiteConfiguration::asObject());
    }

    /**
     * GET /api/website/configurations  (admin)
     * Returns full rows (id, key, value, label) for the admin panel.
     */
    public function adminIndex()
    {
        return response()->json(WebsiteConfiguration::all());
    }

    /**
     * POST /api/website/configurations  (admin)
     * Accepts { configurations: [{ key, value }] } and upserts each entry.
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'configurations'        => 'required|array|min:1',
            'configurations.*.key'  => 'required|string|max:100',
            'configurations.*.value'=> 'required|string|max:10000',
        ]);

        foreach ($request->input('configurations') as $item) {
            WebsiteConfiguration::updateOrCreate(
                ['key' => $item['key']],
                ['value' => $item['value']]
            );
        }

        return response()->json(WebsiteConfiguration::all());
    }
}
