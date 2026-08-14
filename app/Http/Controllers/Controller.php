<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Resolve a created_by filter value into the user IDs it refers to.
     *
     * New payment rows store the integer user ID in created_by, while legacy
     * rows store the user's name string — so both representations must be
     * matched. Accepts either form and returns the matching user IDs plus the
     * raw input (kept for the legacy string match).
     *
     * @param  mixed  $rawInput
     * @return array{userIds: array<int>, rawInput: mixed}
     */
    protected function createdByFilter($rawInput)
    {
        $userIds = [];

        if (is_numeric($rawInput)) {
            $userIds[] = (int) $rawInput;
        } elseif (is_string($rawInput) && $rawInput !== '') {
            $userIds = User::where('name', $rawInput)->pluck('id')->all();
        }

        return [
            'userIds' => array_values(array_unique(array_filter($userIds))),
            'rawInput' => $rawInput,
        ];
    }
}
