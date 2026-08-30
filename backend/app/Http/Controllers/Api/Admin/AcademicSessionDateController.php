<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSessionDateRequest;
use App\Http\Requests\Admin\UpdateSessionDateRequest;
use App\Models\AcademicSession;
use App\Models\AcademicSessionDate;
use Illuminate\Http\JsonResponse;

/**
 * Key dates within an academic session.
 *
 * Nested under a session because a date has no meaning outside one. The nested
 * binding is verified in every action — route model binding resolves both
 * parameters independently, so without the check a date could be read or
 * mutated through a session it does not belong to.
 */
final class AcademicSessionDateController extends Controller
{
    public function index(AcademicSession $session): JsonResponse
    {
        return response()->json([
            'data' => $session->dates->map(fn (AcademicSessionDate $date) => $this->map($date)),
        ]);
    }

    public function store(StoreSessionDateRequest $request, AcademicSession $session): JsonResponse
    {
        $date = $session->dates()->create($request->validated());

        return response()->json([
            'message' => 'Key date added successfully.',
            'data' => $this->map($date),
        ], 201);
    }

    public function update(
        UpdateSessionDateRequest $request,
        AcademicSession $session,
        AcademicSessionDate $date,
    ): JsonResponse {
        $this->assertBelongsTo($session, $date);

        $date->update($request->validated());

        return response()->json([
            'message' => 'Key date updated successfully.',
            'data' => $this->map($date->fresh()),
        ]);
    }

    public function destroy(AcademicSession $session, AcademicSessionDate $date): JsonResponse
    {
        $this->assertBelongsTo($session, $date);

        $date->delete();

        return response()->json(['message' => 'Key date removed successfully.']);
    }

    private function assertBelongsTo(AcademicSession $session, AcademicSessionDate $date): void
    {
        abort_if((int) $date->academic_session_id !== (int) $session->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(AcademicSessionDate $date): array
    {
        return [
            'id' => $date->id,
            'label' => $date->label,
            'description' => $date->description,
            'date' => $date->date?->toDateString(),
            'is_deadline' => $date->is_deadline,
        ];
    }
}
