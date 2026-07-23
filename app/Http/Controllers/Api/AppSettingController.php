<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    /**
     * GET /api/app-settings
     * Returns all settings as a flat key => value JSON object.
     * Available to any authenticated user (e.g. POS reads this to know whether to show its shortcuts panel).
     */
    public function index()
    {
        return response()->json(AppSetting::asObject());
    }

    /**
     * GET /api/app-settings/all  (Superadmin only)
     * Returns full rows (id, key, value, label) for the Settings admin panel.
     */
    public function adminIndex(Request $request)
    {
        abort_unless($request->user()->hasRole('Superadmin'), 403);

        return response()->json(AppSetting::all());
    }

    /**
     * POST /api/app-settings  (Superadmin only)
     * Accepts { configurations: [{ key, value }] } and upserts each entry.
     */
    public function bulkUpdate(Request $request)
    {
        abort_unless($request->user()->hasRole('Superadmin'), 403);

        $request->validate([
            'configurations'        => 'required|array|min:1',
            'configurations.*.key'  => 'required|string|max:100',
            'configurations.*.value'=> 'required|string|max:10000',
        ]);

        foreach ($request->input('configurations') as $item) {
            AppSetting::updateOrCreate(
                ['key' => $item['key']],
                ['value' => $item['value']]
            );
        }

        return response()->json(AppSetting::all());
    }
}
