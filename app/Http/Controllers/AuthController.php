<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('user')->plainTextToken;
            return new ResponseResource(true, "berhasil login", [$token, $user]);
            // return response()->json(['token' => $token]);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:2',
            'username' => 'required|min:2|unique:users,username',
            'email' => 'required|min:2|unique:users,email',
            'password' => 'required',
            'role_id' => 'required|exists:roles,id'
        ], [
            'username.unique' => 'Username sudah ada',
            'email.unique' => 'Email sudah ada',
            'role_id.exists' => 'Role tidak ada'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return (new ResponseResource(false, $errors[0], null))
                ->response()->setStatusCode(422);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
            'role_id' => $request->role_id
        ]);

        return new ResponseResource(true, "Data berhasil ditambahkan", $user);
    }
}
