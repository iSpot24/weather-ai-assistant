<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 *
 */
class UserService
{
    /**
     * @param $displayName
     * @return User|string
     */
    public function create($displayName): User|string
    {
        $validator = Validator::make(
          ['display_name' => $displayName],
          ['display_name' => 'required|string|min:3|max:50']
        );

        if ($validator->fails()) {
            return "There seems to be a problem with the information you gave me. Let's try again.";
        }

        $displayName = $validator->valid()['display_name'];
        $userName = Str::snake($displayName);
        $displayName = ucwords(Str::replace("_", " ", $userName));

        return User::query()->create([
            'display_name' => $displayName,
            'user_name' => $userName
        ]);
    }

    /**
     * @param $displayName
     * @return User|string|null
     */
    public function getUserWithSession($displayName): User|string|null
    {
        $validator = Validator::make(
            ['display_name' => $displayName],
            ['display_name' => 'required|string|min:3|max:50']
        );

        if ($validator->fails()) {
            return "There seems to be a problem with the information you gave me. Let's try again.";
        }

        $displayName = $validator->valid()['display_name'];

        return User::query()->with(['sessions' => fn($q) => $q->latest('updated_at')->first()])
            ->where('user_name', Str::snake($displayName))
            ->first();
    }
}
