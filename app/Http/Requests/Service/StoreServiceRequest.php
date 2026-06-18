<?php

namespace App\Http\Requests\Service;

use App\Rules\PublicDomain;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', new PublicDomain()],
            'type'   => ['sometimes', 'string', 'in:wordpress'],
        ];
    }
}
