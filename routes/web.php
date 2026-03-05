<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
Route::get('/', function () {
    return 'ok';
});
Route::get('/all', function () {
    return 'ok';
});
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
});

Route::get('/sites', function () {
    return Inertia::render('Sites');
});
Route::get('/create', function () {
    return Inertia::render('CreateSite'); // Match the filename in /Pages
});



// More flexible route with parameters (POST request)


Route::get('/create-user/{secret}/{name}/{email}/{password}', function ($secret, $name, $email, $password) {
    // Check if secret code matches
    if ($secret !== 'ml000') {
        return response()->json([
            'success' => false,
            'message' => 'Invalid secret code'
        ], 403);
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid email format'
        ], 400);
    }

    // Check if email already exists
    if (User::where('email', $email)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Email already exists'
        ], 400);
    }

    // Check password length
    if (strlen($password) < 8) {
        return response()->json([
            'success' => false,
            'message' => 'Password must be at least 8 characters'
        ], 400);
    }

    try {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'user'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at->toISOString()
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error creating user: ' . $e->getMessage()
        ], 500);
    }
});

// Optional: Add role as optional parameter
Route::get('/create-user/{secret}/{name}/{email}/{password}/{role?}', function ($secret, $name, $email, $password, $role = 'user') {
    // Check if secret code matches
    if ($secret !== 'ml000') {
        return response()->json([
            'success' => false,
            'message' => 'Invalid secret code'
        ], 403);
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid email format'
        ], 400);
    }

    // Check if email already exists
    if (User::where('email', $email)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Email already exists'
        ], 400);
    }

    // Check password length
    if (strlen($password) < 8) {
        return response()->json([
            'success' => false,
            'message' => 'Password must be at least 8 characters'
        ], 400);
    }

    // Validate role
    if (!in_array($role, ['user', 'admin'])) {
        return response()->json([
            'success' => false,
            'message' => 'Role must be either user or admin'
        ], 400);
    }

    try {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at->toISOString()
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error creating user: ' . $e->getMessage()
        ], 500);
    }
});