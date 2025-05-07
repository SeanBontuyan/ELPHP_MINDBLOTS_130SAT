<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Project;
use App\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CampaignController extends Controller
{
    public function index()
    {
        try {
            $fundingInitiatives = Campaign::with(['project', 'investments'])->get();
            return response()->json(['funding_initiatives' => $fundingInitiatives]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve funding initiatives',
                'error_details' => 'Unable to fetch initiative data. Please try again.'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $fundingInitiative = Campaign::with(['project', 'investments'])->findOrFail($id);
            return response()->json(['funding_initiative' => $fundingInitiative]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Initiative not found',
                'error_details' => 'The requested funding initiative does not exist.'
            ], 404);
        }
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'farmer') {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied',
                'error_details' => 'Only farmers can create funding initiatives.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|max:255',
            'project_description' => 'required|string',
            'project_capital' => 'required|numeric|min:0',
            'project_duration' => 'required|integer|min:1',
            'project_location' => 'required|string|max:255',
            'project_benefits' => 'required|string',
            'project_risks' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'validation_errors' => $validator->errors()
            ], 422);
        }

        try {
            $agriculturalProject = Project::create([
                'name' => $request->project_name,
                'description' => $request->project_description,
                'location' => $request->project_location,
                'capital_needed' => $request->project_capital,
                'duration_months' => $request->project_duration,
                'benefits' => $request->project_benefits,
                'risks' => $request->project_risks,
                'farmer_id' => $request->user()->id,
            ]);

            if (!$agriculturalProject) {
                throw new \Exception('Failed to create agricultural project');
            }

            $fundingInitiative = Campaign::create([
                'project_id' => $agriculturalProject->id,
                'target_amount' => $request->project_capital,
                'start_date' => now(),
                'end_date' => now()->addMonths($request->project_duration),
                'status' => 'pending',
            ]);

            if (!$fundingInitiative) {
                throw new \Exception('Failed to create funding initiative');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Funding initiative created successfully',
                'funding_initiative' => $fundingInitiative->load('project')
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Funding initiative creation failed: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create funding initiative',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function approve($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied',
                'error_details' => 'Only administrators can approve funding initiatives.'
            ], 403);
        }

        try {
            $fundingInitiative = Campaign::findOrFail($id);
            
            if ($fundingInitiative->status === 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid operation',
                    'error_details' => 'Funding initiative is already approved.'
                ], 400);
            }

            $fundingInitiative->status = 'active';
            $fundingInitiative->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Funding initiative approved successfully',
                'funding_initiative' => $fundingInitiative
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve funding initiative',
                'error_details' => 'Unable to approve initiative. Please try again.'
            ], 500);
        }
    }

    public function reject($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied',
                'error_details' => 'Only administrators can reject funding initiatives.'
            ], 403);
        }

        try {
            $fundingInitiative = Campaign::findOrFail($id);
            
            if ($fundingInitiative->status === 'rejected') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid operation',
                    'error_details' => 'Funding initiative is already rejected.'
                ], 400);
            }

            $fundingInitiative->status = 'rejected';
            $fundingInitiative->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Funding initiative rejected successfully',
                'funding_initiative' => $fundingInitiative
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject funding initiative',
                'error_details' => 'Unable to reject initiative. Please try again.'
            ], 500);
        }
    }

    public function fund(Request $request, $id)
    {
        if (auth()->user()->role !== 'investor') {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied',
                'error_details' => 'Only investors can fund initiatives.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'validation_errors' => $validator->errors()
            ], 422);
        }

        try {
            $fundingInitiative = Campaign::findOrFail($id);

            if ($fundingInitiative->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid operation',
                    'error_details' => 'Only active funding initiatives can receive investments.'
                ], 403);
            }

            if (now()->gt($fundingInitiative->end_date)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid operation',
                    'error_details' => 'Funding initiative has ended.'
                ], 403);
            }

            $investment = Investment::create([
                'campaign_id' => $fundingInitiative->id,
                'investor_id' => auth()->id(),
                'amount' => $request->amount,
            ]);

            if (!$investment) {
                throw new \Exception('Failed to create investment record');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Investment successful',
                'investment' => $investment,
                'funding_initiative' => $fundingInitiative->load('investments')
            ]);
        } catch (\Exception $e) {
            \Log::error('Investment failed: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process investment',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
} 