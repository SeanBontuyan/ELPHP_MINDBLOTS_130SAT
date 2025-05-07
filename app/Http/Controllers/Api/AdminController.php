<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function createAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'validation_errors' => $validator->errors()
            ], 422);
        }

        try {
            $administratorAccount = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'role' => 'admin',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Administrator account created successfully',
                'administrator' => $administratorAccount
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create administrator account',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function listAdmins()
    {
        try {
            $administrators = User::where('role', 'admin')->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Administrators retrieved successfully',
                'administrators' => $administrators
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve administrators',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
} 