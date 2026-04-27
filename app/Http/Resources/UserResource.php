<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->when($request->user()?->id === $this->id, $this->email),
            'bio' => $this->bio,
            'avatar_url' => $this->avatar ? Storage::url($this->avatar) : null,
            'followers_count' => $this->followers_count ?? $this->followers()->count(),
            'following_count' => $this->following_count ?? $this->following()->count(),
            'posts_count' => $this->posts_count ?? $this->posts()->count(),
            'is_following' => $request->user()?->isFollowing($this->resource),
            'created_at' => $this->created_at,
        ];
    }
}
