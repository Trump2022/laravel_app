<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    use SoftDeletes;

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
        $this->loadMissing('roles:id,code');

        $actorIsDeveloper = $this->roles->contains('code', Role::DEVELOPER_CODE);

        if ($actorIsDeveloper) {
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

        if ($this->roles->contains('code', Role::SUPER_ADMIN_CODE)) {
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

    /**
     * @param Request $request
     * @return array
     */
    public static function getValidate(Request $request): array
    {
        return $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                // Rule::unique('admins', 'email')->ignore($admin->getKey()),
            ],
        ]);
    }

    /**
     * email是否存在
     *
     * @param array $validated
     * @param Admin|null $admin
     * @param bool $isEdit | 为true是编辑
     * @return bool
     */
    public static function checkEmailExists(array $validated, ?Admin $admin = null, bool $isEdit = false): bool
    {
        return Admin::query()
            ->where('email', $validated['email'])
            ->when(
                $isEdit,
                fn (Builder $query) => $query->where('id', '!=', $admin->getKey()),
            )
            ->exists();
    }
}
