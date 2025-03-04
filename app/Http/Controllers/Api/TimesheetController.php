<?php

namespace App\Http\Controllers\Api;

use App\Filters\TimesheetFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimesheetRequest;
use App\Http\Resources\TimesheetResource;
use App\Models\Timesheet;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Http\Middleware\CacheResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;

class TimesheetController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @param TimesheetFilter $filter
     * @return AnonymousResourceCollection
     */
    public function index(Request $request, TimesheetFilter $filter): AnonymousResourceCollection
    {
        try {
            $query = Timesheet::query()
                ->filter($filter)
                ->with(['user', 'project'])
                ->orderBy('date', 'desc')         // Primary sort by timesheet date
                ->orderBy('created_at', 'desc')   // Secondary sort by creation date
                ->orderBy('id', 'desc');          // Final sort for items created at the same time

            // Log the SQL query before execution
            Log::info('Timesheet filter query', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'filters' => $request->get('filters', [])
            ]);

            $perPage = $request->input('per_page', 10); // Default 10 items per page
            $timesheets = $query->paginate($perPage);

            Log::info('Timesheets pagination', [
                'total' => $timesheets->total(),
                'per_page' => $timesheets->perPage(),
                'current_page' => $timesheets->currentPage(),
                'last_page' => $timesheets->lastPage()
            ]);

            return TimesheetResource::collection($timesheets);
        } catch (Throwable $e) {
            Log::error('Error fetching timesheets', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param TimesheetRequest $request
     * @return JsonResponse
     */
    public function store(TimesheetRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $timesheet = Timesheet::create($request->validated());

            DB::commit();

            Log::info('Timesheet created successfully with ID: ' . $timesheet->id);

            // Clear cache for timesheets list
            CacheResponse::clearCache(request()->create(route('timesheets.index'), 'GET'));

            return response()->json(
                new TimesheetResource($timesheet),
                201
            );
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error creating timesheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);

            throw $e;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $timesheet = Timesheet::findOrFail($id);
            $this->authorize('view', $timesheet);

            return response()->json(
                new TimesheetResource($timesheet)
            );
        } catch (ModelNotFoundException $e) {
            Log::error('Timesheet not found', [
                'timesheet_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => "Timesheet with ID {$id} not found",
                'error_code' => 'TIMESHEET_NOT_FOUND'
            ], 404);
        } catch (AuthorizationException $e) {
            Log::error('Unauthorized timesheet access attempt', [
                'timesheet_id' => $id,
                'user_id' => request()->user()->id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. You can only view your own timesheets.',
                'error_code' => 'TIMESHEET_ACCESS_DENIED'
            ], 403);
        } catch (Throwable $e) {
            Log::error('Error fetching timesheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timesheet_id' => $id
            ]);

            throw $e;
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param TimesheetRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(TimesheetRequest $request, int $id): JsonResponse
    {
        try {
            $timesheet = Timesheet::findOrFail($id);
            $this->authorize('update', $timesheet);

            DB::beginTransaction();

            $timesheet->update($request->validated());

            DB::commit();

            Log::info('Timesheet updated successfully with ID: ' . $timesheet->id);

            // Clear cache for both list and single timesheet
            CacheResponse::clearCache(request()->create(route('timesheets.index'), 'GET'));
            CacheResponse::clearCache(request()->create(route('timesheets.show', $timesheet), 'GET'));

            return response()->json(
                new TimesheetResource($timesheet)
            );
        } catch (ModelNotFoundException $e) {
            Log::error('Timesheet not found', [
                'timesheet_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => "Timesheet with ID {$id} not found",
                'error_code' => 'TIMESHEET_NOT_FOUND'
            ], 404);
        } catch (AuthorizationException $e) {
            DB::rollBack();
            Log::error('Unauthorized timesheet update attempt', [
                'timesheet_id' => $id,
                'user_id' => request()->user()->id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. You can only update your own timesheets.',
                'error_code' => 'TIMESHEET_UPDATE_DENIED'
            ], 403);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error updating timesheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timesheet_id' => $id,
                'data' => $request->validated()
            ]);

            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $timesheet = Timesheet::findOrFail($id);
            $this->authorize('delete', $timesheet);

            DB::beginTransaction();

            $timesheet->delete();

            DB::commit();

            Log::info('Timesheet deleted successfully with ID: ' . $timesheet->id);

            // Clear cache for both list and single timesheet
            CacheResponse::clearCache(request()->create(route('timesheets.index'), 'GET'));
            CacheResponse::clearCache(request()->create(route('timesheets.show', $timesheet), 'GET'));

            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            Log::error('Timesheet not found', [
                'timesheet_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => "Timesheet with ID {$id} not found",
                'error_code' => 'TIMESHEET_NOT_FOUND'
            ], 404);
        } catch (AuthorizationException $e) {
            DB::rollBack();
            Log::error('Unauthorized timesheet deletion attempt', [
                'timesheet_id' => $id,
                'user_id' => request()->user()->id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. You can only delete your own timesheets.',
                'error_code' => 'TIMESHEET_DELETE_DENIED'
            ], 403);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error deleting timesheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timesheet_id' => $id
            ]);

            throw $e;
        }
    }
}
