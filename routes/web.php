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

Route::get('/create-site', function (Request $request) {

    function runCommand($cmd, $wait = true) {
        $output = shell_exec($cmd . ' 2>&1');
        if ($wait) sleep(1);
        return $output;
    }

    $siteName = $request->input('name', 'testsite');

    $wordpressPort = 8081;
    $mysqlPort = 3307;
    $redisPort = 6380;

    $dbName = 'wordpress_' . $siteName;
    $dbUser = 'wp_' . $siteName;
    $dbPassword = 'password';
    $rootPassword = 'root';

    $timestamp = time();

    $networkName = 'wpnetwork_' . $siteName;
    $mysqlContainer = $siteName . '_db_' . $timestamp;
    $wpContainer = $siteName . '_wp_' . $timestamp;
    $redisContainer = $siteName . '_redis_' . $timestamp;

    // -------- CREATE NETWORK --------
    $check = trim(runCommand("docker network ls --filter name={$networkName} --format '{{.Name}}'", false));

    if (empty($check)) {
        runCommand("docker network create {$networkName}");
    }

    // -------- CREATE MYSQL --------
    runCommand("docker run -d --name {$mysqlContainer} --network {$networkName} -p {$mysqlPort}:3306 -e MYSQL_ROOT_PASSWORD={$rootPassword} -e MYSQL_DATABASE={$dbName} -e MYSQL_USER={$dbUser} -e MYSQL_PASSWORD={$dbPassword} --restart unless-stopped mysql:8.0");

    // -------- WAIT MYSQL READY --------
    $maxAttempts = 30;

    for ($i = 1; $i <= $maxAttempts; $i++) {

        $res = shell_exec("docker exec {$mysqlContainer} mysqladmin ping -h localhost -u{$dbUser} -p{$dbPassword} 2>&1");

        if (strpos($res, 'mysqld is alive') !== false) {
            break;
        }

        sleep(2);
    }

    // -------- CREATE WORDPRESS --------
    runCommand("docker run -d --name {$wpContainer} --network {$networkName} -p {$wordpressPort}:80 -e WORDPRESS_DB_HOST={$mysqlContainer}:3306 -e WORDPRESS_DB_USER={$dbUser} -e WORDPRESS_DB_PASSWORD={$dbPassword} -e WORDPRESS_DB_NAME={$dbName} --restart unless-stopped wordpress:latest");

    // -------- CREATE REDIS --------
    runCommand("docker run -d --name {$redisContainer} --network {$networkName} -p {$redisPort}:6379 --restart unless-stopped redis:alpine redis-server --appendonly yes");

    return response()->json([
        'success' => true,
        'site' => $siteName,
        'wordpress_url' => "http://localhost:$wordpressPort",
        'containers' => [
            'wordpress' => $wpContainer,
            'mysql' => $mysqlContainer,
            'redis' => $redisContainer
        ]
    ]);

});