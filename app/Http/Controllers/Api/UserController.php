<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Helper\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Middleware\CacheResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = User::query()
                ->orderBy('created_at', 'desc')  // Ensure consistent sorting
                ->orderBy('id', 'desc');         // Secondary sort for items created at the same time

            $perPage = $request->input('per_page', 10); // Default 10 items per page
            $users = $query->paginate($perPage);

            Log::info('Users pagination', [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage()
            ]);

            return UserResource::collection($users);
        } catch (Throwable $e) {
            Log::error('Error fetching users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param UserRequest $request
     * @return JsonResponse
     */
    public function store(UserRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            DB::commit();

            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            ]);

            // Clear cache for users list
            CacheResponse::clearCache(request()->create(route('users.index'), 'GET'));

            return response()->json(
                new UserResource($user),
                201
            );
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error creating user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->safe()->except(['password'])
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
            $user = User::findOrFail($id);
            return response()->json(new UserResource($user));
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException("User with ID {$id} not found");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UserRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UserRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);
            $data = $request->validated();

            // Remove password field if it's empty
            if (empty($data['password'])) {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make($data['password']);
            }

            $user->fill($data);
            $user->save();

            DB::commit();

            Log::info('User updated successfully', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($data)
            ]);

            // Clear cache for both list and single user
            CacheResponse::clearCache(request()->create(route('users.index'), 'GET'));
            CacheResponse::clearCache(request()->create(route('users.show', $user), 'GET'));

            return response()->json(new UserResource($user));

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => "User with ID {$id} not found",
                'error_code' => 'USER_NOT_FOUND'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error updating user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user',
                'error_code' => 'UPDATE_FAILED',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);
            $user->delete();

            DB::commit();

            Log::info('User deleted successfully', [
                'user_id' => $id
            ]);

            // Clear cache for both list and single user
            CacheResponse::clearCache(request()->create(route('users.index'), 'GET'));
            CacheResponse::clearCache(request()->create(route('users.show', $id), 'GET'));

            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => "User with ID {$id} not found",
                'error_code' => 'USER_NOT_FOUND'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error deleting user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete user',
                'error_code' => 'DELETE_FAILED',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
} 