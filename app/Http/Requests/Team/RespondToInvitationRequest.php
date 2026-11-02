<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\ApiRequest;

class RespondToInvitationRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is enforced in the controller
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:accept,reject',
        ];
    }
}
