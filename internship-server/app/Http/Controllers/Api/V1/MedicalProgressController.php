<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\MedicalProgress;

class MedicalProgressController extends AbstractTaskController
{
    protected string $modelClass = MedicalProgress::class;

    protected string $entityName = '病程记录';

    protected function rules(bool $isUpdate = false): array
    {
        return [
            'patient_case_id' => ['nullable', 'integer', 'exists:patient_cases,id'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
            'status' => ['nullable', 'in:draft,submitted'],
        ];
    }
}
