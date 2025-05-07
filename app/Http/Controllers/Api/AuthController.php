<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|max:20',
            'role' => 'required|in:farmer,investor',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'validation_errors' => $validator->errors()
            ], 422);
        }

        try {
            $userAccount = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'role' => $request->role,
            ]);

            if ($request->role === 'farmer') {
                $farmerProfile = \App\Models\Farmer::create([
                    'farmer_fname' => $request->name,
                    'farmer_lname' => $request->name,
                    'farmer_contact' => $request->contact ?? '',
                ]);
                $userAccount->userable()->associate($farmerProfile);
            } else {
                $investorProfile = \App\Models\Investor::create([
                    'investor_name' => $request->name,
                    'investor_contact_no' => $request->contact ?? '',
                    'investor_budget_range' => $request->budget_range ?? '0-0',
                    'investor_type' => $request->investor_type ?? 'individual',
                ]);
                $userAccount->userable()->associate($investorProfile);
            }

            $userAccount->save();

            $accessToken = $userAccount->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Account created successfully',
                'user_account' => $userAccount,
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', 60 * 24 * 7)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed',
                'error_details' => 'Unable to create user account. Please try again.'
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'validation_errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication failed',
                    'error_details' => 'Invalid credentials provided.'
                ], 401);
            }

            $userAccount = User::where('email', $request->email)->firstOrFail();
            $accessToken = $userAccount->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'user_account' => $userAccount,
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', 60 * 24 * 7)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Login failed',
                'error_details' => 'Unable to authenticate user. Please try again.'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed',
                'error_details' => 'Unable to logout. Please try again.'
            ], 500);
        }
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
} 