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
                return response()->api(null, true, 'No seekers found', 200);
            }

            // Transform seekers for frontend
            $seekersTransformed = $seekers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name ?? null,
                    'email' => $user->email ?? null,
                    'profile' => [
                        'phone'   => $user->profile->phone ?? null,
                        'city'    => $user->profile->city ?? null,
                        'country' => $user->profile->country ?? null,
                        'dob'     => $user->profile->dob ?? null,
                        'gender'  => $user->profile->gender ?? null,
                    ],
                    'desired_titles' => $user->jobSeeker->desiredTitles->map(function ($title) {
                        return [
                            'id'       => $title->id,
                            'title'    => $title->title ?? null,
                            'priority' => $title->priority ?? null,
                        ];
                    }),
                ];
            });

            $data = [
                'seekers' => $seekersTransformed,
                'pagination' => [
                    'current_page' => $seekers->currentPage(),
                    'last_page'    => $seekers->lastPage(),
                    'per_page'     => $seekers->perPage(),
                    'total'        => $seekers->total(),
                ],
            ];

            return response()->api($data); // ✅ success response
        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500); // ✅ error response
        }
    }

}
