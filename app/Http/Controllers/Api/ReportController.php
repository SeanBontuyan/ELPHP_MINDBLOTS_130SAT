<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // Admin Reports
    public function adminDashboard()
    {
        try {
            $userAccounts = User::select('id', 'name', 'email', 'phone', 'role', 'created_at')
                        ->get()
                        ->map(function ($account) {
                            return [
                                'account_id' => $account->id,
                                'full_name' => $account->name,
                                'email_address' => $account->email,
                                'contact_number' => $account->phone,
                                'account_type' => $account->role,
                                'registration_date' => $account->created_at->format('Y-m-d H:i:s'),
                                'total_initiatives' => $account->role === 'farmer' ? $account->projects->count() : 0,
                                'total_contributions' => $account->role === 'investor' ? $account->investments->sum('amount') : 0
                            ];
                        });

            $fundingInitiatives = Campaign::with(['project', 'investments'])
                                ->get()
                                ->map(function ($initiative) {
                                    $totalContributions = $initiative->investments->sum('amount');
                                    $contributorCount = $initiative->investments->count();
                                    $fundingProgress = $initiative->target_amount > 0 
                                        ? round(($totalContributions / $initiative->target_amount) * 100, 2)
                                        : 0;

                                    return [
                                        'initiative_id' => $initiative->id,
                                        'project_title' => $initiative->project->name,
                                        'farmer_name' => $initiative->project->farmer->name,
                                        'target_amount' => $initiative->target_amount,
                                        'start_date' => $initiative->start_date->format('Y-m-d'),
                                        'end_date' => $initiative->end_date->format('Y-m-d'),
                                        'status' => $initiative->status,
                                        'funding_metrics' => [
                                            'total_contributions' => $totalContributions,
                                            'contributor_count' => $contributorCount,
                                            'funding_progress' => $fundingProgress . '%',
                                            'remaining_amount' => max(0, $initiative->target_amount - $totalContributions)
                                        ],
                                        'contributions' => $initiative->investments->map(function ($contribution) {
                                            return [
                                                'contributor_name' => $contribution->investor->name,
                                                'contribution_amount' => $contribution->amount,
                                                'contribution_date' => $contribution->created_at->format('Y-m-d H:i:s')
                                            ];
                                        })
                                    ];
                                });

            $totalContributions = Investment::sum('amount');

            $contributionsByStatus = Campaign::with('investments')
                                    ->get()
                                    ->groupBy('status')
                                    ->map(function ($initiatives) {
                                        return $initiatives->sum(function ($initiative) {
                                            return $initiative->investments->sum('amount');
                                        });
                                    });

            return response()->json([
                'status' => 'success',
                'message' => 'Administrator dashboard data retrieved successfully',
                'data' => [
                    'user_accounts' => $userAccounts,
                    'funding_initiatives' => $fundingInitiatives,
                    'total_contributions' => $totalContributions,
                    'contributions_by_status' => $contributionsByStatus
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Administrator dashboard error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve administrator dashboard data',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function adminCampaignsReport()
    {
        $campaigns = Campaign::with(['farmer', 'investments', 'investors'])
            ->withCount('investments')
            ->withSum('investments', 'amount')
            ->get();

        return response()->json(['campaigns' => $campaigns]);
    }

    // Farmer Reports
    public function farmerDashboard(Request $request)
    {
        try {
            $farmerAccount = $request->user();
            
            if ($farmerAccount->role !== 'farmer') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied',
                    'error_details' => 'Only farmers can access this dashboard.'
                ], 403);
            }

            $initiatives = Campaign::whereHas('project', function($query) use ($farmerAccount) {
                    $query->where('farmer_id', $farmerAccount->id);
                })
                ->with(['project', 'investments', 'investments.investor'])
                ->get()
                ->map(function ($initiative) {
                    $totalContributions = $initiative->investments->sum('amount');
                    $contributorCount = $initiative->investments->count();
                    $fundingProgress = $initiative->target_amount > 0 
                        ? round(($totalContributions / $initiative->target_amount) * 100, 2)
                        : 0;

                    return [
                        'initiative_id' => $initiative->id,
                        'project_title' => $initiative->project->name,
                        'project_description' => $initiative->project->description,
                        'target_amount' => $initiative->target_amount,
                        'start_date' => $initiative->start_date->format('Y-m-d'),
                        'end_date' => $initiative->end_date->format('Y-m-d'),
                        'status' => $initiative->status,
                        'funding_metrics' => [
                            'total_contributions' => $totalContributions,
                            'contributor_count' => $contributorCount,
                            'funding_progress' => $fundingProgress . '%',
                            'remaining_amount' => max(0, $initiative->target_amount - $totalContributions)
                        ],
                        'contributions' => $initiative->investments->map(function ($contribution) {
                            return [
                                'contributor_name' => $contribution->investor->name,
                                'contribution_amount' => $contribution->amount,
                                'contribution_date' => $contribution->created_at->format('Y-m-d H:i:s')
                            ];
                        })
                    ];
                });

            $totalContributions = $initiatives->sum(function ($initiative) {
                return $initiative['funding_metrics']['total_contributions'];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Farmer dashboard data retrieved successfully',
                'data' => [
                    'total_initiatives' => $initiatives->count(),
                    'total_contributions_received' => $totalContributions,
                    'funding_initiatives' => $initiatives
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Farmer dashboard error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve farmer dashboard data',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function farmerCampaignReport(Request $request, $campaignId)
    {
        $campaign = Campaign::where('farmer_id', $request->user()->id)
            ->with(['investments', 'investors'])
            ->withSum('investments', 'amount')
            ->findOrFail($campaignId);

        $investmentDetails = $campaign->investments()
            ->with('investor')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'campaign' => $campaign,
            'investment_details' => $investmentDetails,
        ]);
    }

    public function farmerCampaignsReport(Request $request)
    {
        try {
            $farmer = $request->user();
            
            if ($farmer->role !== 'farmer') {
                return response()->json([
                    'message' => 'Unauthorized',
                    'error' => 'Only farmers can access this report.'
                ], 403);
            }

            $campaigns = Campaign::whereHas('project', function($query) use ($farmer) {
                    $query->where('farmer_id', $farmer->id);
                })
                ->with(['project', 'investments', 'investments.investor'])
                ->get()
                ->map(function ($campaign) {
                    $totalFunds = $campaign->investments->sum('amount');
                    $investorCount = $campaign->investments->count();
                    $fundingProgress = $campaign->target_amount > 0 
                        ? round(($totalFunds / $campaign->target_amount) * 100, 2)
                        : 0;

                    return [
                        'id' => $campaign->id,
                        'project_name' => $campaign->project->name,
                        'project_description' => $campaign->project->description,
                        'target_amount' => $campaign->target_amount,
                        'start_date' => $campaign->start_date->format('Y-m-d'),
                        'end_date' => $campaign->end_date->format('Y-m-d'),
                        'status' => $campaign->status,
                        'funding_details' => [
                            'total_funds' => $totalFunds,
                            'investor_count' => $investorCount,
                            'funding_progress' => $fundingProgress . '%',
                            'remaining_amount' => max(0, $campaign->target_amount - $totalFunds)
                        ],
                        'investments' => $campaign->investments->map(function ($investment) {
                            return [
                                'investor_name' => $investment->investor->name,
                                'amount' => $investment->amount,
                                'invested_at' => $investment->created_at->format('Y-m-d H:i:s')
                            ];
                        })
                    ];
                });

            $totalFunds = $campaigns->sum(function ($campaign) {
                return $campaign['funding_details']['total_funds'];
            });

            return response()->json([
                'message' => 'Farmer campaigns report retrieved successfully',
                'data' => [
                    'total_campaigns' => $campaigns->count(),
                    'total_funds_received' => $totalFunds,
                    'campaigns' => $campaigns
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Farmer campaigns report error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to retrieve farmer campaigns report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Investor Reports
    public function investorDashboard(Request $request)
    {
        try {
            $investorAccount = $request->user();
            
            if ($investorAccount->role !== 'investor') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied',
                    'error_details' => 'Only investors can access this dashboard.'
                ], 403);
            }

            $contributions = Investment::where('investor_id', $investorAccount->id)
                ->with(['campaign.project', 'campaign.project.farmer'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($contribution) {
                    $initiative = $contribution->campaign;
                    $totalInitiativeContributions = $initiative->investments->sum('amount');
                    $fundingProgress = $initiative->target_amount > 0 
                        ? round(($totalInitiativeContributions / $initiative->target_amount) * 100, 2)
                        : 0;

                    return [
                        'contribution_id' => $contribution->id,
                        'contribution_amount' => $contribution->amount,
                        'contribution_date' => $contribution->created_at->format('Y-m-d H:i:s'),
                        'funding_initiative' => [
                            'initiative_id' => $initiative->id,
                            'project_title' => $initiative->project->name,
                            'project_description' => $initiative->project->description,
                            'farmer_name' => $initiative->project->farmer->name,
                            'target_amount' => $initiative->target_amount,
                            'start_date' => $initiative->start_date->format('Y-m-d'),
                            'end_date' => $initiative->end_date->format('Y-m-d'),
                            'status' => $initiative->status,
                            'funding_metrics' => [
                                'total_contributions' => $totalInitiativeContributions,
                                'contributor_count' => $initiative->investments->count(),
                                'funding_progress' => $fundingProgress . '%',
                                'remaining_amount' => max(0, $initiative->target_amount - $totalInitiativeContributions)
                            ]
                        ]
                    ];
                });

            $totalContributions = $contributions->sum('contribution_amount');
            $totalInitiatives = $contributions->pluck('funding_initiative.initiative_id')->unique()->count();

            return response()->json([
                'status' => 'success',
                'message' => 'Investor dashboard data retrieved successfully',
                'data' => [
                    'total_contributions' => $totalContributions,
                    'total_initiatives' => $totalInitiatives,
                    'contributions' => $contributions
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Investor dashboard error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve investor dashboard data',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function investorCampaignReport(Request $request, $campaignId)
    {
        $investment = Investment::where('investor_id', $request->user()->id)
            ->where('campaign_id', $campaignId)
            ->with(['campaign', 'campaign.farmer'])
            ->firstOrFail();

        $campaign = $investment->campaign;
        $totalInvestments = $campaign->investments()->sum('amount');
        $totalInvestors = $campaign->investors()->count();

        return response()->json([
            'investment' => $investment,
            'campaign' => $campaign,
            'total_investments' => $totalInvestments,
            'total_investors' => $totalInvestors,
        ]);
    }
} 