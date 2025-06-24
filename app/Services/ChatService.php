<?php

namespace App\Services;

use App\Models\Session;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Prism;

/**
 *
 */
class ChatService
{
    private const SYSTEM_PROMPT = <<<PROMPT
        You are a helpful weather assistant. Your primary function is to:
        1. Determine if users are asking about weather
        2. Use the 'get_user_location' tool to check if you already know the user's location
        3. Get location data when needed.
        4. Get detailed weather information. You can only provide one set of data.
        5. Always ask the user if he wants to know about a different location and nothing else.
        Identify and extract the new city provided and use it.
        6. Provide friendly, conversational responses

        When asked about weather:
        1. If location is known: Tell the user that you are aware of it's location.
        2. User may ask what's the current location you know, provide it to him.
        3. Use all the information you receive and always format it accordingly on new lines in natural language.
        4. Don't leave anything out of your response.
        5. If location unknown: politely ask for location.

        Keep responses concise and friendly.
        PROMPT;

    /**
     * @var WeatherService
     */
    private WeatherService $weatherService;
    /**
     * @var SessionService
     */
    private SessionService $sessionService;
    /**
     * @var Session|null
     */
    private ?Session $session = null;

    /**
     * @param WeatherService $weatherService
     * @param SessionService $sessionService
     */
    public function __construct(WeatherService $weatherService, SessionService $sessionService)
    {
        $this->weatherService = $weatherService;
        $this->sessionService = $sessionService;
    }

    /**
     * @param Session $session
     * @return void
     */
    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    /**
     * @param int $userId
     * @return Session
     */
    public function createSession(int $userId): Session
    {
        return $this->sessionService->create($userId);
    }

    /**
     * @param string $message
     * @return string
     */
    public function chat(string $message): string
    {
        $systemPrompt = self::SYSTEM_PROMPT;

        if (($contextLength = config('prism.providers.openai.conversation_context'))
            && ($history = $this->session->getAttribute('history')) && $history !== []
        ) {
            $systemPrompt .= $this->addConversationContext($history, $contextLength);
        }

        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-3.5-turbo')
            ->withMaxSteps(config('prism.providers.openai.max_steps'))
            ->withMaxTokens(config('prism.providers.openai.max_tokens'))
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($message)
            ->withTools([$this->getWeatherTool(), $this->getUserLocationTool()])
            ->asText();

        if (!$response->text) {
            return "Something went wrong. Tell the user to try again later.";
        }

        $this->session = $this->sessionService->updateHistory($this->session, [
            'assistant' => $response->text,
            'user' => $message,
            'created_at' => now()->format('d-m-Y H:i:s')
        ]);

        if (!$this->session) {
            return "Something went wrong. Please create a new session.";
        }

        return $response->text;
    }

    /**
     * @param mixed $history
     * @param mixed $contextLength
     * @return string
     */
    private function addConversationContext(mixed $history, mixed $contextLength): string
    {
        $context = "\nRecent conversation context: \n\n";
        $historyLength = count($history);

        if ($contextLength > $historyLength) {
            $contextLength = $historyLength;
        }

        for ($i = $historyLength - $contextLength; $i < $historyLength; $i++) {
            $context .= "User: '{$history[$i]['user']}'\n";
            $context .= "You: '{$history[$i]['assistant']}'\n\n";
        }

        return $context;
    }

    /**
     * @return \Prism\Prism\Tool
     */
    private function getUserLocationTool(): \Prism\Prism\Tool
    {
        return Tool::as('get_user_location')
            ->for("Get the user's stored location if available")
            ->using(function (): string {
                if ($location = $this->session->getAttribute('location')) {
                    return "User's stored location is: $location";
                }

                return "Location is not stored. Ask the user for its location.";
            });
    }

    /**
     * @return \Prism\Prism\Tool
     */
    private function getWeatherTool(): \Prism\Prism\Tool
    {
        return Tool::as('get_weather')
            ->for('Get weather conditions')
            ->withStringParameter('location', 'The city to get weather for')
            ->using(function (string $location): string {
                $result = $this->weatherService->getWeather($this->session, $location);

                if (!is_array($result)) {
                    return $result;
                }

                $this->session = $this->sessionService->updateSession($this->session, [
                    'location' => $location,
                    'lat' => $result['lat'],
                    'long' => $result['long']
                ]);

                if (!$this->session) {
                    return "There seems to be a problem with the information you found. Let's try again.";
                }

                return $result['weather'];
            });
    }
}
