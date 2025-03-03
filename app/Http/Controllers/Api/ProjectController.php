<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Project::query();

        // Only show projects the user is assigned to
        $query->whereHas('users', function (Builder $q) use ($request) {
            $q->where('users.id', $request->user()->id);
        });

        // Handle dynamic attribute filters
        $attributes = Attribute::all();
        foreach ($attributes as $attribute) {
            if ($request->has($attribute->name)) {
                $value = $request->input($attribute->name);
                $query->whereHas('attributeValues', function (Builder $q) use ($attribute, $value) {
                    $q->where('attribute_id', $attribute->id)
                        ->where('value', 'like', "%{$value}%");
                });
            }
        }

        $projects = $query->with(['attributeValues.attribute'])->get();
        return response()->json($projects);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|string|max:255',
        ]);

        $project = Project::create($validated);
        $project->users()->attach($request->user()->id);

        // Handle dynamic attributes
        $attributes = Attribute::all();
        foreach ($attributes as $attribute) {
            if ($request->has($attribute->name)) {
                $project->attributeValues()->create([
                    'attribute_id' => $attribute->id,
                    'value' => $request->input($attribute->name),
                ]);
            }
        }

        return response()->json($project->load('attributeValues.attribute'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        return response()->json($project->load('attributeValues.attribute'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|string|max:255',
        ]);

        $project->update($validated);

        // Handle dynamic attributes
        $attributes = Attribute::all();
        foreach ($attributes as $attribute) {
            if ($request->has($attribute->name)) {
                $project->attributeValues()->updateOrCreate(
                    [
                        'attribute_id' => $attribute->id,
                    ],
                    [
                        'value' => $request->input($attribute->name),
                    ]
                );
            }
        }

        return response()->json($project->load('attributeValues.attribute'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        
        $project->delete();
        return response()->json(null, 204);
    }
}
