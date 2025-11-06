<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getUser(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateUser(Request $request)
    {
        $user = $request->user();
        $data = $request->only(['name', 'email']);

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }
}
