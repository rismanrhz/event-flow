<?php

namespace App\Http\Requests\Participant;

use Illuminate\Foundation\Http\FormRequest;

class CheckInParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required_without:participant_id', 'nullable', 'string', 'max:32'],
            'participant_id' => ['required_without:code', 'nullable', 'integer'],
        ];
    }
}
