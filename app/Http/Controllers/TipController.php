<?php

namespace App\Http\Controllers;

use App\Models\Tip;
use App\Models\TipCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class TipController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tips",
     *     summary="Get all tips with filters",
     *     tags={"Tips"},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="featured",
     *         in="query",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         @OA\Schema(type="string", enum={"latest", "popular", "views"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of tips with expert information"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Tip::with([
            'category',
            'user' => function($query) {
                $query->select('id', 'name', 'username', 'email', 'phone_number', 'location', 'image_url', 'role');
            }
        ])->withCount(['likedBy', 'savedBy']);

        // Apply filters
        if ($request->category) {
            $query->where('category_id', $request->category);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        if ($request->featured) {
            $query->where('is_featured', true);
        }

        // Apply sorting
        switch ($request->sort) {
            case 'popular':
                $query->orderBy('likes_count', 'desc');
                break;
            case 'views':
                $query->orderBy('views_count', 'desc');
                break;
            default: // latest
                $query->latest();
        }

        $tips = $query->paginate(10);

        // Add user's like and save status if authenticated
        if (Auth::check()) {
            $tips->getCollection()->transform(function ($tip) {
                $tip->is_liked = $tip->likedBy()->where('user_id', Auth::id())->exists();
                $tip->is_saved = $tip->savedBy()->where('user_id', Auth::id())->exists();
                return $tip;
            });
        }

        return response()->json(['tips' => $tips]);
    }

    /**
     * @OA\Get(
     *     path="/api/tips/{id}",
     *     summary="Get a specific tip",
     *     tags={"Tips"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tip details"
     *     ),
     *     @OA\Response(response=404, description="Tip not found")
     * )
     */
    public function show($id)
    {
        $tip = Tip::with(['category', 'user'])
            ->withCount(['likedBy', 'savedBy'])
            ->findOrFail($id);

        // Increment view count
        $tip->incrementViews();

        // Add user's like and save status if authenticated
        if (Auth::check()) {
            $tip->is_liked = $tip->likedBy()->where('user_id', Auth::id())->exists();
            $tip->is_saved = $tip->savedBy()->where('user_id', Auth::id())->exists();
        }

        return response()->json(['tip' => $tip]);
    }

    /**
     * @OA\Post(
     *     path="/api/tips",
     *     summary="Create a new tip",
     *     tags={"Tips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "content", "category_id"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="is_featured", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tip created successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        if (Auth::user()->role !== 'expert') {
            return response()->json(['message' => 'Only experts can create tips'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:tip_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'is_featured' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['user_id'] = Auth::id();

        $tip = Tip::create($data);

        return response()->json([
            'message' => 'Tip created successfully',
            'tip' => $tip->load(['category', 'user'])
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/tips/{id}",
     *     summary="Update a tip",
     *     tags={"Tips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="is_featured", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tip updated successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Tip not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $tip = Tip::findOrFail($id);

        if ($tip->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'content' => 'string',
            'category_id' => 'exists:tip_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'is_featured' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tip->update($request->all());

        return response()->json([
            'message' => 'Tip updated successfully',
            'tip' => $tip->load(['category', 'user'])
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/tips/{id}",
     *     summary="Delete a tip",
     *     tags={"Tips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tip deleted successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Tip not found")
     * )
     */
    public function destroy($id)
    {
        $tip = Tip::findOrFail($id);

        if ($tip->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tip->delete();

        return response()->json(['message' => 'Tip deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/tips/{id}/like",
     *     summary="Like or unlike a tip",
     *     tags={"Tips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tip like status toggled"
     *     ),
     *     @OA\Response(response=404, description="Tip not found")
     * )
     */
    public function toggleLike($id)
    {
        $tip = Tip::findOrFail($id);
        $isLiked = $tip->toggleLike(Auth::id());

        return response()->json([
            'message' => $isLiked ? 'Tip liked' : 'Tip unliked',
            'likes_count' => $tip->likes_count
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tips/{id}/save",
     *     summary="Save or unsave a tip",
     *     tags={"Tips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tip save status toggled"
     *     ),
     *     @OA\Response(response=404, description="Tip not found")
     * )
     */
    public function toggleSave($id)
    {
        $tip = Tip::findOrFail($id);
        $isSaved = $tip->toggleSave(Auth::id());

        return response()->json([
            'message' => $isSaved ? 'Tip saved' : 'Tip unsaved'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/tips/saved",
     *     summary="Get user's saved tips",
     *     tags={"Tips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of saved tips"
     *     )
     * )
     */
    public function savedTips()
    {
        $tips = Auth::user()->savedTips()
            ->with(['category', 'user'])
            ->withCount(['likedBy', 'savedBy'])
            ->latest()
            ->paginate(10);

        return response()->json(['tips' => $tips]);
    }

    /**
     * @OA\Get(
     *     path="/api/tips/my-tips",
     *     summary="Get expert's tips",
     *     tags={"Tips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of expert's tips"
     *     )
     * )
     */
    public function myTips()
    {
        if (Auth::user()->role !== 'expert') {
            return response()->json(['message' => 'Only experts can access their tips'], 403);
        }

        $tips = Tip::where('user_id', Auth::id())
            ->with(['category'])
            ->withCount(['likedBy', 'savedBy'])
            ->latest()
            ->paginate(10);

        return response()->json(['tips' => $tips]);
    }

    /**
     * @OA\Get(
     *     path="/api/tips/featured",
     *     summary="Get all featured tips",
     *     tags={"Tips"},
     *     @OA\Response(
     *         response=200,
     *         description="List of featured tips"
     *     )
     * )
     */
    public function featured()
    {
        $tips = Tip::with(['category', 'user'])
            ->withCount(['likedBy', 'savedBy'])
            ->where('is_featured', true)
            ->latest()
            ->paginate(10);

        return response()->json(['tips' => $tips]);
    }
}
