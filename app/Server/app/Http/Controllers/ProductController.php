<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function addProduct(Request $request){

        $validator = Validator::make($request->all(),[
            'name' => 'required|string|min:5|max:100',
            'price' => 'required|numeric',
            'description' => 'nullable|string',
            'category' => 'required|string',
            'subcategory' => 'nullable|string',
            'delivery_type' => 'required|in:digital,physical',
            'delivery_time' => 'required|string',
            'image_url' => 'nullable|string',
            'stock' => 'nullable|integer',
        ]);

        if($validator->fails()){
          return response()->json(['error' => $validator->errors()], 422);  
        }

        Product::create([
            'name'=> $request->get('name'),
            'price'=> $request->get('price'),
            'quantity' => $request->get('quantity'),
            'description'=> $request->get('description'),
            'category'=> $request->get('category'),
            'subcategory'=> $request->get('subcategory'),
            'delivery_type'=> $request->get('delivery_type'),
            'delivery_time' => 'required|string',
            'image_url'=> $request->get('image_url'),
            'stock'=> $request->get('stock'),
        ]);
        return response()->json(['message' => 'Product added successfully'], 201);
    }


    public function getProducts(){

        $products = Product::all();

        if($products->isEmpty()){
            return response()->json(['message' => 'No products found'], 404);
        }

        return response()->json($products, 200);
    }


    public function getProductById($id){

        $product = Product::find($id);

        if(!$product){
            return response()->json(['message'=> 'Product not found', 404]);
        }

        return response()->json($product, 200);
    }


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
            'delivery_type' => 'in:digital,físico',
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

        $product->update();

        return response()->json(['message' => 'Product updated successfully'], 200);
    }

    
    public function deleteProductById($id){

        $product = Product::find($id);

        if(!$product){
            return response()->json(['message' => 'Product not found', 404]);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully'], 200);
    
    }
}
