<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{

    // POST NUEVO PRODUCT
    public function addProduct(Request $request){

        $validator = Validator::make($request->all(),[
            'name' => 'required|string|min:5|max:100',
            'price' => 'required|numeric',
            'description' => 'nullable|string',
            'category' => 'required|string',
            'subcategory' => 'nullable|string',
            'delivery_type' => 'required|in:digital,physical',
            'delivery_time' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'stock' => 'nullable|integer',
            'order_id' => 'required|exists:orders,id'
        ]);

        if($validator->fails()){
          return response()->json(['error' => $validator->errors()], 422);  
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('uploads', 'public');
        }

        Product::create([
            'name'=> $request->get('name'),
            'price'=> $request->get('price'),
            'quantity' => $request->get('quantity'),
            'description'=> $request->get('description'),
            'category'=> $request->get('category'),
            'subcategory'=> $request->get('subcategory'),
            'delivery_type'=> $request->get('delivery_type'),
            'delivery_time' => $request->get('delivery_time'),
            'image_url'=> $imagePath,
            'stock'=> $request->get('stock'),
            'order_id' => $request->get('order_id')
        ]);
        return response()->json(['message' => 'Product added successfully'], 201);
    }

    // GET TODOS LOS PRODUCTOS DE UNA ORDER CON SU ID
    public function getProductsByOrderId($orderId){

        $order = Order::with('products')->find($orderId);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->products->isEmpty()) {
            return response()->json(['message' => 'No products found for this order'], 404);
        }

        return response()->json($order->products, 200);
    }

    // GET PRODUCT POR SU ID
    public function getProductById($id){

        $product = Product::find($id);

        if(!$product){
            return response()->json(['message'=> 'Product not found', 404]);
        }

        return response()->json($product, 200);
    }

    // UPDATE PRODUCT POR SU ID
    public function updateProductById(Request $request, $id){

        $product = Product::find($id);

        if(!$product){
            return response()->json(['message'=> 'Product not found', 404]);
        }

        $validator = Validator::make($request->all(),[
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'category' => 'sometimes|string',
            'subcategory' => 'nullable|string',
            'delivery_type' => 'in:digital,physical',
            'price' => 'sometimes|numeric',
            'delivery_time' => 'sometimes|string',
            'image_url' => 'nullable|string',
            'stock' => 'nullable|integer',
        ]);

        if($validator->fails()){
          return response()->json(['error' => $validator->errors()], 422);  
        }

        if($request->has('name')){
            $product->name = $request->name;
        }

        if($request->has('price')){
            $product->price = $request->price;
        }

        if($request->has('delivery_type')){
            $product->delivery_type = $request->delivery_type;
        }

        $product->update();

        return response()->json(['message' => 'Product updated successfully'], 200);
    }

    // DELETE PRODUCT POR EL ID
    public function deleteProductById($id){

        $product = Product::find($id);

        if(!$product){
            return response()->json(['message' => 'Product not found', 404]);
        }

        // Si hay imagen, eliminarla del disco
        if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
            Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully'], 200);
        
    }
}
