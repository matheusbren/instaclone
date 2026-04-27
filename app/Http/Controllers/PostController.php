<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => 'required|image|max:10240',
            'caption' => 'nullable|string|max:2200',
        ]);

        $path = $request->file('image')->store('posts', 'public');

        $post = $request->user()->posts()->create([
            'image_path' => $path,
            'caption' => $data['caption'] ?? null,
        ]);

        return response()->json(new PostResource($post->load('user')), 201);
    }

    public function show(Post $post): PostResource
    {
        return new PostResource($post->load('user'));
    }

    public function update(Request $request, Post $post): PostResource
    {
        $this->authorize('update', $post);

        $data = $request->validate([
            'caption' => 'nullable|string|max:2200',
        ]);

        $post->update($data);

        return new PostResource($post->load('user'));
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        Storage::disk('public')->delete($post->image_path);
        $post->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    public function userPosts(User $user): AnonymousResourceCollection
    {
        $posts = $user->posts()->with('user')->latest()->paginate(12);

        return PostResource::collection($posts);
    }
}
