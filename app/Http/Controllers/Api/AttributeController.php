<?php

namespace App\Http\Controllers\Api;

use App\Filters\AttributeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttributeRequest;
use App\Http\Resources\AttributeResource;
use App\Models\Attribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class AttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AttributeFilter $filter): JsonResponse
    {
        try {
            Log::info('Attribute filter request', [
                'filters' => $request->get('filters', []),
                'raw_request' => $request->all()
            ]);

            $query = Attribute::query()->filter($filter);

            // Log the SQL query before execution
            Log::info('SQL Query', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            $perPage = $request->input('per_page', 10);
            $attributes = $query->latest()->paginate($perPage);

            // Log pagination info
            Log::info('Attributes pagination', [
                'total' => $attributes->total(),
                'per_page' => $attributes->perPage(),
                'current_page' => $attributes->currentPage(),
                'last_page' => $attributes->lastPage()
            ]);

            return response()->json(AttributeResource::collection($attributes));
        } catch (Throwable $e) {
            Log::error('Error fetching attributes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $attribute = Attribute::create($request->validated());

            DB::commit();

            Log::info('Attribute created successfully..');

            return response()->json(
                new AttributeResource($attribute),
                201
            );
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error creating attribute', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);

            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Attribute $attribute): JsonResponse
    {
        try {
            return response()->json(
                new AttributeResource($attribute)
            );
        } catch (Throwable $e) {
            Log::error('Error fetching attribute', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attribute_id' => $attribute->id
            ]);

            throw $e;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeRequest $request, Attribute $attribute): JsonResponse
    {
        try {
            DB::beginTransaction();

            $attribute->update($request->validated());

            DB::commit();

            Log::info('Attribute updated successfully..');

            return response()->json(
                new AttributeResource($attribute)
            );
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error updating attribute', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attribute_id' => $attribute->id,
                'data' => $request->validated()
            ]);

            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attribute $attribute): JsonResponse
    {
        try {
            DB::beginTransaction();

            $attribute->delete();

            DB::commit();

            Log::info('Attribute deleted successfully..');

            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error deleting attribute', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attribute_id' => $attribute->id
            ]);

            throw $e;
        }
    }
}
