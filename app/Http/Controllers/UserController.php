<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('q');

        $users = User::latest()
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('name', 'LIKE', '%' . $query . '%')
                    ->orWhere('username', 'LIKE', '%' . $query . '%');
            })->paginate(50);

        return new ResponseResource(true, "List users", $users);
    }

    public function store(Request $request)
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
            return response()->json(["errors" => $validator->errors()], 422);
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

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:2',
            'username' => 'required|min:2|unique:users,username,' . $id,
            'email' => 'required|min:2|unique:users,email,' . $id,
            'password' => 'required',
            'role_id' => 'required|exists:roles,id'
        ], [
            'username.unique' => 'Username sudah ada',
            'email.unique' => 'Email sudah ada',
            'role_id.exists' => 'Role tidak ada'
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return new ResponseResource(false, "User not found", null);
        }

        $user->update($request->all());

        return new ResponseResource(true, "User updated successfully", $user);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return new ResponseResource(false, "User not found", null);
        }

        $user->delete();

        return new ResponseResource(true, "User deleted successfully", null);
    }
}
