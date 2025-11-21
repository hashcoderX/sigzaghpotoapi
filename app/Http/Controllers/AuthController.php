<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        Log::info('Register attempt payload', [
            'payload' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept'),
        ]);

        // Normalize auto_renew incoming checkbox values like "on"/"off" to boolean prior to validation
        if ($request->has('auto_renew')) {
            $raw = $request->input('auto_renew');
            if (is_string($raw)) {
                $lower = strtolower($raw);
                if ($lower === 'on') { $request->merge(['auto_renew' => true]); }
                elseif ($lower === 'off') { $request->merge(['auto_renew' => false]); }
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', 'string', 'in:photographer,business,buyer'],
            // Contact fields
            'phone' => ['nullable','string','max:30'],
            'whatsapp' => ['nullable','string','max:30'],
            'website' => ['nullable','string','max:255'],
            // Optional profile/subscription fields
            'country' => ['nullable','string','max:100'],
            'address' => ['nullable','string'],
            'currency' => ['nullable','string','size:3'],
            // Branding assets
            'logo' => ['nullable','image','max:2048'],
            'cover' => ['nullable','image','max:4096'],
        ]);

        // password is automatically hashed by User model casts
        $now = Carbon::now();
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'website' => $validated['website'] ?? null,
            'country' => $validated['country'] ?? null,
            'address' => $validated['address'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'payment_provider' => null,
            'payment_customer_id' => null,
            // Free trial window (30 days) - stored but not enforced by middleware
            'trial_started_at' => $now,
            'trial_ends_at' => $now->copy()->addDays(30),
        ]);

        // Trial feature disabled: do not set trial or next payment fields

        // Handle uploads if provided
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $user->logo_path = $path;
        }
        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('covers', 'public');
            $user->cover_path = $path;
        }
        if ($user->isDirty()) { $user->save(); }

    $token = $user->createToken('api')->plainTextToken;
    Log::info('Register success', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
                'whatsapp' => $user->whatsapp,
                'website' => $user->website,
                'country' => $user->country,
                'address' => $user->address,
                'currency' => $user->currency,
                'logo_url' => $user->logo_path ? asset('storage/'.$user->logo_path) : null,
                'cover_url' => $user->cover_path ? asset('storage/'.$user->cover_path) : null,
                'trial_started_at' => optional($user->trial_started_at)?->toIso8601String(),
                'trial_ends_at' => optional($user->trial_ends_at)?->toIso8601String(),
                'premium_package' => (bool)$user->premium_package,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        Log::info('Login attempt payload', [
            'payload' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept'),
        ]);
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 422);
        }

        // Trial feature disabled – do not set or calculate trial fields on login

        $token = $user->createToken('api')->plainTextToken;
        Log::info('Login success', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'privilege' => $user->privilege,
                'trial_started_at' => optional($user->trial_started_at)?->toIso8601String(),
                'trial_ends_at' => optional($user->trial_ends_at)?->toIso8601String(),
                'premium_package' => (bool)$user->premium_package,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'privilege' => $user->privilege,
            'phone' => $user->phone,
            'whatsapp' => $user->whatsapp,
            'website' => $user->website,
            'country' => $user->country,
            'address' => $user->address,
            'currency' => $user->currency,
            'logo_url' => $user->logo_path ? asset('storage/'.$user->logo_path) : null,
            'cover_url' => $user->cover_path ? asset('storage/'.$user->cover_path) : null,
            'trial_started_at' => optional($user->trial_started_at)?->toIso8601String(),
            'trial_ends_at' => optional($user->trial_ends_at)?->toIso8601String(),
            'premium_package' => (bool)$user->premium_package,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['status' => 'success']);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'email' => ['sometimes','string','email','max:255', \Illuminate\Validation\Rule::unique('users','email')->ignore($user->id)],
            'password' => ['sometimes','string', \Illuminate\Validation\Rules\Password::min(8)],
            'name' => ['sometimes','string','max:255'],
            'phone' => ['sometimes','nullable','string','max:30'],
            'whatsapp' => ['sometimes','nullable','string','max:30'],
            'website' => ['sometimes','nullable','string','max:255'],
            'country' => ['sometimes','nullable','string','max:100'],
            'address' => ['sometimes','nullable','string'],
            'currency' => ['sometimes','nullable','string','size:3'],
            'logo' => ['sometimes','nullable','image','max:2048'],
            'cover' => ['sometimes','nullable','image','max:4096'],
        ]);

        foreach (['email','name','phone','whatsapp','website','country','address','currency'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('password', $validated)) {
            $user->password = $validated['password'];
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $user->logo_path = $path;
        }
        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('covers', 'public');
            $user->cover_path = $path;
        }

        $user->save();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'privilege' => $user->privilege,
            'phone' => $user->phone,
            'whatsapp' => $user->whatsapp,
            'website' => $user->website,
            'country' => $user->country,
            'address' => $user->address,
            'currency' => $user->currency,
            'logo_url' => $user->logo_path ? asset('storage/'.$user->logo_path) : null,
            'cover_url' => $user->cover_path ? asset('storage/'.$user->cover_path) : null,
            'premium_package' => (bool)$user->premium_package,
        ]);
    }
}
