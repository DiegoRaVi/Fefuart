<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller{

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

    public function getAllOrders(){

        $user = Auth::user();

        if($user->role !== 'admin'){
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $orders = Order::with('products')->orderBy('order_date', 'desc')->paginate(10);

        return response()->json($orders, 200);
    }


    public function createOrder(){
        
    }


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