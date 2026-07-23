<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index()
    {
        $users = User::with(['roles'])->get();

        $usersWithRoles = $users->map(function ($user) {
            $userData = $user->toArray();
            $userData['role'] = $user->getRoleAttribute(); // For backward compatibility
            $userData['roles_list'] = $user->getRolesListAttribute(); // New array of all user roles
            return $userData;
        });

        return response()->json($usersWithRoles);
    }

    public function store(UserRequest $request)
    {
        $user = $this->userService->createUser($request->validated());

        return response()->json(['status' => 'New user created successfully and invitation sent'], 201);
    }

    public function update(UserRequest $request, User $user)
    {
        $updatedUser = $this->userService->updateUser($user, $request->validated());
        return response()->json(['status' => 'User record has been successfully updated'], 200);
    }

    public function updateRoles(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        // Sync the user's roles with the provided role names
        $user->syncRoles($request->roles);

        return response()->json([
            'status' => 'User roles updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name')
            ]
        ], 200);
    }
    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:8',
            'repassword' => 'required|string|same:password',
        ]);

        $user = User::findOrFail($id);
        $this->userService->resetUserPassword($user, $request->password);

        return response()->json(['status' => 'Password reset successfully and email sent to the user'], 200);
    }

    public function destroy(User $user)
    {
        //$this->userService->deleteUser($user);
        $user->delete();
        return response()->json(null, 204);
    }

    public function forceDelete($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->forceDelete();
        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return response()->json($user->load('roles'), 200);
    }

    public function trashedUsers()
    {
        $trashedUsers = User::onlyTrashed()->with('roles')->get();
        return response()->json($trashedUsers, 200);
    }

    public function show(User $user)
    {
        $user->role = $user->getRoleAttribute(); // For backward compatibility
        $user->roles_list = $user->getRolesListAttribute(); // Add array of all roles
        return response()->json($user);
    }

    public function getUsersByRole($roleName)
    {
        $users = $this->userService->getUsersByRole($roleName);

        if ($users === null) {
            return response()->json(['error' => 'Role not found'], 404);
        }

        return response()->json($users);
    }

    public function getEstimatorUsers()
    {
        $estimators = $this->userService->getEstimatorUsers();

        return response()->json($estimators);
    }

    public function acceptInvitation(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password'
        ]);

        $invitation = UserInvitation::where('token', $request->token)
            ->where('created_at', '>', now()->subHours(48))
            ->first();

        if (!$invitation) {
            return response()->json(['error' => 'Invalid or expired invitation token'], 400);
        }

        $user = User::find($invitation->user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->password = bcrypt($request->password);
        $user->email_verified_at = now();
        $user->save();

        $invitation->delete();

        return response()->json(['message' => 'Password set successfully'], 200);
    }

    public function verifyInvitation($token)
    {
        $invitation = UserInvitation::where('token', $token)
            ->where('created_at', '>', now()->subHours(48))
            ->first();

        if (!$invitation) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired invitation token'
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'email' => $invitation->email
        ]);
    }

    public function setPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        $invitation = UserInvitation::where('token', $request->token)
            ->where('created_at', '>', now()->subHours(48))
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invalid or expired invitation token'
            ], 400);
        }

        $user = User::find($invitation->user_id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Update password
        $user->password = bcrypt($request->password);
        $user->email_verified_at = now(); // Mark email as verified
        $user->save();

        // Delete the invitation
        $invitation->delete();

        return response()->json([
            'message' => 'Password set successfully'
        ]);
    }
}