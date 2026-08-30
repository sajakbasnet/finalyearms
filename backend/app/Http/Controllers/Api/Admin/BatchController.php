<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBatchRequest;
use App\Http\Requests\Admin\UpdateBatchRequest;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $batches = Batch::query()
            ->with('department:id,name,code')
            ->withCount('students')
            ->when(
                $request->filled('department_id'),
                fn ($query) => $query->where('department_id', $request->integer('department_id')),
            )
            ->when(
                $request->filled('is_active'),
                fn ($query) => $query->where('is_active', $request->boolean('is_active')),
            )
            ->orderByDesc('intake_year')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $batches->map(fn (Batch $batch) => $this->map($batch)),
        ]);
    }

    public function store(StoreBatchRequest $request): JsonResponse
    {
        $batch = Batch::query()->create($request->validated());

        return response()->json([
            'message' => 'Batch created successfully.',
            'data' => $this->map($batch->load('department:id,name,code')->loadCount('students')),
        ], 201);
    }

    public function update(UpdateBatchRequest $request, Batch $batch): JsonResponse
    {
        $batch->update($request->validated());

        return response()->json([
            'message' => 'Batch updated successfully.',
            'data' => $this->map($batch->load('department:id,name,code')->loadCount('students')),
        ]);
    }

    /**
     * Deletes an empty batch, deactivates one that has students.
     *
     * Removing a batch that students belong to would strip their cohort with no
     * way to recover which one it was, so it is retired instead.
     */
    public function destroy(Batch $batch): JsonResponse
    {
        $studentCount = $batch->students()->count();

        if ($studentCount > 0) {
            $batch->update(['is_active' => false]);

            return response()->json([
                'message' => "Batch deactivated — {$studentCount} student(s) still belong to it.",
                'data' => $this->map($batch->load('department:id,name,code')->loadCount('students')),
            ]);
        }

        $batch->delete();

        return response()->json(['message' => 'Batch deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(Batch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'intake_year' => $batch->intake_year,
            'is_active' => $batch->is_active,
            'students_count' => $batch->students_count ?? 0,
            'department' => $batch->department === null ? null : [
                'id' => $batch->department->id,
                'name' => $batch->department->name,
                'code' => $batch->department->code,
            ],
        ];
    }
}
