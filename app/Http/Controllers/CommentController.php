<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    public function index(Post $post): AnonymousResourceCollection
    {
        $comments = $post->comments()->with('user')->latest()->paginate(15);

        return CommentResource::collection($comments);
    }

    public function store(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $post->increment('comments_count');

        return response()->json(new CommentResource($comment->load('user')), 201);
    }

    public function update(Request $request, Comment $comment): CommentResource
    {
        $this->authorize('update', $comment);

        $data = $request->validate(['body' => 'required|string|max:1000']);
        $comment->update($data);

        return new CommentResource($comment->load('user'));
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->post()->decrement('comments_count');
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }
}
