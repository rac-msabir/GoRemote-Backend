<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\JobSeeker;
use App\Models\User;
use App\Models\SeekerDesiredTitle;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'city' => ['nullable','string','max:120'],
            'state_province' => ['nullable','string','max:120'],
            'postal_code' => ['nullable','string','max:20'],
            'country_code' => ['nullable','string','size:2'],
            'remote_preference' => ['boolean'],
        ]);
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        $seeker->fill($validated);
        $seeker->save();
        return response()->json($seeker);
    }

    public function updateMinPay(Request $request)
    {
        $validated = $request->validate([
            'min_base_pay' => ['nullable','numeric'],
            'min_pay_period' => ['nullable','in:hour,day,week,month,year'],
        ]);
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        $seeker->fill($validated);
        $seeker->save();
        return response()->json($seeker);
    }

    public function updateDesiredTitles(Request $request)
    {
        $validated = $request->validate([
            'titles' => ['array','max:10'],
            'titles.*.title' => ['required','string','max:120'],
            'titles.*.priority' => ['nullable','integer','min:0','max:9'],
        ]);
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        // replace existing titles
        $seeker->desiredTitles()->delete();
        foreach ($validated['titles'] ?? [] as $item) {
            $seeker->desiredTitles()->create([
                'title' => $item['title'],
                'priority' => $item['priority'] ?? 0,
            ]);
        }
        return response()->json($seeker->load('desiredTitles'));
    }

    public function findSeeker(Request $request)
    {
       $seekers = User::with([
        'profile',
        'jobSeeker.desiredTitles' => function ($q) {
            $q->orderBy('priority', 'asc');
        }
        ])
        ->where('role', 'seeker')
        ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $seekers
        ]);
    }
}


