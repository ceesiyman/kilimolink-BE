<?php

namespace App\Http\Controllers;

use App\Models\TipCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TipCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tip-categories",
     *     summary="Get all tip categories (Public endpoint)",
     *     tags={"Tips"},
     *     @OA\Response(
     *         response=200,
     *         description="List of tip categories with tips count",
     *         @OA\JsonContent(
     *             @OA\Property(property="categories", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="icon", type="string"),
     *                 @OA\Property(property="tips_count", type="integer"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ))
     *         )
     *     )
     * )
     */
    public function index()
    {
        // This is a public endpoint, accessible to all users
        $categories = TipCategory::withCount('tips')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => $categories,
            'total_categories' => $categories->count(),
            'total_tips' => $categories->sum('tips_count')
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tip-categories",
     *     summary="Create a new tip category",
     *     tags={"Tips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="icon", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can create categories'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:tip_categories',
            'description' => 'nullable|string',
            'icon' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = TipCategory::create($request->all());

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/tip-categories/{id}",
     *     summary="Update a tip category",
     *     tags={"Tips"},
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
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="icon", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can update categories'], 403);
        }

        $category = TipCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255|unique:tip_categories,name,' . $id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->update($request->all());

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/tip-categories/{id}",
     *     summary="Delete a tip category",
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
     *         description="Category deleted successfully"
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function destroy($id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can delete categories'], 403);
        }

        $category = TipCategory::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
