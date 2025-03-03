<?php

namespace App\Http\Controllers\Api;

use App\Filters\ProjectFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @param ProjectFilter $filter
     * @return AnonymousResourceCollection
     */
    public function index(Request $request, ProjectFilter $filter): AnonymousResourceCollection
    {
        try {
            Log::info('Project filter request', [
                'filters' => $request->get('filters', []),
                'raw_request' => $request->all()
            ]);

            // Get the attribute IDs for start_date and end_date
            $startDateAttr = \App\Models\Attribute::where('name', 'Start Date')->first();
            $endDateAttr = \App\Models\Attribute::where('name', 'End Date')->first();
            
            Log::info('Date attributes found:', [
                'start_date_attr' => $startDateAttr ? $startDateAttr->toArray() : null,
                'end_date_attr' => $endDateAttr ? $endDateAttr->toArray() : null
            ]);

            $query = Project::query()
                ->filter($filter)
                ->with('attributeValues.attribute');

            // Log the SQL query before execution
            Log::info('SQL Query', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            $perPage = $request->input('per_page', 10);
            $projects = $query->latest()->paginate($perPage);

            // Log pagination info
            Log::info('Projects pagination', [
                'total' => $projects->total(),
                'per_page' => $projects->perPage(),
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage()
            ]);

            return ProjectResource::collection($projects);
        } catch (Throwable $e) {
            Log::error('Error fetching projects', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ProjectRequest $request
     * @return JsonResponse
     */
    public function store(ProjectRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $project = Project::create($request->safe()->except('attributes'));

            // Automatically assign the creating user to the project
            $project->users()->attach(Auth::id());

            Log::info('Project created:', [
                'project_id' => $project->id,
                'assigned_user_id' => Auth::id()
            ]);

            if ($request->has('attributes')) {
                $attributes = $request->json('attributes', []);
                
                Log::info('Processing attributes:', [
                    'attributes' => $attributes,
                    'count' => count($attributes)
                ]);

                if (!empty($attributes)) {
                    foreach ($attributes as $attribute) {
                        $data = [
                            'attribute_id' => $attribute['attribute_id'],
                            'entity_type' => Project::class,
                            'entity_id' => $project->id,
                            'value' => $attribute['value']
                        ];

                        Log::info('Creating attribute value:', [
                            'attribute_data' => $data
                        ]);

                        $attributeValue = \App\Models\AttributeValue::create($data);

                        Log::info('Attribute value created:', [
                            'attribute_value_id' => $attributeValue->id ?? null,
                            'attribute_value' => $attributeValue->toArray()
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('Project created successfully with ID: ' . $project->id);

            // Fresh load to ensure we get the latest data
            $loadedProject = $project->fresh(['attributeValues.attribute', 'users']);
            
            Log::info('Loaded project data:', [
                'attribute_values_count' => $loadedProject->attributeValues->count(),
                'attribute_values' => $loadedProject->attributeValues->toArray(),
                'users' => $loadedProject->users->pluck('id')->toArray()
            ]);

            return response()->json(
                new ProjectResource($loadedProject),
                201
            );
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error creating project', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'validated_data' => $request->validated()
            ]);

            throw $e;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param Project $project
     * @return JsonResponse
     */
    public function show(Project $project): JsonResponse
    {
        try {
            return response()->json(
                new ProjectResource($project->load('attributeValues.attribute'))
            );
        } catch (Throwable $e) {
            Log::error('Error fetching project', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $project->id
            ]);

            throw $e;
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ProjectRequest $request
     * @param Project $project
     * @return JsonResponse
     */
    public function update(ProjectRequest $request, Project $project): JsonResponse
    {
        try {
            DB::beginTransaction();

            Log::info('Updating project with data:', [
                'project_id' => $project->id,
                'project_data' => $request->safe()->except('attributes'),
                'has_attributes' => $request->has('attributes'),
                'attributes' => $request->input('attributes')
            ]);

            $project->update($request->safe()->except('attributes'));

            if ($request->has('attributes')) {
                $attributes = $request->json('attributes', []);
                
                Log::info('Processing attributes for update:', [
                    'attributes' => $attributes,
                    'count' => count($attributes)
                ]);

                if (!empty($attributes)) {
                    // Store new attribute values before deleting old ones
                    $newAttributeValues = [];
                    
                    foreach ($attributes as $attribute) {
                        $data = [
                            'attribute_id' => $attribute['attribute_id'],
                            'entity_type' => Project::class,
                            'entity_id' => $project->id,
                            'value' => $attribute['value']
                        ];

                        Log::info('Preparing attribute value:', [
                            'attribute_data' => $data
                        ]);

                        $newAttributeValues[] = $data;
                    }

                    if (!empty($newAttributeValues)) {
                        // Only delete old values if we have valid new ones to insert
                        Log::info('Deleting existing attribute values for project:', [
                            'project_id' => $project->id
                        ]);
                        
                        $project->attributeValues()->delete();

                        // Create new attribute values
                        foreach ($newAttributeValues as $data) {
                            $attributeValue = \App\Models\AttributeValue::create($data);

                            Log::info('Attribute value created:', [
                                'attribute_value_id' => $attributeValue->id ?? null,
                                'attribute_value' => $attributeValue->toArray()
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            Log::info('Project updated successfully with ID: ' . $project->id);

            // Fresh load to ensure we get the latest data
            $loadedProject = $project->fresh(['attributeValues.attribute']);
            
            Log::info('Loaded project data:', [
                'attribute_values_count' => $loadedProject->attributeValues->count(),
                'attribute_values' => $loadedProject->attributeValues->toArray()
            ]);

            return response()->json(
                new ProjectResource($loadedProject)
            );
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error updating project', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $project->id,
                'request_data' => $request->all(),
                'validated_data' => $request->validated()
            ]);

            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Project $project
     * @return JsonResponse
     */
    public function destroy(Project $project): JsonResponse
    {
        try {
            DB::beginTransaction();

            $project->attributeValues()->delete();
            $project->delete();

            DB::commit();

            Log::info('Project deleted successfully..');

            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error deleting project', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $project->id
            ]);

            throw $e;
        }
    }
}
