<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('invitedBy')->orderByDesc('is_admin')->orderBy('id')->get();

        return Inertia::render('admin/Users', [
            'users' => UserResource::collection($users)->resolve(),
        ]);
    }
}
