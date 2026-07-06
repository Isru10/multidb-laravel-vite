<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    /**
     * Get all posts in this tenant
     * Anyone in the clinic can view posts
     */
    public function index()
    {
        try {
            $posts = Post::with('user:id,name,email')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($posts);
        } catch (\Exception $e) {
            Log::error('Failed to fetch posts', [
                'error' => $e->getMessage(),
                'tenant' => tenant('id'),
            ]);

            return response()->json([
                'error' => 'Failed to fetch posts',
            ], 500);
        }
    }

    /**
     * Create a new post
     * Only 'admin' role can create posts (protected in routes)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
            ]);

            $user = auth()->guard('sanctum')->user();

            // Get the current user - note: we need to fetch them from tenant context
            // Since auth() returns the central user, we store their ID
            $post = Post::create([
                'user_id' => $user->id,
                'title' => $validated['title'],
                'description' => $validated['description'],
            ]);

            Log::info('New post created', [
                'post_id' => $post->id,
                'tenant' => tenant('id'),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Post created successfully!',
                'post' => $post->load('user:id,name,email'),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create post', [
                'error' => $e->getMessage(),
                'tenant' => tenant('id'),
            ]);

            return response()->json([
                'error' => 'Failed to create post: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific post
     */
    public function show(Post $post)
    {
        try {
            return response()->json($post->load('user:id,name,email'));
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Post not found',
            ], 404);
        }
    }

    /**
     * Update a post (only admin or post author)
     */
    public function update(Request $request, Post $post)
    {
        try {
            $user = auth()->guard('sanctum')->user();

            // Check if user is admin or post author
            if ($user->id !== $post->user_id && !$user->hasRole('admin')) {
                return response()->json([
                    'error' => 'Unauthorized. Only post author or admin can update.',
                ], 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
            ]);

            $post->update($validated);

            Log::info('Post updated', [
                'post_id' => $post->id,
                'tenant' => tenant('id'),
                'updated_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Post updated successfully!',
                'post' => $post->load('user:id,name,email'),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update post', [
                'error' => $e->getMessage(),
                'tenant' => tenant('id'),
            ]);

            return response()->json([
                'error' => 'Failed to update post',
            ], 500);
        }
    }

    /**
     * Delete a post (only admin or post author)
     */
    public function destroy(Post $post)
    {
        try {
            $user = auth()->guard('sanctum')->user();

            // Check if user is admin or post author
            if ($user->id !== $post->user_id && !$user->hasRole('admin')) {
                return response()->json([
                    'error' => 'Unauthorized. Only post author or admin can delete.',
                ], 403);
            }

            $post->delete();

            Log::info('Post deleted', [
                'post_id' => $post->id,
                'tenant' => tenant('id'),
                'deleted_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Post deleted successfully!',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete post', [
                'error' => $e->getMessage(),
                'tenant' => tenant('id'),
            ]);

            return response()->json([
                'error' => 'Failed to delete post',
            ], 500);
        }
    }
}
