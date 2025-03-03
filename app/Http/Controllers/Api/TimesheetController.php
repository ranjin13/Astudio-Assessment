<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $timesheets = $request->user()->timesheets()
            ->with(['project'])
            ->latest()
            ->get();

        return response()->json($timesheets);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_name' => 'required|string|max:255',
            'date' => 'required|date',
            'hours' => 'required|numeric|min:0|max:24',
            'project_id' => 'required|exists:projects,id',
        ]);

        $timesheet = $request->user()->timesheets()->create($validated);

        return response()->json($timesheet->load('project'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Timesheet $timesheet): JsonResponse
    {
        $this->authorize('view', $timesheet);
        return response()->json($timesheet->load('project'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Timesheet $timesheet): JsonResponse
    {
        $this->authorize('update', $timesheet);

        $validated = $request->validate([
            'task_name' => 'required|string|max:255',
            'date' => 'required|date',
            'hours' => 'required|numeric|min:0|max:24',
            'project_id' => 'required|exists:projects,id',
        ]);

        $timesheet->update($validated);

        return response()->json($timesheet->load('project'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Timesheet $timesheet): JsonResponse
    {
        $this->authorize('delete', $timesheet);
        
        $timesheet->delete();
        return response()->json(null, 204);
    }
}
