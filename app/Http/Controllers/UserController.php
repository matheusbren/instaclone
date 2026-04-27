<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function show(string $username): UserResource
    {
        $user = User::where('username', $username)
            ->withCount(['followers', 'following', 'posts'])
            ->firstOrFail();

        return new UserResource($user);
    }

    public function updateMe(Request $request): UserResource
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => ['sometimes', 'string', 'max:30', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'bio' => 'nullable|string|max:500',
        ]);

        $user->update($data);
        $user->loadCount(['followers', 'following', 'posts']);

        return new UserResource($user);
    }

    public function updateAvatar(Request $request): UserResource
    {
        $user = $request->user();

        $request->validate(['avatar' => 'required|image|max:2048']);

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => $request->file('avatar')->store('avatars', 'public')]);
        $user->loadCount(['followers', 'following', 'posts']);

        return new UserResource($user);
    }

    public function update(Request $request, User $user): UserResource
    {
        abort_if($request->user()->id !== $user->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($data['avatar']);
        }

        $user->update($data);
        $user->loadCount(['followers', 'following', 'posts']);

        return new UserResource($user);
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $q = $request->validate(['q' => 'required|string|min:1|max:100'])['q'];

        $users = User::where('name', 'like', "%{$q}%")
            ->orWhere('username', 'like', "%{$q}%")
            ->withCount(['followers', 'following', 'posts'])
            ->limit(20)
            ->get();

        return UserResource::collection($users);
    }

    public function suggestions(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $excludeIds = $user->following()->pluck('users.id')->push($user->id);

        $users = User::whereNotIn('id', $excludeIds)
            ->withCount(['followers', 'following', 'posts'])
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return UserResource::collection($users);
    }
}
