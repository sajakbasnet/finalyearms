<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role?->id,
                'name' => $this->role?->name,
                'slug' => $this->role?->slug,
            ]),
            'teacher' => $this->whenLoaded('teacher', function () {
                if ($this->teacher === null) {
                    return null;
                }

                return [
                    'id' => $this->teacher->id,
                    'employee_id' => $this->teacher->employee_id,
                    'designation' => $this->teacher->designation,
                    'department' => $this->teacher->relationLoaded('department')
                        ? [
                            'id' => $this->teacher->department?->id,
                            'name' => $this->teacher->department?->name,
                            'code' => $this->teacher->department?->code,
                        ]
                        : null,
                ];
            }),
            'student' => $this->whenLoaded('student', function () {
                if ($this->student === null) {
                    return null;
                }

                return [
                    'id' => $this->student->id,
                    'registration_number' => $this->student->registration_number,
                    'roll_number' => $this->student->roll_number,
                    'batch' => $this->student->batch,
                    'department' => $this->student->relationLoaded('department')
                        ? [
                            'id' => $this->student->department?->id,
                            'name' => $this->student->department?->name,
                            'code' => $this->student->department?->code,
                        ]
                        : null,
                    'academic_session' => $this->student->relationLoaded('academicSession')
                        ? [
                            'id' => $this->student->academicSession?->id,
                            'name' => $this->student->academicSession?->name,
                        ]
                        : null,
                ];
            }),
        ];
    }
}
