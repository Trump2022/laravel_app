<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Builder;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_role', 'admin_id', 'role_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()
            ->where('code', Role::SUPER_ADMIN_CODE)
            ->exists();
    }

    public function isDeveloper(): bool
    {
        return $this->roles()
            ->where('code', Role::DEVELOPER_CODE)
            ->exists();
    }

    public function hasApiPermission(string $routeName, string $method): bool
    {
        if ($this->isDeveloper()) {
            return true;
        }

        $permissionCode = $routeName.':'.strtolower($method);

        $permission = Permission::query()
            ->where('permission_code', $permissionCode)
            ->where('type', 3)
            ->first();

        if ($permission === null || $permission->developer_only) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ((int) $permission->status !== 1) {
            return false;
        }

        if (! $permission->authorization_required) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function (Builder $query) use ($permission): void {
                $query->whereKey($permission->getKey());
            })
            ->exists();
    }
}
