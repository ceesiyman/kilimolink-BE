<?php

namespace App\Http\Controllers;

use App\Models\CommunityMessage;
use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CommunityMessageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/community/messages",
     *     summary="Get all community messages",
     *     tags={"Community Messages"},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Search term", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category", in="query", description="Category filter", @OA\Schema(type="string")),
     *     @OA\Parameter(name="pinned", in="query", description="Show only pinned messages", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="announcement", in="query", description="Show only announcements", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="last_updated", in="query", description="Last update timestamp for polling", @OA\Schema(type="string", format="date-time")),
     *     @OA\Response(response="200", description="Messages retrieved successfully")
     * )
     */
    public function index(Request $request)
    {
        $query = CommunityMessage::with(['user:id,name,image_url', 'attachments'])
            ->withFilters($request)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc');

        // For polling: if last_updated is provided, only return messages updated after that time
        if ($request->has('last_updated')) {
            $lastUpdated = $request->get('last_updated');
            $query->where('updated_at', '>', $lastUpdated);
        }

        $messages = $query->paginate(20);

        // Add headers for efficient polling
        $response = response()->json([
            'success' => true,
            'data' => $messages,
            'server_time' => now()->toISOString(),
            'polling_interval' => 3000 // 3 seconds in milliseconds
        ]);

        // Set cache control headers for polling
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');

        return $response;
    }

    /**
     * @OA\Get(
     *     path="/api/community/messages/{id}",
     *     summary="Get a specific community message",
     *     tags={"Community Messages"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response="200", description="Message retrieved successfully"),
     *     @OA\Response(response="404", description="Message not found")
     * )
     */
    public function show($id)
    {
        $message = CommunityMessage::with([
            'user:id,name,image_url',
            'attachments',
            'replies.user:id,name,image_url',
            'replies.replies.user:id,name,image_url'
        ])->findOrFail($id);

        // Increment view count
        $message->incrementViews();

        // Check if current user liked this message
        $message->is_liked = Auth::check() ? $message->likedBy()->where('user_id', Auth::id())->exists() : false;

        return response()->json([
            'success' => true,
            'data' => $message
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/community/messages",
     *     summary="Create a new community message",
     *     tags={"Community Messages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", description="Message title"),
     *                 @OA\Property(property="content", type="string", description="Message content"),
     *                 @OA\Property(property="category", type="string", description="Message category"),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), description="Message tags (comma-separated string or array)"),
     *                 @OA\Property(property="is_pinned", type="boolean", example=false, description="Pin the message (true/false, 1/0, yes/no)"),
     *                 @OA\Property(property="is_announcement", type="boolean", example=false, description="Mark as announcement (true/false, 1/0, yes/no)"),
     *                 @OA\Property(property="attachments[]", type="array", @OA\Items(type="string", format="binary"), description="File attachments")
     *             )
     *         )
     *     ),
     *     @OA\Response(response="201", description="Message created successfully"),
     *     @OA\Response(response="422", description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|max:10000',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable',
            'is_pinned' => 'nullable|in:true,false,1,0,yes,no,y,n,on,off',
            'is_announcement' => 'nullable|in:true,false,1,0,yes,no,y,n,on,off',
            'attachments.*' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,mp4,avi,mov,mp3,wav'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Process tags - convert string to array if needed
        $tags = $request->tags;
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        } elseif (!is_array($tags)) {
            $tags = [];
        }

        // Process boolean fields - convert various formats to proper boolean for database storage
        $isPinned = $request->is_pinned;
        if (is_string($isPinned)) {
            $isPinned = in_array(strtolower(trim($isPinned)), ['true', '1', 'yes', 'on', 'y']);
        } elseif (is_numeric($isPinned)) {
            $isPinned = (bool)$isPinned;
        } elseif (!is_bool($isPinned)) {
            $isPinned = false;
        }

        $isAnnouncement = $request->is_announcement;
        if (is_string($isAnnouncement)) {
            $isAnnouncement = in_array(strtolower(trim($isAnnouncement)), ['true', '1', 'yes', 'on', 'y']);
        } elseif (is_numeric($isAnnouncement)) {
            $isAnnouncement = (bool)$isAnnouncement;
        } elseif (!is_bool($isAnnouncement)) {
            $isAnnouncement = false;
        }

        $message = CommunityMessage::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'content' => $request->content,
            'category' => $request->category,
            'tags' => $tags,
            'is_pinned' => $isPinned,
            'is_announcement' => $isAnnouncement
        ]);

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            $this->handleAttachments($message, $request->file('attachments'));
        }

        $message->load(['user:id,name,image_url', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => 'Message created successfully',
            'data' => $message
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/community/messages/{id}",
     *     summary="Update a community message",
     *     tags={"Community Messages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", description="Message title"),
     *                 @OA\Property(property="content", type="string", description="Message content"),
     *                 @OA\Property(property="category", type="string", description="Message category"),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), description="Message tags (comma-separated string or array)"),
     *                 @OA\Property(property="is_pinned", type="boolean", example=false, description="Pin the message (true/false, 1/0, yes/no)"),
     *                 @OA\Property(property="is_announcement", type="boolean", example=false, description="Mark as announcement (true/false, 1/0, yes/no)"),
     *                 @OA\Property(property="attachments[]", type="array", @OA\Items(type="string", format="binary"), description="File attachments")
     *             )
     *         )
     *     ),
     *     @OA\Response(response="200", description="Message updated successfully"),
     *     @OA\Response(response="403", description="Unauthorized"),
     *     @OA\Response(response="404", description="Message not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $message = CommunityMessage::findOrFail($id);

        // Check if user can edit this message
        if ($message->user_id !== Auth::id() && !Auth::user()->hasRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to edit this message'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|max:10000',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable',
            'is_pinned' => 'nullable|in:true,false,1,0,yes,no,y,n,on,off',
            'is_announcement' => 'nullable|in:true,false,1,0,yes,no,y,n,on,off',
            'attachments.*' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,mp4,avi,mov,mp3,wav'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Process tags - convert string to array if needed
        $tags = $request->tags;
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        } elseif (!is_array($tags)) {
            $tags = $message->tags ?? [];
        }

        // Process boolean fields - convert various formats to proper boolean for database storage
        $isPinned = $request->is_pinned;
        if (is_string($isPinned)) {
            $isPinned = in_array(strtolower(trim($isPinned)), ['true', '1', 'yes', 'on', 'y']);
        } elseif (is_numeric($isPinned)) {
            $isPinned = (bool)$isPinned;
        } elseif (!is_bool($isPinned)) {
            $isPinned = $message->is_pinned;
        }

        $isAnnouncement = $request->is_announcement;
        if (is_string($isAnnouncement)) {
            $isAnnouncement = in_array(strtolower(trim($isAnnouncement)), ['true', '1', 'yes', 'on', 'y']);
        } elseif (is_numeric($isAnnouncement)) {
            $isAnnouncement = (bool)$isAnnouncement;
        } elseif (!is_bool($isAnnouncement)) {
            $isAnnouncement = $message->is_announcement;
        }

        $message->update([
            'title' => $request->title,
            'content' => $request->content,
            'category' => $request->category,
            'tags' => $tags,
            'is_pinned' => $isPinned,
            'is_announcement' => $isAnnouncement
        ]);

        // Handle new file attachments
        if ($request->hasFile('attachments')) {
            $this->handleAttachments($message, $request->file('attachments'));
        }

        $message->load(['user:id,name,image_url', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => 'Message updated successfully',
            'data' => $message
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/community/messages/{id}",
     *     summary="Delete a community message",
     *     tags={"Community Messages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response="200", description="Message deleted successfully"),
     *     @OA\Response(response="403", description="Unauthorized"),
     *     @OA\Response(response="404", description="Message not found")
     * )
     */
    public function destroy($id)
    {
        $message = CommunityMessage::findOrFail($id);

        // Check if user can delete this message
        if ($message->user_id !== Auth::id() && !Auth::user()->hasRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this message'
            ], 403);
        }

        // Delete attachments from storage
        foreach ($message->attachments as $attachment) {
            $filePath = public_path($attachment->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/community/messages/{id}/like",
     *     summary="Toggle like on a community message",
     *     tags={"Community Messages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response="200", description="Like toggled successfully")
     * )
     */
    public function toggleLike($id)
    {
        $message = CommunityMessage::findOrFail($id);
        $isLiked = $message->toggleLike(Auth::id());

        return response()->json([
            'success' => true,
            'message' => $isLiked ? 'Message liked' : 'Message unliked',
            'data' => [
                'is_liked' => $isLiked,
                'likes_count' => $message->fresh()->likes_count
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/community/messages/latest",
     *     summary="Get latest messages for polling",
     *     tags={"Community Messages"},
     *     @OA\Parameter(name="last_id", in="query", description="Last message ID seen", @OA\Schema(type="integer")),
     *     @OA\Response(response="200", description="Latest messages retrieved")
     * )
     */
    public function getLatest(Request $request)
    {
        $lastId = $request->get('last_id', 0);
        
        $messages = CommunityMessage::with(['user:id,name,image_url', 'attachments'])
            ->where('id', '>', $lastId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
            'last_id' => $messages->max('id') ?? $lastId
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/community/messages/poll",
     *     summary="Poll for new community messages (optimized for real-time)",
     *     tags={"Community Messages"},
     *     @OA\Parameter(name="last_id", in="query", description="Last message ID seen", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="last_updated", in="query", description="Last update timestamp", @OA\Schema(type="string", format="date-time")),
     *     @OA\Response(response="200", description="New messages retrieved successfully")
     * )
     */
    public function poll(Request $request)
    {
        $lastId = $request->get('last_id', 0);
        $lastUpdated = $request->get('last_updated');
        
        $query = CommunityMessage::with(['user:id,name,image_url', 'attachments'])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc');

        // Get messages newer than the last seen ID
        if ($lastId > 0) {
            $query->where('id', '>', $lastId);
        }

        // Or get messages updated after the last update time
        if ($lastUpdated) {
            $query->where('updated_at', '>', $lastUpdated);
        }

        // Limit results for polling efficiency
        $messages = $query->limit(50)->get();

        $response = response()->json([
            'success' => true,
            'data' => $messages,
            'last_id' => $messages->max('id') ?? $lastId,
            'server_time' => now()->toISOString(),
            'has_new_messages' => $messages->count() > 0,
            'polling_interval' => 3000 // 3 seconds in milliseconds
        ]);

        // Set cache control headers for polling
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');

        return $response;
    }

    private function handleAttachments($message, $files)
    {
        $uploadPath = public_path('communityfiles');
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        foreach ($files as $index => $file) {
            try {
                // Validate file
                if (!$file->isValid()) {
                    \Log::warning('Invalid file uploaded: ' . $file->getClientOriginalName());
                    continue; // Skip invalid files
                }
                
                // Get file information before moving
                $originalName = $file->getClientOriginalName();
                $mimeType = $file->getMimeType();
                $fileSize = $file->getSize();
                
                // Validate file size (10MB limit)
                if ($fileSize > 10 * 1024 * 1024) {
                    \Log::warning('File too large: ' . $originalName . ' (' . $fileSize . ' bytes)');
                    continue; // Skip files larger than 10MB
                }
                
                $fileName = time() . '_' . $index . '_' . $originalName;
                $filePath = $uploadPath . '/' . $fileName;
                
                // Use copy instead of move to avoid temporary file issues
                if (copy($file->getPathname(), $filePath)) {
                    $fileType = $this->getFileType($mimeType);
                    
                    MessageAttachment::create([
                        'community_message_id' => $message->id,
                        'file_name' => $originalName,
                        'file_path' => 'communityfiles/' . $fileName,
                        'file_type' => $fileType,
                        'mime_type' => $mimeType,
                        'file_size' => $fileSize,
                        'order' => $index
                    ]);
                    
                    \Log::info('File uploaded successfully: ' . $originalName);
                } else {
                    \Log::error('Failed to copy file: ' . $originalName);
                }
            } catch (\Exception $e) {
                // Log the error but continue processing other files
                \Log::error('File upload error for ' . ($file->getClientOriginalName() ?? 'unknown') . ': ' . $e->getMessage());
                continue;
            }
        }
    }

    private function getFileType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } else {
            return 'document';
        }
    }
}
