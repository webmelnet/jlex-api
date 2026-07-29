<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TimeLogController extends Controller
{
    /**
     * POST /api/time-logs/clock-in
     */
    public function clockIn(Request $request)
    {
        $user = $request->user();

        $openEntry = TimeLog::where('user_id', $user->id)->open()->first();
        if ($openEntry) {
            return response()->json(['message' => 'You are already clocked in.'], 422);
        }

        $entry = TimeLog::create([
            'user_id'   => $user->id,
            'work_date' => today(),
            'clock_in'  => now(),
        ]);

        return response()->json([
            'message' => 'Clocked in successfully.',
            'entry'   => $entry->load('user'),
        ], 201);
    }

    /**
     * POST /api/time-logs/clock-out
     */
    public function clockOut(Request $request)
    {
        $user = $request->user();

        $openEntry = TimeLog::where('user_id', $user->id)->open()->latest('clock_in')->first();
        if (!$openEntry) {
            return response()->json(['message' => 'You are not currently clocked in.'], 422);
        }

        $openEntry->update(['clock_out' => now()]);

        return response()->json([
            'message' => 'Clocked out successfully.',
            'entry'   => $openEntry->load('user'),
        ]);
    }

    /**
     * GET /api/time-logs/my-status
     * Returns the current open session (if any) and today's sessions for the authenticated user.
     */
    public function myStatus(Request $request)
    {
        $user = $request->user();

        $todayEntries = TimeLog::where('user_id', $user->id)
            ->today()
            ->orderBy('clock_in')
            ->get();

        return response()->json([
            'open_entry'    => $todayEntries->firstWhere('clock_out', null),
            'today_entries' => $todayEntries,
        ]);
    }

    /**
     * GET /api/time-logs/my-history
     */
    public function myHistory(Request $request)
    {
        $query = TimeLog::where('user_id', $request->user()->id);

        if ($request->filled('date_from')) {
            $query->whereDate('work_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('work_date', '<=', $request->date_to);
        }

        $entries = $query->orderByDesc('work_date')->orderByDesc('clock_in')->get();

        return response()->json($entries);
    }

    /**
     * GET /api/time-logs  (Superadmin/Admin only) — DTR management listing.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['Superadmin', 'Admin']), 403);

        $query = TimeLog::with(['user.roles', 'creator']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('role')) {
            $query->whereHas('user.roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('work_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('work_date', '<=', $request->date_to);
        }

        $entries = $query->orderByDesc('work_date')->orderByDesc('clock_in')->get();

        return response()->json($entries);
    }

    /**
     * POST /api/time-logs  (Superadmin/Admin only) — manual entry, e.g. to backfill a forgotten clock-in.
     */
    public function store(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['Superadmin', 'Admin']), 403);

        $validated = $request->validate([
            'user_id'   => 'required|exists:users,id',
            'work_date' => 'required|date',
            'clock_in'  => 'required|date',
            'clock_out' => 'nullable|date|after:clock_in',
            'notes'     => 'nullable|string|max:500',
        ]);

        $entry = TimeLog::create($validated + ['created_by' => $request->user()->id]);

        return response()->json([
            'message' => 'Time log created successfully.',
            'entry'   => $entry->load('user'),
        ], 201);
    }

    /**
     * PUT /api/time-logs/{timeLog}  (Superadmin/Admin only) — correct an entry.
     */
    public function update(Request $request, TimeLog $timeLog)
    {
        abort_unless($request->user()->hasAnyRole(['Superadmin', 'Admin']), 403);

        $validated = $request->validate([
            'work_date' => 'required|date',
            'clock_in'  => 'required|date',
            'clock_out' => 'nullable|date|after:clock_in',
            'notes'     => 'nullable|string|max:500',
        ]);

        $timeLog->update($validated);

        return response()->json([
            'message' => 'Time log updated successfully.',
            'entry'   => $timeLog->load('user'),
        ]);
    }

    /**
     * DELETE /api/time-logs/{timeLog}  (Superadmin/Admin only)
     */
    public function destroy(Request $request, TimeLog $timeLog)
    {
        abort_unless($request->user()->hasAnyRole(['Superadmin', 'Admin']), 403);

        $timeLog->delete();

        return response()->json(['message' => 'Time log deleted successfully.']);
    }
}
