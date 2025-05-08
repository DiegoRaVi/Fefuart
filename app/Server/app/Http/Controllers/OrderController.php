<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller{

    // GET ORDER POR ID
    public function getOrderById($id){
        
        $user = Auth::user();

        $order = Order::with('products')->find($id);

        if(!$order){
            return response()->json(['message' => 'Order not found'], 404);
        }

        if($user->role !== 'admin' && $user->role !== $order->user_id){
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order, 200);
    }

    // GET ORDERS POR USER
    public function getUserOrders(){

        $user = Auth::user();

        $orders = Order::where('user_id', $user->id)->with('products')->orderBy('order_date', 'desc')->paginate(10);

        if($orders->isEmpty()){
            return response()->json(['message' => 'No orders found'], 404);
        }

        return response()->json($orders, 200);
    }

    // GET ORDERS POR USER ID
    public function getOrdersByUserId($id){

        $user = Auth::user();

        if($user->role !== 'admin'){
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $orders = Order::where('user_id', $id)->with('products')->orderBy('order_date', 'desc')->paginate(10);

        if($orders->isEmpty()){
            return response()->json(['message' => 'No orders found'], 404);
        }

        return response()->json($orders, 200);
    }

    public function getOrCreateCartOrder(Request $request){
    $user = Auth::user();

    if (!$user){
        return response()->json(['message' => 'User not authenticated'], 401);
    }

    // Buscar una orden con estado 'cart' para el usuario logueado
    $cartOrder = Order::where('user_id', $user->id)->where('status', 'cart')->first();

    if ($cartOrder){
        // Si existe una orden con estado 'cart', devolverla
        return response()->json($cartOrder, 200);
    }

    // Si no existe, crear una nueva orden con estado 'cart'
    $newOrder = Order::create([
        'user_id' => $user->id,
        'order_date' => now(),
        'status' => 'cart',
        'address' => $request->get('address', 'No address provided'), // Dirección opcional
        'total' => 0, // Total inicial en 0
    ]);

    return response()->json($newOrder, 201);
}

    // GET PENDING ORDERS
    public function getPendingOrders(){

        $user = Auth::user();

        if($user->role !== 'admin'){
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $orders = Order::where('status','pending')->with('products')->orderBy('order_date', 'desc')->paginate(10);

        return response()->json($orders, 200);
    }

    //POST NUEVA ORDER
    public function addOrder(Request $request){
        
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
    
        $request->validate([
            'order_date' => 'required|date|after_or_equal:today',
            'address' => 'required|string|max:255',
            'total' => 'required|numeric|min:0',
            //'user_id' => 'required|exists:users,id'
        ]);
    
        Order::create([
            'user_id' => $user->id,
            'order_date' => $request->order_date,
            'status' => 'pending',
            'address' => $request->address,
            'total' => $request->total,
        ]);
        return response()->json(['message' => 'Order created successfully'], 201);
    }

    // UPDATE ORDER POR ID
    public function updateOrderById(Request $request, $id){

        $user = Auth::user();

        if($user->role !== 'admin'){
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = Order::find($id);

        if(!$order){
            return response()->json(['message' => 'Order not found'], 404);
        }

        $request->validate([
            'order_date' => 'sometimes|date',
            'status' => 'sometimes|in:pending,paid,shipped,cancelled',
            'total' => 'sometimes|numeric|min:0',
        ]);

        $order->update($request->only(['order_date', 'status', 'total']));

        return response()->json($order, 200);
    }

    // DELETE ORDER POR ID
    public function deleteOrderById($id){

        $user = Auth::user();

        if($user->role !== 'admin'){
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