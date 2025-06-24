<?php

namespace App\Console\Commands;

use App\Models\Session;
use App\Services\ChatService;
use App\Services\UserService;
use Illuminate\Console\Command;

class WeatherChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:weather {--new-session : Start a new chat session}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Begin chatting with the AI weather assistant';

    private ChatService $chatService;
    private UserService $userService;

    public function __construct(ChatService $chatService, UserService $userService)
    {
        parent::__construct();

        $this->chatService = $chatService;
        $this->userService = $userService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayWelcome();
        $initData = $this->initSession();

        if (!is_array($initData)) {
            return $initData;
        }

        $user = $initData['user'];
        $session = $initData['session'];
        $this->chatService->setSession($session);

        while (true) {
            $input = $this->ask("You ");
            $input = strip_tags($input);

            if ($this->checkClose($input)) {
                $this->info("Assistant: Goodbye {$user->getAttribute('display_name')}. See you next time!\n");
                break;
            }

            $response = $this->chatService->chat($input);
            $this->info("\nAssistant: $response\n");
        }

        return self::SUCCESS;
    }

    private function displayWelcome(): void
    {
        $this->info("\nType 'exit|quit|bye' to stop chatting\n");
        $this->info("Your personal weather assistant\n");
        $this->info("Assistant: Ask me about the weather anywhere in the world!\n");
    }

    private function initSession(): array|int
    {
        $this->info("Assistant: Let's get started!");
        $input = $this->ask("Assistant: What's your name?");
        $input = strip_tags($input);

        if ($this->checkClose($input)) {
            return self::SUCCESS;
        }

        $user = $this->userService->getUserWithSession($input);

        if (is_string($user)) {
            $this->info($user);

            return self::FAILURE;
        }

        if (!$user) {
            $user = $this->userService->create($input);
            $session = $this->chatService->createSession($user->getAttribute('id'));

            $this->info("Assistant: Nice to meet you, {$user->getAttribute('display_name')}\n");
            $this->info("Assistant: I'm here to help you with weather information.\n");

            return [
                'user' => $user,
                'session' => $session
            ];
        }

        $session = $user->sessions->first();

        if (!$session || $this->option('new-session')) {
            $session = $this->chatService->createSession($user->getAttribute('id'));
        }

        $this->info("Assistant: Welcome back, {$user->getAttribute('display_name')}\n");

        return [
            'user' => $user,
            'session' => $session
        ];
    }

    private function checkClose(mixed $input)
    {
        return in_array(strtolower($input), ['exit', 'quit', 'bye']);
    }
}
