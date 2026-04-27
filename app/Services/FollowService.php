<?php

namespace App\Services;

use App\Exceptions\SelfFollowException;
use App\Models\Follow;
use App\Models\User;

class FollowService
{
    public function follow(User $follower, User $userToFollow): void
    {
        if ($follower->id === $userToFollow->id) {
            throw new SelfFollowException();
        }

        Follow::firstOrCreate([
            'follower_id' => $follower->id,
            'following_id' => $userToFollow->id,
        ]);
    }

    public function unfollow(User $follower, User $userToUnfollow): void
    {
        Follow::where('follower_id', $follower->id)
            ->where('following_id', $userToUnfollow->id)
            ->delete();
    }
}
