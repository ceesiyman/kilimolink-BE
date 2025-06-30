<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Info(
 *     title="User Auth API",
 *     version="1.0.0"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new user",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","role"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="username", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="image_url", type="string"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="role", type="string", enum={"admin","expert","customer","farmer"}),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'image_url' => 'nullable|string',
            'location' => 'nullable|string',
            'role' => 'required|string|in:admin,expert,customer,farmer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'image_url' => $request->image_url,
            'location' => $request->location,
            'role' => $request->role,
        ]);

        // Generate token (using Laravel Sanctum or Passport, here just a placeholder)
        $token = $user->createToken('auth_token')->plainTextToken ?? Str::random(60);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Login user by email and password",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Generate token (using Laravel Sanctum or Passport, here just a placeholder)
        $token = $user->createToken('auth_token')->plainTextToken ?? Str::random(60);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/user",
     *     summary="Update user details (only provided fields are updated)",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="username", type="string"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="role", type="string", enum={"admin","expert","customer","farmer"}),
     *             @OA\Property(property="favorites", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="saved_tips", type="array", @OA\Items(type="string")),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User details updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated."),
     *     @OA\Response(response=422, description="Validation error.")
     * )
     */
    public function updateUserDetails(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:20',
            'location' => 'sometimes|string',
            'role' => 'sometimes|string|in:admin,expert,customer,farmer',
            'favorites' => 'sometimes|array',
            'saved_tips' => 'sometimes|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $updateData = $request->only(['name', 'username', 'phone_number', 'location', 'role', 'favorites', 'saved_tips']);
        $user->update($updateData);
        return response()->json(['user' => $user]);
    }

    /**
     * @OA\Post(
     *     path="/api/user/image",
     *     summary="Update user image (upload image to public/storage/userImage and update image_url)",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image"},
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User image updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateUserImage(Request $request)
    {
        try {
            $user = $request->user();

            // Basic validation
            if (!$request->hasFile('image')) {
                return response()->json([
                    'message' => 'No image file provided',
                    'errors' => ['image' => ['Please provide an image file']]
                ], 422);
            }

            $image = $request->file('image');
            
            // Generate unique filename with original extension
            $extension = $image->getClientOriginalExtension() ?: 'jpg';
            $imageName = uniqid() . '_' . time() . '.' . $extension;
            
            // Ensure the directory exists in public path
            $uploadPath = public_path('userImage');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Move the file directly to public directory
            $image->move($uploadPath, $imageName);

            // Delete old image if exists
            if ($user->image_url) {
                $oldImagePath = public_path(str_replace('storage/', '', $user->image_url));
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            // Update user image URL to point to the public directory
            $user->image_url = 'userImage/' . $imageName;
            $user->save();

            return response()->json([
                'message' => 'Image updated successfully',
                'user' => $user,
                'image_url' => url($user->image_url) // Return the full URL
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Image upload error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update image',
                'errors' => ['image' => ['An error occurred while uploading the image']]
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/user/profile",
     *     summary="Get authenticated user's profile details",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function userProfile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => $user
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout user and revoke token",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function logout(Request $request)
    {
        // Revoke the current user's token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ], 200);
    }
} 