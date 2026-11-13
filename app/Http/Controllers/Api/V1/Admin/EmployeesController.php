<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeesController extends Controller
{
    public function __construct(protected EmployeeService $employees)
    {
    }

    /**
     * GET /api/v1/admin/employees
     */
    public function index(): AnonymousResourceCollection
    {
        return EmployeeResource::collection(
            User::with('team')
                ->where('role', 'employee')
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * POST /api/v1/admin/employees
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employees->create($request->validated());

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/admin/employees/{employee}
     */
    public function show(User $employee): JsonResource
    {
        return new EmployeeResource($employee->load('team'));
    }

    /**
     * PUT /api/v1/admin/employees/{employee}
     */
    public function update(UpdateEmployeeRequest $request, User $employee): JsonResource
    {
        $updated = $this->employees->update($employee, $request->validated());

        return new EmployeeResource($updated->load('team'));
    }

    /**
     * DELETE /api/v1/admin/employees/{employee}
     */
    public function destroy(User $employee): JsonResponse
    {
        $this->employees->delete($employee);

        return response()->json(['message' => 'Employee deleted.']);
    }
}
