<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SupportService;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function submitDeletedUserSupport(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        try {
            $supportService = new SupportService();
            $supportService->sendDeletedUserSupportEmail($validatedData);

            return response()->json([
                'message' => 'Your support request has been submitted successfully. We will get back to you soon.'
            ], 200);
        } catch (\Exception $e) {
            // Log the actual error for debugging
            \Log::error('Support request failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $validatedData ?? $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to submit support request. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}