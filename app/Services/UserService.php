<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\UserInvitation as UserInvitationMail;
use App\Mail\PasswordReset;


class UserService
{
    public function createUser(array $data)
    {
        if (isset($data['dob'])) {
            $data['dob'] = date('Y-m-d', strtotime(str_replace('-', '/', $data['dob'])));
        }
    
        if (isset($data['picture'][0])) {
            $data['picture'] = $this->uploadPicture($data['picture'][0]);
        }
    
        // Create user without temporary password
        $data['password'] = bcrypt(Str::random(32)); // temporary password, will be changed by user
    
        $user = User::create($data);
    
        // Handle multiple roles from form submission format (roles[])
        if (isset($data['roles']) && is_array($data['roles'])) {
            $user->syncRoles($data['roles']);
        }
        // For backward compatibility - handle single role
        elseif (!empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }
    
        // Send invitation email
        $this->sendInvitationEmail($user);
    
        return $user;
    }

    public function updateUser(User $user, array $data)
    {
        if (isset($data['dob'])) {
            $data['dob'] = date('Y-m-d', strtotime(str_replace('-', '/', $data['dob'])));
        }

        if (isset($data['picture'][0]) && is_array($data['picture'])) {
            $this->deletePicture($user->picture);
            $data['picture'] = $this->uploadPicture($data['picture'][0]);
        }

        if (isset($data['password']) && empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        // Handle multiple roles if provided
        if (!empty($data['roles']) && is_array($data['roles'])) {
            $user->syncRoles($data['roles']);
        }
        // For backward compatibility - handle single role
        elseif (!empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user;
    }

    public function deleteUser(User $user)
    {
        $this->deletePicture($user->picture);
        $user->delete();
    }

    public function getUsersByRole(string $roleName)
    {
        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            return null;
        }

        return User::role($roleName)->get();
    }

    public function getEstimatorUsers()
    {
        return $this->getUsersByRole('Estimator');
    }

    private function uploadPicture($picture)
    {
        $path = $picture->store('user_pictures', 'public');
        return $path;
    }

    private function deletePicture($path)
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
    private function sendInvitationEmail(User $user)
    {
        $token = Str::random(64);

        // Create invitation record
        UserInvitation::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => $token
        ]);

        $invitationUrl = config('app.frontend_url') . '/set-password/' . $token;

        // Use renamed mail class
        Mail::to($user->email)->send(new UserInvitationMail($user, $invitationUrl));
    }

    public function resetUserPassword(User $user, string $newPassword)
    {
        $user->password = bcrypt($newPassword);
        $user->save();

        $this->sendPasswordResetEmail($user, $newPassword);
    }

    private function sendPasswordResetEmail(User $user, string $newPassword)
    {
        $loginUrl = config('app.frontend_url') . '/login';
        Mail::to($user->email)->send(new PasswordReset($user, $loginUrl, $newPassword));
    }
}