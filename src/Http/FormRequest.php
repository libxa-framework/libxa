<?php

declare(strict_types=1);

namespace Libxa\Http;

use Libxa\Validation\Validator;
use Libxa\Validation\ValidationException;

/**
 * LibxaFrame Form Request
 *
 * Base class for all form requests.
 * Handles automatic authorization and validation before the controller is called.
 */
abstract class FormRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    abstract public function rules(): array;

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Validate the class instance.
     */
    public function validateResolved(): void
    {
        if (! $this->authorize()) {
            $this->failedAuthorization();
        }

        $validator = new Validator($this->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            $this->failedValidation($validator);
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator->errors());
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        throw new \RuntimeException('This action is unauthorized.', 403);
    }

    /**
     * Get the validated data from the request.
     */
    public function validated(): array
    {
        $validator = new Validator($this->all(), $this->rules(), $this->messages());
        
        return $validator->validated();
    }
}
