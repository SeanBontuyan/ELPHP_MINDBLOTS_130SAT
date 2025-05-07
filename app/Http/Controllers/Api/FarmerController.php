<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class FarmerController extends Controller
{
    public function register(Request $request)
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
            $farmerAccount = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'role' => 'farmer',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Farmer account created successfully',
                'farmer_account' => $farmerAccount
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create farmer account',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {
            $farmerAccounts = User::where('role', 'farmer')->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Farmer accounts retrieved successfully',
                'farmer_accounts' => $farmerAccounts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve farmer accounts',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $farmerAccount = User::where('role', 'farmer')->findOrFail($id);
            return response()->json([
                'status' => 'success',
                'message' => 'Farmer account retrieved successfully',
                'farmer_account' => $farmerAccount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve farmer account',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'farmer_fname' => 'required|string|max:30',
            'farmer_lname' => 'required|string|max:30',
            'farmer_contact' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $farmer = User::create($request->all());
        return response()->json($farmer, 201);
    }

    public function update(Request $request, User $farmer)
    {
        $validator = Validator::make($request->all(), [
            'farmer_fname' => 'required|string|max:30',
            'farmer_lname' => 'required|string|max:30',
            'farmer_contact' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'validation_errors' => $validator->errors()
            ], 422);
        }

        try {
            $farmer->update($request->all());
            return response()->json([
                'status' => 'success',
                'message' => 'Farmer account updated successfully',
                'farmer_account' => $farmer
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update farmer account',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(User $farmer)
    {
        try {
            $farmer->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Farmer account deleted successfully'
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete farmer account',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
} 