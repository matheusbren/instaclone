<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;

class FeedService
{
    public function getFeed(User $user, int $perPage = 15): CursorPaginator
    {
        $followingIds = $user->following()->pluck('users.id');

        return Post::whereIn('user_id', $followingIds)
            ->with('user')
            ->withExists(['likes as liked_by_me' => fn($q) => $q->where('user_id', $user->id)])
            ->latest()
            ->cursorPaginate($perPage);
    }
}
