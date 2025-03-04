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
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
    public function show($id): JsonResponse
    {
        try {
            $attribute = Attribute::findOrFail($id);
            return response()->json(new AttributeResource($attribute));
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Attribute with ID {$id} not found",
                'error_code' => 'ATTRIBUTE_NOT_FOUND'
            ], 404);
        } catch (Throwable $e) {
            Log::error('Error fetching attribute', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attribute_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch attribute',
                'error_code' => 'FETCH_FAILED',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $attribute = Attribute::findOrFail($id);
            $data = $request->validated();

            // Handle options field
            if (isset($data['type'])) {
                if ($data['type'] === 'select') {
                    // Ensure options is an array
                    $data['options'] = isset($data['options']) ? (array) $data['options'] : [];
                } else {
                    // For non-select types, set options to null
                    $data['options'] = null;
                }
            }

            $attribute->fill($data);
            $attribute->save();

            DB::commit();

            Log::info('Attribute updated successfully', [
                'attribute_id' => $id,
                'updated_fields' => array_keys($data),
                'data' => $data
            ]);

            // Clear cache for both list and single attribute
            CacheResponse::clearCache(request()->create(route('attributes.index'), 'GET'));
            CacheResponse::clearCache(request()->create(route('attributes.show', $id), 'GET'));

            return response()->json(new AttributeResource($attribute));
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => "Attribute with ID {$id} not found",
                'error_code' => 'ATTRIBUTE_NOT_FOUND'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error updating attribute', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attribute_id' => $id,
                'request_data' => $request->all(),
                'validated_data' => $request->validated()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update attribute',
                'error_code' => 'UPDATE_FAILED',
                'details' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $attribute = Attribute::findOrFail($id);
            $attribute->delete();

            DB::commit();

            Log::info('Attribute deleted successfully', [
                'attribute_id' => $id
            ]);

            // Clear cache for both list and single attribute
            CacheResponse::clearCache(request()->create(route('attributes.index'), 'GET'));
            CacheResponse::clearCache(request()->create(route('attributes.show', $id), 'GET'));

            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => "Attribute with ID {$id} not found",
                'error_code' => 'ATTRIBUTE_NOT_FOUND'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error deleting attribute', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attribute_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete attribute',
                'error_code' => 'DELETE_FAILED',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
