<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobSeeker;
use App\Models\User;
use App\Models\SeekerDesiredTitle;

class UserController extends Controller
{
   public function findSeeker(Request $request)
    {
        try {
            $seekers = User::with([
                'profile',
                'jobSeeker.desiredTitles' => function ($q) {
                    $q->orderBy('priority', 'asc');
                }
            ])
            ->where('role', 'seeker')
            ->paginate(10);

            if ($seekers->isEmpty()) {
                return response()->json([
                    'status_code' => 200,
                    'error' => true,
                    'errorMessage' => 'No seekers found',
                    'data' => null,
                ]);
            }

            // Transform seekers for frontend
            $seekersTransformed = $seekers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name ?? null,
                    'email' => $user->email ?? null,
                    'profile' => [
                        'phone' => $user->profile->phone ?? null,
                        'city' => $user->profile->city ?? null,
                        'country' => $user->profile->country ?? null,
                        'dob' => $user->profile->dob ?? null,
                        'gender' => $user->profile->gender ?? null,
                    ],
                    'desired_titles' => $user->jobSeeker->desiredTitles->map(function ($title) {
                        return [
                            'id' => $title->id,
                            'title' => $title->title ?? null,
                            'priority' => $title->priority ?? null,
                        ];
                    }),
                ];
            });

            return response()->json([
                'status_code' => 200,
                'error' => false,
                'errorMessage' => null,
                'data' => [
                    'seekers' => $seekersTransformed,
                    'pagination' => [
                        'current_page' => $seekers->currentPage(),
                        'last_page' => $seekers->lastPage(),
                        'per_page' => $seekers->perPage(),
                        'total' => $seekers->total(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'error' => true,
                'errorMessage' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }

}
