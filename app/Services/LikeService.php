<?php

namespace App\Services;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;

class LikeService
{
    public function like(User $user, Post $post): int
    {
        $like = Like::firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        if ($like->wasRecentlyCreated) {
            $post->increment('likes_count');
        }

        return $post->fresh()->likes_count;
    }

    public function unlike(User $user, Post $post): int
    {
        $deleted = Like::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();

        if ($deleted > 0) {
            $post->decrement('likes_count');
        }

        return $post->fresh()->likes_count;
    }
}
