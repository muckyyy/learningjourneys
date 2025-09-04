<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JourneyAttempt;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get active journey attempt (only one allowed at a time, excluding preview journeys)
        $activeAttempt = JourneyAttempt::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->where(function($query) {
                $query->where('journey_type', '!=', 'preview')
                      ->orWhereNull('journey_type');
            })
            ->with(['journey'])
            ->first();

        return view('home', compact('activeAttempt'));
    }
}
