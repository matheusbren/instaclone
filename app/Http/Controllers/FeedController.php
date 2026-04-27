<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(private readonly FeedService $feedService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->feedService->getFeed($request->user());

        return response()->json([
            'items' => PostResource::collection($paginator->items()),
            'next_cursor' => $paginator->nextCursor()?->encode(),
        ]);
    }
}
