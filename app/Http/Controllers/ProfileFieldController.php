<?php

namespace App\Http\Controllers;

use App\Models\ProfileField;
use Illuminate\Http\Request;

class ProfileFieldController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $profileFields = ProfileField::orderBy('sort_order')->get();
        return view('profile-fields.index', compact('profileFields'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $inputTypes = ProfileField::getInputTypes();
        return view('profile-fields.create', compact('inputTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:100|unique:profile_fields,short_name|regex:/^[a-z_]+$/',
            'input_type' => 'required|in:text,number,textarea,select,select_multiple',
            'options' => 'nullable|string',
            'required' => 'boolean',
            'description' => 'nullable|string'
        ]);

        $data = $request->all();
        
        // Handle options for select fields
        if (in_array($data['input_type'], ['select', 'select_multiple'])) {
            $options = array_filter(array_map('trim', explode("\n", $request->options ?? '')));
            $data['options'] = $options;
        } else {
            $data['options'] = null;
        }

        // Set sort order to the end
        $data['sort_order'] = ProfileField::max('sort_order') + 1;

        ProfileField::create($data);

        return redirect()->route('profile-fields.index')
            ->with('success', 'Profile field created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProfileField $profileField)
    {
        $inputTypes = ProfileField::getInputTypes();
        return view('profile-fields.edit', compact('profileField', 'inputTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProfileField $profileField)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:100|regex:/^[a-z_]+$/|unique:profile_fields,short_name,' . $profileField->id,
            'input_type' => 'required|in:text,number,textarea,select,select_multiple',
            'options' => 'nullable|string',
            'required' => 'boolean',
            'description' => 'nullable|string'
        ]);

        $data = $request->all();
        
        // Handle options for select fields
        if (in_array($data['input_type'], ['select', 'select_multiple'])) {
            $options = array_filter(array_map('trim', explode("\n", $request->options ?? '')));
            $data['options'] = $options;
        } else {
            $data['options'] = null;
        }

        $profileField->update($data);

        return redirect()->route('profile-fields.index')
            ->with('success', 'Profile field updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProfileField $profileField)
    {
        $profileField->delete();

        return redirect()->route('profile-fields.index')
            ->with('success', 'Profile field deleted successfully.');
    }

    /**
     * API: Return all active profile fields for preview-chat.
     */
    public function apiIndex()
    {
        return ProfileField::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'short_name', 'input_type', 'options', 'required', 'description']);
    }

    /**
     * API: Return all profile fields (for preview-chat, no auth).
     */
    public function apiAll()
    {
        return ProfileField::orderBy('sort_order')->get(['id', 'name', 'short_name', 'input_type', 'options', 'required', 'description', 'is_active']);
    }
}
