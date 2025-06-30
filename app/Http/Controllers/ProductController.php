<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/products",
     *     summary="Get all products with optional filters (min_price, max_price, category_id, created_after, created_before)",
     *     tags={"Products"},
     *     @OA\Parameter(name="min_price", in="query", required=false, @OA\Schema(type="number")),
     *     @OA\Parameter(name="max_price", in="query", required=false, @OA\Schema(type="number")),
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="created_after", in="query", required=false, @OA\Schema(type="string", format="date-time")),
     *     @OA\Parameter(name="created_before", in="query", required=false, @OA\Schema(type="string", format="date-time")),
     *     @OA\Response(response=200, description="List of products with seller details")
     * )
     */
    public function index(Request $request)
    {
        $query = Product::query();
        if ($request->min_price) $query->where('price', '>=', $request->min_price);
        if ($request->max_price) $query->where('price', '<=', $request->max_price);
        if ($request->category_id) $query->where('category_id', $request->category_id);
        if ($request->created_after) $query->where('created_at', '>=', $request->created_after);
        if ($request->created_before) $query->where('created_at', '<=', $request->created_before);
        
        $products = $query->with([
            'category',
            'user' => function($query) {
                $query->select('id', 'name', 'username', 'email', 'phone_number', 'location', 'image_url', 'role');
            }
        ])->orderBy('created_at', 'desc')->get();
        
        return response()->json(['products' => $products]);
    }

    /**
     * @OA\Get(
     *     path="/api/products/featured",
     *     summary="Get all featured products",
     *     tags={"Products"},
     *     @OA\Response(response=200, description="List of featured products")
     * )
     */
    public function featured()
    {
        $products = Product::where('is_featured', true)->with('category')->orderBy('created_at', 'desc')->get();
        return response()->json(['products' => $products]);
    }

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     summary="Get a single product by ID",
     *     tags={"Products"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product details"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function show($id)
    {
        $product = Product::with('category')->find($id);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);
        return response()->json(['product' => $product]);
    }

    /**
     * @OA\Post(
     *     path="/api/products",
     *     summary="Create a new product (image upload to public/productImages)",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name","description","price","category_id","image"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="is_featured", type="boolean"),
     *                 @OA\Property(property="stock", type="integer"),
     *                 @OA\Property(property="location", type="string"),
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Product created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'category_id' => 'required|exists:categories,id',
            'image' => 'required|image',
            'is_featured' => 'sometimes|in:true,false,1,0,"true","false"',
            'stock' => 'sometimes|integer',
            'location' => 'sometimes|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $image = $request->file('image');
        $imageName = uniqid() . '_' . time() . '.' . $image->getClientOriginalExtension();
        $uploadPath = public_path('productImages');
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        $image->move($uploadPath, $imageName);

        // Convert is_featured to integer
        $isFeatured = $request->is_featured;
        if (is_string($isFeatured)) {
            $isFeatured = filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        } elseif (is_bool($isFeatured)) {
            $isFeatured = $isFeatured ? 1 : 0;
        }

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'category_id' => $request->category_id,
            'is_featured' => $isFeatured,
            'stock' => $request->stock ?? 0,
            'location' => $request->location,
            'image' => 'productImages/' . $imageName,
            'user_id' => Auth::id(),
        ]);
        return response()->json(['message' => 'Product created successfully', 'product' => $product], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/products/{id}",
     *     summary="Update a product (image upload to public/productImages)",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="price", type="number"),
     *                 @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="is_featured", type="boolean"),
     *                 @OA\Property(property="stock", type="integer"),
     *                 @OA\Property(property="location", type="string"),
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Product updated successfully"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'category_id' => 'sometimes|exists:categories,id',
            'image' => 'sometimes|image',
            'is_featured' => 'sometimes|in:true,false,1,0,"true","false"',
            'stock' => 'sometimes|integer',
            'location' => 'sometimes|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = $request->except('image');
        
        // Convert is_featured to integer if it exists in the request
        if ($request->has('is_featured')) {
            $isFeatured = $request->is_featured;
            if (is_string($isFeatured)) {
                $updateData['is_featured'] = filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            } elseif (is_bool($isFeatured)) {
                $updateData['is_featured'] = $isFeatured ? 1 : 0;
            }
        }

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = uniqid() . '_' . time() . '.' . $image->getClientOriginalExtension();
            $uploadPath = public_path('productImages');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $image->move($uploadPath, $imageName);
            // Delete old image
            if ($product->image && file_exists(public_path($product->image))) {
                unlink(public_path($product->image));
            }
            $updateData['image'] = 'productImages/' . $imageName;
        }

        $product->update($updateData);
        return response()->json(['message' => 'Product updated successfully', 'product' => $product]);
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     summary="Delete a product",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product deleted successfully"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);
        // Delete image
        if ($product->image && file_exists(public_path($product->image))) {
            unlink(public_path($product->image));
        }
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
