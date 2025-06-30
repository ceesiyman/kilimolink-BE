<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/orders",
     *     summary="Create a new order with multiple products",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items", "shipping_address", "phone_number"},
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id", "quantity"},
     *                     @OA\Property(property="product_id", type="integer"),
     *                     @OA\Property(property="quantity", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="shipping_address", type="string"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Order created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|string',
            'phone_number' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $totalAmount = 0;
            $orderItems = [];

            // Calculate total and prepare order items
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $quantity = $item['quantity'];
                $unitPrice = $product->price;
                $totalPrice = $unitPrice * $quantity;

                $totalAmount += $totalPrice;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'status' => 'pending'
                ];
            }

            // Create order
            $order = Order::create([
                'user_id' => Auth::id(),
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'shipping_address' => $request->shipping_address,
                'phone_number' => $request->phone_number,
                'notes' => $request->notes
            ]);

            // Create order items
            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            DB::commit();

            // Load relationships for response
            $order->load(['items.product.user', 'user']);

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders",
     *     summary="Get all orders (admin only)",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of all orders")
     * )
     */
    public function index()
    {
        $orders = Order::with(['items.product.user', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    /**
     * @OA\Get(
     *     path="/api/orders/my-orders",
     *     summary="Get authenticated user's orders",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of user's orders")
     * )
     */
    public function myOrders()
    {
        $orders = Order::with(['items.product.user', 'user'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{id}",
     *     summary="Get a specific order",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order details"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function show($id)
    {
        $order = Order::with(['items.product.user', 'user'])->find($id);
        
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Check if user is authorized to view this order
        if (Auth::id() !== $order->user_id && !Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['order' => $order]);
    }

    /**
     * @OA\Patch(
     *     path="/api/orders/{id}/status",
     *     summary="Update order status (admin only)",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "cancelled"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order status updated successfully"),
     *     @OA\Response(response=404, description="Order not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled'
        ]);

        $order = Order::find($id);
        
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        try {
            DB::beginTransaction();

            $order->update(['status' => $request->status]);
            
            // Update all order items status
            $order->items()->update(['status' => $request->status]);

            DB::commit();

            $order->load(['items.product.user', 'user']);
            return response()->json([
                'message' => 'Order status updated successfully',
                'order' => $order
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/orders/{orderId}/items/{itemId}/status",
     *     summary="Update specific order item status (admin only)",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="itemId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "cancelled"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order item status updated successfully"),
     *     @OA\Response(response=404, description="Order or item not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateItemStatus(Request $request, $orderId, $itemId)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled'
        ]);

        $orderItem = OrderItem::where('order_id', $orderId)
            ->where('id', $itemId)
            ->first();
        
        if (!$orderItem) {
            return response()->json(['message' => 'Order item not found'], 404);
        }

        $orderItem->update(['status' => $request->status]);

        // Check if all items are completed
        $order = Order::find($orderId);
        $allItemsCompleted = $order->items()
            ->where('status', '!=', 'completed')
            ->count() === 0;

        if ($allItemsCompleted) {
            $order->update(['status' => 'completed']);
        }

        $order->load(['items.product.user', 'user']);
        return response()->json([
            'message' => 'Order item status updated successfully',
            'order' => $order
        ]);
    }
}
