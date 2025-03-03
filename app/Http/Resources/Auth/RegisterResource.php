<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->id,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'created_at' => $this->created_at?->toISOString(),
            ],
            'token' => $this->when(isset($this->token), fn() => [
                'access_token' => $this->token,
                'token_type' => 'Bearer',
            ]),
            'message' => 'Registration successful.',
        ];
    }
} 