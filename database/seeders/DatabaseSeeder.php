<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Site;
use App\Models\Database;
use App\Models\RedisInstance;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create demo users
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'password' => Hash::make('demo123'),
            'role' => 'admin',
        ]);

        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@demo.com',
            'password' => Hash::make('user123'),
            'role' => 'user',
        ]);

        // Create demo sites for admin
        $site1 = Site::create([
            'user_id' => $admin->id,
            'name' => 'demo-site-1',
            'domain' => 'demo-site-1.localhost',
            'domain_type' => 'subdomain',
            'port' => 8081,
            'status' => 'running',
            'container_name' => 'demo-site-1',
            'ssl_enabled' => false,
            'protocol' => 'http',
            'mysql_port' => 9081,
            'redis_port' => 10081,
            'stats' => [
                'cpu' => '0.5%',
                'memory' => '45MiB / 512MiB',
                'memory_usage' => ['used' => 45, 'total' => 512, 'percentage' => 8.8],
                'net_io' => '1.2KB / 0.5KB',
                'block_io' => '0B / 0B',
                'pids' => '8'
            ]
        ]);

        Database::create([
            'site_id' => $site1->id,
            'name' => 'demo_site_1_db',
            'status' => 'connected',
            'container_name' => 'demo_site_1_db',
            'port' => 9081,
            'database_name' => 'wordpress',
            'username' => 'wpuser',
            'password' => Hash::make('password'),
        ]);

        RedisInstance::create([
            'site_id' => $site1->id,
            'name' => 'demo_site_1_redis',
            'status' => 'cached',
            'container_name' => 'demo_site_1_redis',
            'port' => 10081,
        ]);

        // Create second site for admin
        $site2 = Site::create([
            'user_id' => $admin->id,
            'name' => 'test-blog',
            'domain' => 'test-blog.localhost',
            'domain_type' => 'subdomain',
            'port' => 8082,
            'status' => 'running',
            'container_name' => 'test-blog',
            'ssl_enabled' => true,
            'protocol' => 'https',
            'mysql_port' => 9082,
            'redis_port' => 10082,
            'stats' => [
                'cpu' => '0.3%',
                'memory' => '32MiB / 512MiB',
                'memory_usage' => ['used' => 32, 'total' => 512, 'percentage' => 6.2],
                'net_io' => '0.8KB / 0.2KB',
                'block_io' => '0B / 0B',
                'pids' => '6'
            ]
        ]);

        Database::create([
            'site_id' => $site2->id,
            'name' => 'test_blog_db',
            'status' => 'connected',
            'container_name' => 'test_blog_db',
            'port' => 9082,
            'database_name' => 'wordpress',
            'username' => 'wpuser',
            'password' => Hash::make('password'),
        ]);

        RedisInstance::create([
            'site_id' => $site2->id,
            'name' => 'test_blog_redis',
            'status' => 'cached',
            'container_name' => 'test_blog_redis',
            'port' => 10082,
        ]);

        // Create a site for regular user
        $site3 = Site::create([
            'user_id' => $user->id,
            'name' => 'my-site',
            'domain' => 'my-site.localhost',
            'domain_type' => 'subdomain',
            'port' => 8083,
            'status' => 'stopped',
            'container_name' => 'my-site',
            'ssl_enabled' => false,
            'protocol' => 'http',
            'mysql_port' => 9083,
            'redis_port' => 10083,
        ]);

        Database::create([
            'site_id' => $site3->id,
            'name' => 'my_site_db',
            'status' => 'disconnected',
            'container_name' => 'my_site_db',
            'port' => 9083,
            'database_name' => 'wordpress',
            'username' => 'wpuser',
            'password' => Hash::make('password'),
        ]);
    }
}