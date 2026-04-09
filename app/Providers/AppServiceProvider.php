<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseModule;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Policies\CourseMaterialPolicy;
use App\Policies\CourseModulePolicy;
use App\Policies\CoursePolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\FacultyProfilePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(CourseModule::class, CourseModulePolicy::class);
        Gate::policy(CourseMaterial::class, CourseMaterialPolicy::class);
        Gate::policy(FacultyProfile::class, FacultyProfilePolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
                : null,
        );
    }
}
