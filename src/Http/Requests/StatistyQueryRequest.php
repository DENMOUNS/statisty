<?php

declare(strict_types=1);

namespace Statisty\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StatistyQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // package-level authorization should be handled separately
    }

    public function rules(): array
    {
        return [
            'model' => ['required', 'string'],
            'columns' => ['sometimes', 'array'],
            'filters' => ['sometimes', 'array'],
            'group_by' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer'],
            'offset' => ['sometimes', 'integer'],
        ];
    }
}
