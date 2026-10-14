<?php

namespace App\Http\Requests\Participant;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->missing('user_id')) {
            return true;
        }

        /** @var Event|null $event */
        $event = $this->route('event');

        return $event !== null && $this->user()?->can('manageParticipants', $event) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }
}
