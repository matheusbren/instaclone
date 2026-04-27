<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caption' => $this->caption,
            'image_url' => Storage::url($this->image_path),
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'liked_by_me' => array_key_exists('liked_by_me', $this->resource->getAttributes())
                ? (bool) $this->resource->getAttributes()['liked_by_me']
                : ($request->user() ? $this->likes()->where('user_id', $request->user()->id)->exists() : false),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
