<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_all_platform_roles(): void
    {
        $this->seed(RoleSeeder::class);

        foreach (RoleName::values() as $roleName) {
            $this->assertDatabaseHas((new Role)->getTable(), [
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }
    }
}
