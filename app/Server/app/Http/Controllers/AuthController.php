<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request){

        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:20',
            
            'email' => 'required|string|email|min:10|max:50|unique:users',
            'password' => 'required|string|min:5|confirmed',
        ]);

        if($validator->fails()){
          return response()->json(['error' => $validator->errors()], 422);  
        }

        User::create([
            'name'=> $request->get('name'),
            'role'=> $request->get('role', 'user'),
            'email'=> $request->get('email'),
            'password'=> bcrypt($request->get('password')),
        ]);

        return response()->json(['message' => 'User created successfully'], 201);
    }

    public function login(Request $request){

        $validator = Validator::make($request->all(),[
            'email' => 'required|string|email|min:10|max:50',
            'password' => 'required|string|min:5',
        ]);

        if($validator->fails()){
          return response()->json(['error' => $validator->errors()], 422);  
        }

        $credentials = $request->only(['email', 'password']);

        try {

            if(!$token = JWTAuth::attempt($credentials)){
                return response()->json(['error' => 'Invalid Credentials'], 401);
            }

            $user = Auth::user();

            return response()->json([
                'token' => $token,
                'user' => [
                    'name' => $user->name,
                    'role' => $user->role
                ]
            ], 200);

            //return response()->json(['token' => $token], 200);

        } catch (JWTException $e) {

            // SEC-012: devolver $e volcaba la excepcion completa al cliente
            // (traza, rutas del sistema y configuracion) con independencia de
            // APP_DEBUG. El detalle va al log; el cliente solo recibe el motivo.
            report($e);

            return response()->json(['error' => 'Could not create token'], 500);
        }
    }


    public function getUser(){

        $user = Auth::user();

        return response()->json($user, 200);
    }

    public function getUserById($id){
        
        $user = User::find($id);

        if(!$user){
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        return response()->json($user, 200);
    }

    public function logout(){
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}
