<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DamageReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDamageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku_id' => ['required', 'integer', 'exists:skus,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason_code' => ['nullable', new Enum(DamageReason::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
