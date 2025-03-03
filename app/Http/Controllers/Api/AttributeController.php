<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $attributes = Attribute::all();
        return response()->json($attributes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:attributes,name|max:255',
            'type' => ['required', Rule::in(Attribute::TYPES)],
            'options' => 'required_if:type,select|array|nullable',
        ]);

        $attribute = Attribute::create($validated);
        return response()->json($attribute, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Attribute $attribute): JsonResponse
    {
        return response()->json($attribute);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Attribute $attribute): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', Rule::unique('attributes')->ignore($attribute), 'max:255'],
            'type' => ['required', Rule::in(Attribute::TYPES)],
            'options' => 'required_if:type,select|array|nullable',
        ]);

        $attribute->update($validated);
        return response()->json($attribute);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attribute $attribute): JsonResponse
    {
        $attribute->delete();
        return response()->json(null, 204);
    }
}
