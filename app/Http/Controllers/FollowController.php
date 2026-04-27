<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FollowController extends Controller
{
    public function __construct(private readonly FollowService $followService) {}

    public function follow(Request $request, User $user): JsonResponse
    {
        $this->followService->follow($request->user(), $user);

        return response()->json(['message' => 'Followed.']);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $this->followService->unfollow($request->user(), $user);

        return response()->json(['message' => 'Unfollowed.']);
    }

    public function followers(User $user): AnonymousResourceCollection
    {
        $followers = $user->followers()
            ->withCount(['followers', 'following', 'posts'])
            ->paginate(20);

        return UserResource::collection($followers);
    }

    public function following(User $user): AnonymousResourceCollection
    {
        $following = $user->following()
            ->withCount(['followers', 'following', 'posts'])
            ->paginate(20);

        return UserResource::collection($following);
    }

    public function isFollowing(Request $request, User $user): JsonResponse
    {
        return response()->json([
            'is_following' => $request->user()->isFollowing($user),
        ]);
    }
}
