<?php

namespace App\Http\Controllers;

use App\Models\ProfileField;
use App\Models\UserProfileValue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show()
    {
        $user = Auth::user();
        $profileFields = ProfileField::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('profile.show', compact('user', 'profileFields'));
    }

    public function edit()
    {
        $user = Auth::user();
        $profileFields = ProfileField::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('profile.edit', compact('user', 'profileFields'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $profileFields = ProfileField::where('is_active', true)->get();

        // Validate profile fields
        $rules = [];
        foreach ($profileFields as $field) {
            $key = 'profile_' . $field->short_name;
            
            $fieldRules = [];
            if ($field->required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field->input_type) {
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'select_multiple':
                    $fieldRules[] = 'array';
                    break;
            }

            $rules[$key] = implode('|', $fieldRules);
        }

        $request->validate($rules);

        // Save profile field values
        foreach ($profileFields as $field) {
            $key = 'profile_' . $field->short_name;
            $value = $request->input($key);
            
            if ($value !== null) {
                $user->setProfileValue($field->short_name, $value);
            }
        }

        return redirect()->route('profile.show')
            ->with('success', 'Profile updated successfully.');
    }

    public function passwordEdit()
    {
        return view('profile.password');
    }

    public function passwordUpdate(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        Auth::user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('profile.show')
            ->with('success', 'Password updated successfully.');
    }
}
