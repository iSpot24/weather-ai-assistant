<?php

namespace App\Services;

use App\Models\Session;
use Illuminate\Support\Facades\Validator;

/**
 *
 */
class SessionService
{
    /**
     * @param int $userId
     * @return Session
     */
    public function create(int $userId): Session
    {
        return Session::query()->create([
            'user_id' => $userId,
            'history' => []
        ]);
    }

    /**
     * @param Session $session
     * @param array $data
     * @return Session|null
     */
    public function updateSession(Session $session, array $data): ?Session
    {
        $validator = Validator::make($data, [
            'location' => 'sometimes|required|string|min:2|max:100',
            'lat' => 'sometimes|required|numeric',
            'long' => 'sometimes|required|numeric'
        ]);

        if ($validator->fails()) {
            return null;
        }

        $data = $validator->valid();
        $session->setAttribute('location', $data['location']);
        $session->setAttribute('lat', $data['lat']);
        $session->setAttribute('long', $data['long']);
        $session->save();

        return $session;
    }

    /**
     * @param Session $session
     * @param array $history
     * @return Session|null
     */
    public function updateHistory(Session $session, array $history): ?Session
    {
        $validator = Validator::make($history, [
            'location' => 'sometimes|required|string|min:2|max:100',
            'lat' => 'sometimes|required|numeric',
            'long' => 'sometimes|required|numeric'
        ]);

        if ($validator->fails()) {
            return null;
        }

        $history = $session->getAttribute('history');

        $newHistory = $validator->valid();
        $newHistory['assistant'] = htmlspecialchars($newHistory['assistant'], ENT_QUOTES);

        $history[] = $newHistory;

        $session->setAttribute('history', $history);
        $session->save();

        return $session;
    }
}
