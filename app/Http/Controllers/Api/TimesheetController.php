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
            Log::info('Timesheet filter request', [
                'filters' => $request->get('filters', []),
                'raw_request' => $request->all()
            ]);

            $query = Timesheet::query()
                ->filter($filter)
                ->with(['project', 'user']);

            // Log the SQL query before execution
            Log::info('SQL Query', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            $perPage = $request->input('per_page', 10);
            $timesheets = $query->latest()->paginate($perPage);

            // Log pagination info
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
     * @param Timesheet $timesheet
     * @return JsonResponse
     */
    public function show(Timesheet $timesheet): JsonResponse
    {
        try {
            $this->authorize('view', $timesheet);

            return response()->json(
                new TimesheetResource($timesheet)
            );
        } catch (Throwable $e) {
            Log::error('Error fetching timesheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timesheet_id' => $timesheet->id
            ]);

            throw $e;
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param TimesheetRequest $request
     * @param Timesheet $timesheet
     * @return JsonResponse
     */
    public function update(TimesheetRequest $request, Timesheet $timesheet): JsonResponse
    {
        try {
            $this->authorize('update', $timesheet);

            DB::beginTransaction();

            $timesheet->update($request->validated());

            DB::commit();

            Log::info('Timesheet updated successfully with ID: ' . $timesheet->id);

            return response()->json(
                new TimesheetResource($timesheet)
            );
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error updating timesheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timesheet_id' => $timesheet->id,
                'data' => $request->validated()
            ]);

            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Timesheet $timesheet
     * @return JsonResponse
     */
    public function destroy(Timesheet $timesheet): JsonResponse
    {
        try {
            $this->authorize('delete', $timesheet);

            DB::beginTransaction();

            $timesheet->delete();

            DB::commit();

            Log::info('Timesheet deleted successfully with ID: ' . $timesheet->id);

            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error deleting timesheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timesheet_id' => $timesheet->id
            ]);

            throw $e;
        }
    }
}
