<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PatientCase;

class PatientCaseController extends AbstractTaskController
{
    protected string $modelClass = PatientCase::class;

    protected string $entityName = '病历';

    protected function rules(bool $isUpdate = false): array
    {
        return [
            'patient_name' => ['required', 'string', 'max:50'],
            'admission_no' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', 'in:draft,submitted'],
        ];
    }
}
