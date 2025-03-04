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
use App\Http\Middleware\CacheResponse;

class AttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AttributeFilter $filter): JsonResponse
    {
        try {
            $query = Attribute::query()
                ->filter($filter)
                ->orderBy('created_at', 'desc')  // Ensure consistent sorting
                ->orderBy('id', 'desc');         // Secondary sort for items created at the same time

            // Log the SQL query before execution
            Log::info('Attribute filter query', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'filters' => $request->get('filters', [])
            ]);

            $perPage = $request->input('per_page', 10); // Default 10 items per page
            $attributes = $query->paginate($perPage);

            Log::info('Attributes pagination', [
                'total' => $attributes->total(),
                'per_page' => $attributes->perPage(),
                'current_page' => $attributes->currentPage(),
                'last_page' => $attributes->lastPage()
            ]);

            return response()->json(
                AttributeResource::collection($attributes)
                    ->response()
                    ->getData(true) // This includes pagination metadata
            );
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

            // Clear cache for attributes list
            CacheResponse::clearCache(request()->create(route('attributes.index'), 'GET'));

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

            // Clear cache for both list and single attribute
            CacheResponse::clearCache(request()->create(route('attributes.index'), 'GET'));
            CacheResponse::clearCache(request()->create(route('attributes.show', $attribute), 'GET'));

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

            // Clear cache for both list and single attribute
            CacheResponse::clearCache(request()->create(route('attributes.index'), 'GET'));
            CacheResponse::clearCache(request()->create(route('attributes.show', $attribute), 'GET'));

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
