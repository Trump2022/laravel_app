<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('admin:create-developer')]
#[Description('Create the initial developer admin account')]
class CreateDeveloperAdmin extends Command
{
    protected $signature = 'admin:create-developer';

    protected $description = 'Create the initial developer admin account';

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle(): int
    {
        $input = [
            'name' => $this->ask('Developer name'),
            'email' => $this->ask('Developer email'),
            'password' => $this->secret('Password'),
        ];

        if ($input['password'] !== $this->secret('Confirm password')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $developerRole = Role::query()
            ->where('code', Role::DEVELOPER_CODE)
            ->first();

        if ($developerRole === null) {
            $this->error('Developer role not found. Run the RoleSeeder first.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($input, $developerRole): void {
            $admin = Admin::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $admin->roles()->attach($developerRole->id);
        });

        $this->info('Developer admin created.');

        return self::SUCCESS;
    }
}
