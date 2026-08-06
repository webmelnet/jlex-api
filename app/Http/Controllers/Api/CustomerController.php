<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('id_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->get();

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'zip_code' => 'nullable|string',
            'country' => 'nullable|string',
            'loyalty_points' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'customer_category' => 'nullable|in:regular,pwd,senior',
            'id_number' => 'nullable|string|max:100',
            'id_expiry_date' => 'nullable|date',
        ]);

        $customer = Customer::create($validated);

        return response()->json([
            'status' => 'Customer created successfully',
            'customer' => $customer
        ], 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer->load(['sales' => function ($query) {
            $query->with(['items.product', 'user', 'staffUser', 'queue.createdBy'])->orderByDesc('sale_date');
        }]));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'zip_code' => 'nullable|string',
            'country' => 'nullable|string',
            'loyalty_points' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'customer_category' => 'nullable|in:regular,pwd,senior',
            'id_number' => 'nullable|string|max:100',
            'id_expiry_date' => 'nullable|date',
        ]);

        $customer->update($validated);

        return response()->json([
            'status' => 'Customer updated successfully',
            'customer' => $customer
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(null, 204);
    }

    public function addLoyaltyPoints(Request $request, Customer $customer)
    {
        abort_unless($request->user()->hasRole('Superadmin'), 403);

        $validated = $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $customer->addLoyaltyPoints($validated['points']);

        return response()->json([
            'status' => 'Loyalty points added successfully',
            'customer' => $customer
        ]);
    }

    public function deductLoyaltyPoints(Request $request, Customer $customer)
    {
        abort_unless($request->user()->hasRole('Superadmin'), 403);

        $validated = $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $result = $customer->deductLoyaltyPoints($validated['points']);

        if ($result) {
            return response()->json([
                'status' => 'Loyalty points deducted successfully',
                'customer' => $customer
            ]);
        }

        return response()->json([
            'error' => 'Insufficient loyalty points'
        ], 400);
    }

    public function restore($id)
    {
        $customer = Customer::withTrashed()->findOrFail($id);
        $customer->restore();
        return response()->json($customer, 200);
    }

    public function forceDelete($id)
    {
        $customer = Customer::withTrashed()->findOrFail($id);
        $customer->forceDelete();
        return response()->json(null, 204);
    }

    public function trashedCustomers()
    {
        $customers = Customer::onlyTrashed()->get();
        return response()->json($customers);
    }
}
