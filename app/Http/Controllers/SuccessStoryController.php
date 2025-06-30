<?php

namespace App\Http\Controllers;

use App\Models\SuccessStory;
use App\Models\StoryImage;
use App\Models\StoryComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SuccessStoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/success-stories",
     *     summary="Get all success stories with filters",
     *     tags={"Success Stories"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="crop_type",
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
     *         description="List of success stories"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = SuccessStory::with([
            'user' => function($query) {
                $query->select('id', 'name', 'username', 'email', 'phone_number', 'location', 'image_url', 'role');
            },
            'images'
        ])->withCount(['likes', 'allComments']);

        // Apply filters
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('crop_type', 'like', "%{$search}%");
            });
        }

        if ($request->crop_type) {
            $query->where('crop_type', $request->crop_type);
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

        $stories = $query->paginate(10);

        // Add user's like status if authenticated
        if (Auth::check()) {
            $stories->getCollection()->transform(function ($story) {
                $story->is_liked = $story->likedBy()->where('user_id', Auth::id())->exists();
                return $story;
            });
        }

        return response()->json(['stories' => $stories]);
    }

    /**
     * @OA\Get(
     *     path="/api/success-stories/{id}",
     *     summary="Get a specific success story",
     *     tags={"Success Stories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success story details"
     *     ),
     *     @OA\Response(response=404, description="Story not found")
     * )
     */
    public function show($id)
    {
        $story = SuccessStory::with([
            'user' => function($query) {
                $query->select('id', 'name', 'username', 'email', 'phone_number', 'location', 'image_url', 'role');
            },
            'images',
            'comments'
        ])->withCount(['likes', 'allComments'])
        ->findOrFail($id);

        // Increment view count
        $story->incrementViews();

        // Add user's like status if authenticated
        if (Auth::check()) {
            $story->is_liked = $story->likedBy()->where('user_id', Auth::id())->exists();
        }

        return response()->json(['story' => $story]);
    }

    /**
     * @OA\Post(
     *     path="/api/success-stories",
     *     summary="Create a new success story",
     *     tags={"Success Stories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "content"},
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="location", type="string"),
     *                 @OA\Property(property="crop_type", type="string"),
     *                 @OA\Property(property="yield_improvement", type="number"),
     *                 @OA\Property(property="yield_unit", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="captions", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Success story created successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'location' => 'nullable|string|max:255',
            'crop_type' => 'nullable|string|max:255',
            'yield_improvement' => 'nullable|numeric|min:0',
            'yield_unit' => 'nullable|string|max:50',
            'images.*' => 'nullable|image|max:5120', // 5MB max per image
            'captions.*' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $story = SuccessStory::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'content' => $request->content,
            'location' => $request->location,
            'crop_type' => $request->crop_type,
            'yield_improvement' => $request->yield_improvement,
            'yield_unit' => $request->yield_unit
        ]);

        $uploadedImages = [];

        // Handle image uploads
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            \Log::info('Images found: ' . (is_array($files) ? count($files) : 1));
            
            $uploadPath = public_path('success_stories');
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadPath)) {
                if (!mkdir($uploadPath, 0755, true)) {
                    \Log::error('Failed to create directory: ' . $uploadPath);
                    return response()->json(['error' => 'Failed to create upload directory'], 500);
                }
            }

            // Handle both single file and multiple files
            if (is_array($files)) {
                foreach ($files as $index => $image) {
                    try {
                        \Log::info('Processing image ' . $index . ': ' . $image->getClientOriginalName());
                        
                        // Generate unique filename
                        $imageName = uniqid() . '_' . time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                        $fullPath = $uploadPath . '/' . $imageName;
                        
                        \Log::info('Moving image to: ' . $fullPath);
                        
                        // Move the uploaded file
                        if ($image->move($uploadPath, $imageName)) {
                            \Log::info('Image moved successfully');
                            
                            // Create database record
                            $storyImage = StoryImage::create([
                                'success_story_id' => $story->id,
                                'image_path' => 'success_stories/' . $imageName,
                                'caption' => $request->captions[$index] ?? null,
                                'order' => $index
                            ]);
                            
                            \Log::info('Database record created: ' . $storyImage->id);
                            $uploadedImages[] = $storyImage;
                        } else {
                            \Log::error('Failed to move uploaded image: ' . $imageName);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error uploading image: ' . $e->getMessage());
                        \Log::error('Stack trace: ' . $e->getTraceAsString());
                    }
                }
            } else {
                // Single file
                try {
                    \Log::info('Processing single image: ' . $files->getClientOriginalName());
                    
                    // Generate unique filename
                    $imageName = uniqid() . '_' . time() . '_0.' . $files->getClientOriginalExtension();
                    $fullPath = $uploadPath . '/' . $imageName;
                    
                    \Log::info('Moving image to: ' . $fullPath);
                    
                    // Move the uploaded file
                    if ($files->move($uploadPath, $imageName)) {
                        \Log::info('Image moved successfully');
                        
                        // Create database record
                        $storyImage = StoryImage::create([
                            'success_story_id' => $story->id,
                            'image_path' => 'success_stories/' . $imageName,
                            'caption' => $request->captions[0] ?? null,
                            'order' => 0
                        ]);
                        
                        \Log::info('Database record created: ' . $storyImage->id);
                        $uploadedImages[] = $storyImage;
                    } else {
                        \Log::error('Failed to move uploaded image: ' . $imageName);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error uploading image: ' . $e->getMessage());
                    \Log::error('Stack trace: ' . $e->getTraceAsString());
                }
            }
        } else {
            \Log::info('No images found in request');
        }

        // Reload the story with relationships
        $story->load(['user', 'images']);

        \Log::info('Story loaded with ' . $story->images->count() . ' images');

        return response()->json([
            'message' => 'Success story created successfully',
            'story' => $story,
            'debug' => [
                'uploaded_images_count' => count($uploadedImages),
                'story_images_count' => $story->images->count(),
                'has_images_in_request' => $request->hasFile('images')
            ]
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/success-stories/{id}",
     *     summary="Update a success story",
     *     tags={"Success Stories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="location", type="string"),
     *                 @OA\Property(property="crop_type", type="string"),
     *                 @OA\Property(property="yield_improvement", type="number"),
     *                 @OA\Property(property="yield_unit", type="string"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="captions", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success story updated successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Story not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $story = SuccessStory::findOrFail($id);

        if ($story->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'content' => 'string',
            'location' => 'nullable|string|max:255',
            'crop_type' => 'nullable|string|max:255',
            'yield_improvement' => 'nullable|numeric|min:0',
            'yield_unit' => 'nullable|string|max:50',
            'images.*' => 'nullable|image|max:5120',
            'captions.*' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $story->update($request->except(['images', 'captions']));

        // Handle new image uploads
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            $uploadPath = public_path('success_stories');
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Handle both single file and multiple files
            if (is_array($files)) {
                foreach ($files as $index => $image) {
                    // Generate unique filename
                    $imageName = uniqid() . '_' . time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                    
                    // Move the uploaded file
                    $image->move($uploadPath, $imageName);

                    // Create database record
                    StoryImage::create([
                        'success_story_id' => $story->id,
                        'image_path' => 'success_stories/' . $imageName,
                        'caption' => $request->captions[$index] ?? null,
                        'order' => $story->images()->count() + $index
                    ]);
                }
            } else {
                // Single file
                // Generate unique filename
                $imageName = uniqid() . '_' . time() . '_0.' . $files->getClientOriginalExtension();
                
                // Move the uploaded file
                $files->move($uploadPath, $imageName);

                // Create database record
                StoryImage::create([
                    'success_story_id' => $story->id,
                    'image_path' => 'success_stories/' . $imageName,
                    'caption' => $request->captions[0] ?? null,
                    'order' => $story->images()->count()
                ]);
            }
        }

        // Reload the story with relationships
        $story->load(['user', 'images']);

        return response()->json([
            'message' => 'Success story updated successfully',
            'story' => $story
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/success-stories/{id}",
     *     summary="Delete a success story",
     *     tags={"Success Stories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success story deleted successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Story not found")
     * )
     */
    public function destroy($id)
    {
        $story = SuccessStory::findOrFail($id);

        if ($story->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete images from storage
        foreach ($story->images as $image) {
            $imagePath = public_path($image->image_path);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $story->delete();

        return response()->json(['message' => 'Success story deleted successfully']);
    }

    /**
     * @OA\Post(
     *     path="/api/success-stories/{id}/like",
     *     summary="Like or unlike a success story",
     *     tags={"Success Stories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Story like status toggled"
     *     ),
     *     @OA\Response(response=404, description="Story not found")
     * )
     */
    public function toggleLike($id)
    {
        $story = SuccessStory::findOrFail($id);
        $isLiked = $story->toggleLike(Auth::id());

        return response()->json([
            'message' => $isLiked ? 'Story liked' : 'Story unliked',
            'likes_count' => $story->likes_count
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/success-stories/{id}/comments",
     *     summary="Get comments for a success story",
     *     tags={"Success Stories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of comments"
     *     )
     * )
     */
    public function getComments($id)
    {
        $story = SuccessStory::findOrFail($id);
        $comments = $story->comments()->with('user')->paginate(10);

        return response()->json(['comments' => $comments]);
    }

    /**
     * @OA\Post(
     *     path="/api/success-stories/{id}/comments",
     *     summary="Add a comment to a success story",
     *     tags={"Success Stories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"comment"},
     *             @OA\Property(property="comment", type="string"),
     *             @OA\Property(property="parent_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Comment added successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function addComment(Request $request, $id)
    {
        $story = SuccessStory::findOrFail($id);

        // Debug: Log the request data
        \Log::info('Comment request data:', $request->all());
        \Log::info('Request content type: ' . $request->header('Content-Type'));
        \Log::info('Request body: ' . $request->getContent());
        \Log::info('Request method: ' . $request->method());
        \Log::info('Has comment field: ' . ($request->has('comment') ? 'yes' : 'no'));
        \Log::info('Comment value: ' . $request->input('comment'));

        // Check if JSON is valid
        $rawBody = $request->getContent();
        $jsonData = json_decode($rawBody, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('JSON parsing error: ' . json_last_error_msg());
            return response()->json([
                'error' => 'Invalid JSON format',
                'json_error' => json_last_error_msg(),
                'received_body' => $rawBody
            ], 400);
        }

        // Use the parsed JSON data if request data is empty
        $data = $request->all();
        if (empty($data) && !empty($jsonData)) {
            $data = $jsonData;
            \Log::info('Using parsed JSON data:', $data);
        }

        $validator = Validator::make($data, [
            'comment' => 'required|string',
            'parent_id' => 'nullable|exists:story_comments,id'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment = StoryComment::create([
            'success_story_id' => $story->id,
            'user_id' => Auth::id(),
            'comment' => $data['comment'],
            'parent_id' => $data['parent_id'] ?? null
        ]);

        $story->updateCommentsCount();

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment->load('user')
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/success-stories/my-stories",
     *     summary="Get user's success stories",
     *     tags={"Success Stories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of user's stories"
     *     )
     * )
     */
    public function myStories()
    {
        $stories = SuccessStory::where('user_id', Auth::id())
            ->with(['images'])
            ->withCount(['likes', 'allComments'])
            ->latest()
            ->paginate(10);

        return response()->json(['stories' => $stories]);
    }

    /**
     * @OA\Post(
     *     path="/api/success-stories/test-upload",
     *     summary="Test image upload",
     *     tags={"Success Stories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Upload test result"
     *     )
     * )
     */
    public function testUpload(Request $request)
    {
        $files = $request->file('images');
        
        $result = [
            'has_files' => $request->hasFile('images'),
            'files_count' => 0,
            'all_request_data' => $request->all(),
            'files_info' => []
        ];

        if ($request->hasFile('images')) {
            // Handle both single file and multiple files
            if (is_array($files)) {
                $result['files_count'] = count($files);
                foreach ($files as $index => $file) {
                    $result['files_info'][] = [
                        'index' => $index,
                        'original_name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'extension' => $file->getClientOriginalExtension(),
                        'is_valid' => $file->isValid(),
                        'error' => $file->getError()
                    ];
                }
            } else {
                // Single file
                $result['files_count'] = 1;
                $result['files_info'][] = [
                    'index' => 0,
                    'original_name' => $files->getClientOriginalName(),
                    'size' => $files->getSize(),
                    'mime_type' => $files->getMimeType(),
                    'extension' => $files->getClientOriginalExtension(),
                    'is_valid' => $files->isValid(),
                    'error' => $files->getError()
                ];
            }
        }

        return response()->json($result);
    }
}
