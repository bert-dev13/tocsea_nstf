<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /**
     * Show the admin settings page.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return view('admin.settings.index', [
            'user' => $user,
        ]);
    }

    /**
     * Update the authenticated admin's profile (name, email).
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Change the authenticated admin's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->password = \Illuminate\Support\Facades\Hash::make($request->validated('new_password'));
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
