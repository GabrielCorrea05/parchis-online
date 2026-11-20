<?php

namespace App\Http\Requests;

use App\Domain\Game\Enums\Color;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'min:2', 'max:24'],
            'color' => ['required', 'string', Rule::in(Color::values())],
        ];
    }
}
