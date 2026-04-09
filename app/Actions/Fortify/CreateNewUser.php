<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\RoleName;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        Role::findOrCreate(RoleName::Student->value, 'web');
        $user->assignRole(RoleName::Student->value);

        StudentProfile::query()->create([
            'user_id' => $user->id,
            'student_number' => 'STU-'.strtoupper(substr(sha1((string) $user->id.$user->email), 0, 8)),
            'major' => null,
            'year_level' => null,
            'bio' => null,
            'avatar_path' => null,
        ]);

        return $user;
    }
}
