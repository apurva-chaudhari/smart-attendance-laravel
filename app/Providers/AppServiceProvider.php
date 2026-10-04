<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // /teachers/{teacher} only ever resolves subject teachers.
        // Passing the class teacher's id (or a missing id) gives a 404,
        // so the class teacher can never be edited/deleted from that screen.
        Route::bind('teacher', function (string $value) {
            return User::where('role', User::ROLE_SUBJECT_TEACHER)->findOrFail($value);
        });
    }
}
