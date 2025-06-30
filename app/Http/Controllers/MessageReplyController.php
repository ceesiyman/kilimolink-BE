<?php

namespace App\Http\Controllers;

use App\Models\CommunityMessage;
use App\Models\MessageReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessageReplyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/community/messages/{messageId}/replies",
     *     summary="Get replies for a community message",
     *     tags={"Message Replies"},
     *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Response(response="200", description="Replies retrieved successfully")
     * )
     */
    public function index($messageId)
    {
        $replies = MessageReply::with(['user:id,name,image_url', 'replies.user:id,name,image_url'])
            ->where('community_message_id', $messageId)
            ->whereNull('parent_reply_id')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $replies
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/community/messages/{messageId}/replies",
     *     summary="Add a reply to a community message",
     *     tags={"Message Replies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", description="Reply content"),
     *             @OA\Property(property="parent_reply_id", type="integer", description="Parent reply ID for nested replies")
     *         )
     *     ),
     *     @OA\Response(response="201", description="Reply created successfully"),
     *     @OA\Response(response="422", description="Validation error")
     * )
     */
    public function store(Request $request, $messageId)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
            'parent_reply_id' => 'nullable|exists:message_replies,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if parent reply belongs to the same message
        if ($request->parent_reply_id) {
            $parentReply = MessageReply::find($request->parent_reply_id);
            if ($parentReply->community_message_id != $messageId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent reply does not belong to this message'
                ], 422);
            }
        }

        $reply = MessageReply::create([
            'community_message_id' => $messageId,
            'user_id' => Auth::id(),
            'content' => $request->content,
            'parent_reply_id' => $request->parent_reply_id
        ]);

        // Update message replies count
        $message = CommunityMessage::find($messageId);
        $message->updateRepliesCount();

        $reply->load(['user:id,name,image_url', 'replies.user:id,name,image_url']);

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully',
            'data' => $reply
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/community/messages/{messageId}/replies/{replyId}",
     *     summary="Update a reply",
     *     tags={"Message Replies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="replyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", description="Reply content")
     *         )
     *     ),
     *     @OA\Response(response="200", description="Reply updated successfully"),
     *     @OA\Response(response="403", description="Unauthorized"),
     *     @OA\Response(response="404", description="Reply not found")
     * )
     */
    public function update(Request $request, $messageId, $replyId)
    {
        $reply = MessageReply::where('community_message_id', $messageId)
            ->where('id', $replyId)
            ->firstOrFail();

        // Check if user can edit this reply
        if ($reply->user_id !== Auth::id() && !Auth::user()->hasRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to edit this reply'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $reply->update([
            'content' => $request->content
        ]);

        $reply->load(['user:id,name,image_url', 'replies.user:id,name,image_url']);

        return response()->json([
            'success' => true,
            'message' => 'Reply updated successfully',
            'data' => $reply
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/community/messages/{messageId}/replies/{replyId}",
     *     summary="Delete a reply",
     *     tags={"Message Replies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="replyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response="200", description="Reply deleted successfully"),
     *     @OA\Response(response="403", description="Unauthorized"),
     *     @OA\Response(response="404", description="Reply not found")
     * )
     */
    public function destroy($messageId, $replyId)
    {
        $reply = MessageReply::where('community_message_id', $messageId)
            ->where('id', $replyId)
            ->firstOrFail();

        // Check if user can delete this reply
        if ($reply->user_id !== Auth::id() && !Auth::user()->hasRole(['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this reply'
            ], 403);
        }

        $reply->delete();

        // Update message replies count
        $message = CommunityMessage::find($messageId);
        $message->updateRepliesCount();

        return response()->json([
            'success' => true,
            'message' => 'Reply deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/community/messages/{messageId}/replies/{replyId}/like",
     *     summary="Toggle like on a reply",
     *     tags={"Message Replies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="messageId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="replyId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response="200", description="Like toggled successfully")
     * )
     */
    public function toggleLike($messageId, $replyId)
    {
        $reply = MessageReply::where('community_message_id', $messageId)
            ->where('id', $replyId)
            ->firstOrFail();

        $isLiked = $reply->toggleLike(Auth::id());

        return response()->json([
            'success' => true,
            'message' => $isLiked ? 'Reply liked' : 'Reply unliked',
            'data' => [
                'is_liked' => $isLiked,
                'likes_count' => $reply->fresh()->likes_count
            ]
        ]);
    }
}
