<?php

namespace App\Http\Controllers;

use App\Models\JobSeeker;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','max:191','unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['in:seeker,company,admin'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'seeker',
        ]);

        if ($user->role === 'seeker') {
            JobSeeker::create(['user_id' => $user->id]);
        }
        if ($user->role === 'company') {
            $company = Company::create(['name' => $user->name . ' Company']);
            CompanyUser::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;
        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);
        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password ?? '')) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }
        $token = $user->createToken('api')->plainTextToken;
        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return $request->user();
    }
}


