<?php

namespace App\Http\Controllers;

use App\Models\JourneyCollection;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JourneyCollectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:editor,institution,administrator')->except(['index', 'show']);
    }

    /**
     * Display a listing of journey collections.
     */
    public function index()
    {
        $user = Auth::user();
        $query = JourneyCollection::with(['institution', 'editor']);

        if ($user->role === 'regular') {
            // Regular learners should only see active collections scoped to their institution or global ones
            $regularQuery = clone $query;
            $regularQuery->active()->where(function ($builder) use ($user) {
                $builder->whereNull('institution_id');

                if ($user->institution_id) {
                    $builder->orWhere('institution_id', $user->institution_id);
                }
            });

            $collections = $regularQuery->paginate(12);
        } elseif ($user->role === 'editor') {
            // Editors see their own collections
            $collections = $query->where('editor_id', $user->id)->paginate(12);
        } elseif ($user->role === 'institution') {
            // Institution users see their institution's collections
            $collections = $query->where('institution_id', $user->institution_id)->paginate(12);
        } else {
            // Administrators see all collections
            $collections = $query->paginate(12);
        }

        return view('collections.index', compact('collections'));
    }

    /**
     * Show the form for creating a new collection.
     */
    public function create()
    {
        $user = Auth::user();
        
        if ($user->role === 'administrator') {
            $institutions = Institution::all();
            $editors = \App\Models\User::where('role', 'editor')->get();
        } elseif ($user->role === 'institution') {
            $institutions = Institution::where('id', $user->institution_id)->get();
            $editors = \App\Models\User::where('role', 'editor')
                ->where('institution_id', $user->institution_id)->get();
        } else {
            $institutions = Institution::where('id', $user->institution_id)->get();
            $editors = collect([$user]);
        }

        return view('collections.create', compact('institutions', 'editors'));
    }

    /**
     * Store a newly created collection in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'institution_id' => 'required|exists:institutions,id',
            'editor_id' => 'required|exists:users,id',
            'is_active' => 'boolean',
        ]);

        // Validate permissions
        if ($user->role === 'editor' && $request->editor_id != $user->id) {
            abort(403, 'You can only create collections for yourself.');
        }

        if ($user->role === 'institution' && $request->institution_id != $user->institution_id) {
            abort(403, 'You can only create collections for your institution.');
        }

        $collection = JourneyCollection::create([
            'name' => $request->name,
            'description' => $request->description,
            'institution_id' => $request->institution_id,
            'editor_id' => $request->editor_id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('collections.show', $collection)
            ->with('success', 'Journey collection created successfully!');
    }

    /**
     * Display the specified collection.
     */
    public function show(JourneyCollection $collection)
    {
        $this->authorize('view', $collection);

        $collection->load(['institution', 'editor', 'journeys' => function($query) {
            $query->where('is_published', true)->with('creator');
        }]);

        return view('collections.show', compact('collection'));
    }

    /**
     * Show the form for editing the specified collection.
     */
    public function edit(JourneyCollection $collection)
    {
        $this->authorize('update', $collection);

        $user = Auth::user();
        
        if ($user->role === 'administrator') {
            $institutions = Institution::all();
            $editors = \App\Models\User::where('role', 'editor')->get();
        } elseif ($user->role === 'institution') {
            $institutions = Institution::where('id', $user->institution_id)->get();
            $editors = \App\Models\User::where('role', 'editor')
                ->where('institution_id', $user->institution_id)->get();
        } else {
            $institutions = Institution::where('id', $user->institution_id)->get();
            $editors = collect([$user]);
        }

        return view('collections.edit', compact('collection', 'institutions', 'editors'));
    }

    /**
     * Update the specified collection in storage.
     */
    public function update(Request $request, JourneyCollection $collection)
    {
        $this->authorize('update', $collection);

        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'institution_id' => 'required|exists:institutions,id',
            'editor_id' => 'required|exists:users,id',
            'is_active' => 'boolean',
        ]);

        // Validate permissions
        if ($user->role === 'editor' && $request->editor_id != $user->id) {
            abort(403, 'You can only manage your own collections.');
        }

        if ($user->role === 'institution' && $request->institution_id != $user->institution_id) {
            abort(403, 'You can only manage collections for your institution.');
        }

        $collection->update([
            'name' => $request->name,
            'description' => $request->description,
            'institution_id' => $request->institution_id,
            'editor_id' => $request->editor_id,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('collections.show', $collection)
            ->with('success', 'Journey collection updated successfully!');
    }

    /**
     * Remove the specified collection from storage.
     */
    public function destroy(JourneyCollection $collection)
    {
        $this->authorize('delete', $collection);

        // Check if collection has journeys
        if ($collection->journeys()->count() > 0) {
            return redirect()->route('collections.index')
                ->with('error', 'Cannot delete collection with existing journeys.');
        }

        $collection->delete();

        return redirect()->route('collections.index')
            ->with('success', 'Journey collection deleted successfully!');
    }
}
