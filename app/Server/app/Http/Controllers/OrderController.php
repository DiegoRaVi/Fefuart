<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{


public function getOrdersByUserEmail(Request $request)
{
    $userEmail = $request->query('email');

    if (!$userEmail) {
        return response()->json(['message' => 'Email is required'], 400);
    }

    // Buscar el usuario por email (email es único)
    $user = User::where('email', $userEmail)->first();
    
    if (!$user) {
        return response()->json([
            'message' => 'User not found',
            'userEmail' => $userEmail
        ], 404);
    }

    // Obtener las órdenes asociadas a ese usuario, incluyendo los productos
    $orders = Order::where('user_id', $user->id)->with('products')->orderBy('status','asc')->orderBy('order_date','desc')->get();
    
    if ($orders->isEmpty()) {
    return response()->json([
        'orders' => [],
        'message' => 'No orders found for this user',
        'userEmail' => $userEmail
    ], 200);
}

    return response()->json($orders);
}

    // GET ORDER POR ID
    public function getOrderById($id)
    {

        $user = Auth::user();

        $order = Order::with('products')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($user->role !== 'admin' && $user->role !== $order->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order, 200);
    }

    // GET ORDERS POR USER
    public function getUserOrders()
    {

        $user = Auth::user();

        $orders = Order::where('user_id', $user->id)->with('products')->orderBy('order_date', 'desc')->paginate(10);

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'No orders found'], 404);
        }

        return response()->json($orders, 200);
    }

    // GET ORDERS POR USER ID
    public function getOrdersByUserId($id)
    {

        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $orders = Order::where('user_id', $id)->with('products')->orderBy('order_date', 'desc')->paginate(10);

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'No orders found'], 404);
        }

        return response()->json($orders, 200);
    }

    public function getCartOrder()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // Buscar una orden con estado 'cart' para el usuario logueado
        $cartOrder = Order::where('user_id', $user->id)->where('status', 'cart')->first();

        if ($cartOrder) {
            // Si existe una orden con estado 'cart', devolverla
            return response()->json($cartOrder, 200);
        } else {
            return response()->json(['message' => 'No cart order found'], 404);
        }

        // // Si no existe, crear una nueva orden con estado 'cart'
        // $newOrder = Order::create([
        //     'user_id' => $user->id,
        //     'order_date' => now(),
        //     'status' => 'cart',
        //     'address' => $request->get('address', 'No address provided'), // Dirección opcional
        //     'total' => 0, // Total inicial en 0
        // ]);

        // return response()->json($newOrder, 201);
    }

    // GET PENDING ORDERS
    public function getPendingOrders()
    {

        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $orders = Order::where('status', 'pending')->with('products')->orderBy('order_date', 'asc')->paginate(10);

        return response()->json($orders, 200);
    }


    // GET PAID ORDERS
    public function getPaidOrders()
    {

        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $orders = Order::where('status', 'paid')->with('products')->orderBy('order_date', 'asc')->paginate(10);

        return response()->json($orders, 200);
    }

    // GET SHIPPED ORDERS
    public function getShippedOrders()
    {

        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $orders = Order::where('status', 'shipped')->with('products')->orderBy('order_date', 'asc')->paginate(10);

        return response()->json($orders, 200);
    }

    // GET CANCELLED ORDERS
    public function getCancelledOrders()
    {

        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $orders = Order::where('status', 'cancelled')->with('products')->orderBy('order_date', 'asc')->paginate(10);

        return response()->json($orders, 200);
    }

    //POST NUEVA ORDER
    public function addOrder(Request $request)
    {

        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // $request->validate([
        //     'order_date' => 'required|date|after_or_equal:today',
        //     'address' => 'required|string|max:255',
        //     'total' => 'required|numeric|min:0',
        //     //'user_id' => 'required|exists:users,id'
        // ]);

        $newOrder = Order::create([
            'user_id' => $user->id,
            'order_date' => now(),
            'status' => 'cart',
            'address' => $request->get('address', 'No address provided'),
            'total' => 0,
        ]);

        return response()->json($newOrder, 201);
    }

    // UPDATE ORDER POR ID
    public function updateOrderById(Request $request, $id)
    {

        /*$user = Auth::user();

        if($user->role !== 'admin'){
            return response()->json(['message' => 'Unauthorized'], 403);
        }*/

        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $request->validate([
            'order_date' => 'sometimes|date',
            'status' => 'sometimes|in:pending,paid,shipped,cancelled',
            'address' => 'sometimes',
            'total' => 'sometimes|numeric|min:0',
        ]);

        $order->update($request->only(['order_date', 'address', 'status', 'total']));

        return response()->json($order, 200);
    }

    // DELETE ORDER POR ID
    public function deleteOrderById($id)
    {

        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->products()->count() > 0) {
            $order->products()->delete();
        }

        $order->delete();

        return response()->json(['message' => 'Order and associated products deleted successfully'], 200);
    }
}
