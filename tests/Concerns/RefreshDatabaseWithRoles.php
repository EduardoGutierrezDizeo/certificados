<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

trait RefreshDatabaseWithRoles
{
    use RefreshDatabase;

    /**
     * Seed the base roles after database migrations run.
     */
    protected function afterRefreshingDatabase(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'abogado']);
    }
}
