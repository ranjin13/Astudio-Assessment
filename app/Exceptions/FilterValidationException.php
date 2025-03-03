<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class FilterValidationException extends Exception
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'Invalid filter parameters', int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->message,
            'errors' => $this->errors
        ], $this->code);
    }
} 