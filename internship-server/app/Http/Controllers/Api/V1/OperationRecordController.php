<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\OperationRecord;

class OperationRecordController extends AbstractTaskController
{
    protected string $modelClass = OperationRecord::class;

    protected string $entityName = '操作记录';

    protected function rules(bool $isUpdate = false): array
    {
        return [
            'type' => ['nullable', 'in:operation,surgery'],
            'project_name' => ['required', 'string', 'max:100'],
            'patient_name' => ['nullable', 'string', 'max:50'],
            'operation_date' => ['required', 'date'],
            'role' => ['nullable', 'in:主刀,助手,观摩'],
            'content' => ['required', 'string'],
            'status' => ['nullable', 'in:draft,submitted'],
        ];
    }
}
