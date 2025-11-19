<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $auth */
        $auth = $request->user();

        if (!in_array(strtolower($auth->role), ['photographer','business'])) {
            return response()->json(['message' => 'Only photographer or business can manage users'], 403);
        }

        $users = User::query()
            ->where('id', $auth->id)
            ->orWhere('parent_user_id', $auth->id)
            ->orderBy('id')
            ->get(['id','name','email','role','privilege','parent_user_id','currency']);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $auth */
        $auth = $request->user();

        if (!in_array(strtolower($auth->role), ['photographer','business'])) {
            return response()->json(['message' => 'Only photographer or business can register users'], 403);
        }

        $validated = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','string','email','max:255','unique:users,email'],
            'password' => ['required', Password::min(8)],
            'privilege' => ['required','string', Rule::in(['officer','cashier'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // hashed by cast
            'role' => $auth->role,
            'currency' => $auth->currency,
            'privilege' => $validated['privilege'] ?? null,
            'parent_user_id' => $auth->id,
        ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'privilege' => $user->privilege,
            'parent_user_id' => $user->parent_user_id,
            'currency' => $user->currency,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        /** @var User $auth */
        $auth = $request->user();

        if (!in_array(strtolower($auth->role), ['photographer','business'])) {
            return response()->json(['message' => 'Only photographer or business can manage users'], 403);
        }

        if (!($user->id === $auth->id || $user->parent_user_id === $auth->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'email' => ['sometimes','string','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['sometimes','string', Password::min(8)],
            'privilege' => ['sometimes','string', Rule::in(['officer','cashier'])],
            // role updates are ignored to enforce inheritance
        ]);

        foreach (['name','email'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
            }
        }
        if (array_key_exists('password', $validated)) {
            $user->password = $validated['password'];
        }
        if (array_key_exists('privilege', $validated)) {
            $user->privilege = $validated['privilege'];
        }

        // Enforce role alignment to parent (do not allow arbitrary changes)
        if ($user->parent_user_id === $auth->id) {
            $user->role = $auth->role;
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'privilege' => $user->privilege,
            'parent_user_id' => $user->parent_user_id,
            'currency' => $user->currency,
        ]);
    }
}
