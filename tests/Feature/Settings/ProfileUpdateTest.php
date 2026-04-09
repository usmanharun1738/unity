<?php

namespace Tests\Feature\Settings;

use App\Enums\RoleName;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertFalse(auth()->check());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }

    public function test_student_can_update_student_profile_fields(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'student_number' => 'STU-000001',
        ]);

        $this->actingAs($student);

        Livewire::test('pages::settings.profile')
            ->set('name', 'Student Updated')
            ->set('email', 'student.updated@example.com')
            ->set('major', 'Computer Science')
            ->set('year_level', 3)
            ->set('bio', 'Focused on distributed systems.')
            ->set('avatar_path', '/avatars/student-updated.png')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $student->refresh();
        $this->assertSame('Student Updated', $student->name);
        $this->assertSame('student.updated@example.com', $student->email);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'major' => 'Computer Science',
            'year_level' => 3,
            'avatar_path' => '/avatars/student-updated.png',
        ]);
    }
}
