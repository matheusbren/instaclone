<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // static before dynamic to avoid {username} swallowing these
    Route::put('/users/me', [UserController::class, 'updateMe']);
    Route::post('/users/me/avatar', [UserController::class, 'updateAvatar']);
    Route::get('/users/search', [UserController::class, 'search']);
    Route::get('/users/suggestions', [UserController::class, 'suggestions']);
    Route::get('/users/{username}', [UserController::class, 'show'])->where('username', '[a-zA-Z0-9_]+');
    Route::put('/users/{user}', [UserController::class, 'update']);

    Route::get('/users/{user}/posts', [PostController::class, 'userPosts']);
    Route::post('/users/{user}/follow', [FollowController::class, 'follow']);
    Route::delete('/users/{user}/follow', [FollowController::class, 'unfollow']);
    Route::delete('/users/{user}/unfollow', [FollowController::class, 'unfollow']);
    Route::get('/users/{user}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{user}/following', [FollowController::class, 'following']);
    Route::get('/users/{user}/is-following', [FollowController::class, 'isFollowing']);

    Route::get('/feed', [FeedController::class, 'index']);

    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/posts/{post}', [PostController::class, 'show']);
    Route::put('/posts/{post}', [PostController::class, 'update']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    Route::post('/posts/{post}/like', [LikeController::class, 'like']);
    Route::delete('/posts/{post}/like', [LikeController::class, 'unlike']);
    Route::delete('/posts/{post}/unlike', [LikeController::class, 'unlike']);
    Route::get('/posts/{post}/likers', [LikeController::class, 'likers']);

    Route::get('/posts/{post}/comments', [CommentController::class, 'index']);
    Route::post('/posts/{post}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
});
