<?php

namespace App\Http\Controllers;

use App\Models\JobSeeker;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Mail\WelcomeMail;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use DB;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','max:191','unique:users,email'],
            'password' => ['required', PasswordRule::min(8)],
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

        // try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        // } catch (\Throwable $e) {
        //     report($e);
        // }

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


    public function sendResetEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return response()->api(null, false, 'User not found', 404);
        }

        // 1️⃣ Generate plain token (sent to frontend)
        $plainToken = Str::random(64);

        // 2️⃣ Store HASHED token in your table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $validated['email']],
            [
                'email'      => $validated['email'],
                'token'      => Hash::make($plainToken),
                'created_at' => now(),
            ]
        );

        // 3️⃣ Create frontend reset URL
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $resetUrl = "{$frontendUrl}/reset-password?token={$plainToken}&email=" . urlencode($validated['email']);

        // 4️⃣ Send email
        Mail::to($validated['email'])->send(
            new PasswordResetMail($resetUrl)
        );

        return response()->api(null, true, 'Password reset email sent successfully', 200);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // requires password_confirmation
        ]);

        // 1) Find token record
        $row = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$row) {
            return response()->api(null, false, 'Invalid token or email', 422);
        }

        // 2) Expire token (60 minutes)
        if (!empty($row->created_at)) {
            $createdAt = \Carbon\Carbon::parse($row->created_at);
            if ($createdAt->lt(now()->subMinutes(60))) {
                DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
                return response()->api(null, false, 'Token expired', 422);
            }
        }

        // 3) Verify token (plain token from frontend vs hashed in DB)
        if (!Hash::check($validated['token'], $row->token)) {
            return response()->api(null, false, 'Invalid token or email', 422);
        }

        // 4) Update user password
        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return response()->api(null, false, 'User not found', 404);
        }

        $user->password = Hash::make($validated['password']);
        $user->remember_token = Str::random(60);
        $user->save();

        // 5) Delete reset record (one-time use)
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->api(null, true, 'Password updated successfully', 200);
    }
}


