<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Services\ChatService;
use App\Services\SessionService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Prism;
use Prism\Prism\Testing\TextStepFake;
use Prism\Prism\Text\ResponseBuilder;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatService $chatService;
    private WeatherService $weatherService;
    private SessionService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->weatherService = $this->createMock(WeatherService::class);
        $this->sessionService = $this->createMock(SessionService::class);

        $this->chatService = new ChatService(
            $this->weatherService,
            $this->sessionService
        );
    }

    #[Test]
    public function it_can_create_a_new_session()
    {
        $userId = 1;
        $expectedSession = new Session(['user_id' => $userId]);

        $this->sessionService->expects($this->once())
            ->method('create')
            ->with($userId)
            ->willReturn($expectedSession);

        $session = $this->chatService->createSession($userId);

        $this->assertEquals($expectedSession, $session);
    }

    #[Test]
    public function it_can_handle_weather_related_queries()
    {
        // Setup fake Prism responses
        $responses = [
            (new ResponseBuilder)
                ->addStep(
                    TextStepFake::make()
                        ->withToolCalls([
                            new ToolCall(
                                id: 'call_123',
                                name: 'get_weather',
                                arguments: ['location' => 'London']
                            ),
                        ])
                        ->withFinishReason(FinishReason::ToolCalls)
                        ->withUsage(new Usage(15, 25))
                        ->withMeta(new Meta('fake-1', 'gpt-3.5-turbo'))
                )
                ->addStep(
                    TextStepFake::make()
                        ->withText('The weather in London is sunny with 22°C.')
                        ->withToolResults([
                            new ToolResult(
                                toolCallId: 'call_123',
                                toolName: 'get_weather',
                                args: ['location' => 'London'],
                                result: 'Sunny, 22°C'
                            ),
                        ])
                        ->withFinishReason(FinishReason::Stop)
                        ->withUsage(new Usage(20, 30))
                        ->withMeta(new Meta('fake-2', 'gpt-3.5-turbo')),
                )->toResponse(),
        ];

        Prism::fake($responses);

        // Mock weather service response
        $this->weatherService->method('getWeather')
            ->willReturn([
                'weather' => 'Sunny, 22°C',
                'lat' => 51.5074,
                'long' => -0.1278
            ]);

        // Mock session handling
        $session = new Session(['user_id' => 1]);
        $this->chatService->setSession($session);

        $this->sessionService->method('updateSession')
            ->willReturn($session);

        $this->sessionService->method('updateHistory')
            ->willReturn($session);

        // Test the chat method
        $response = $this->chatService->chat('What is the weather in London?');

        // Assertions
        $this->assertEquals('The weather in London is sunny with 22°C.', $response);
    }

    #[Test]
    public function it_can_retrieve_user_location()
    {
        // Setup fake Prism responses
        $responses = [
            (new ResponseBuilder)
                ->addStep(
                    TextStepFake::make()
                        ->withToolCalls([
                            new ToolCall(
                                id: 'call_456',
                                name: 'get_user_location',
                                arguments: []
                            ),
                        ])
                        ->withFinishReason(FinishReason::ToolCalls)
                        ->withUsage(new Usage(10, 20))
                        ->withMeta(new Meta('fake-1', 'gpt-3.5-turbo')),
                )
                ->addStep(
                    TextStepFake::make()
                        ->withText('Your current location is London.')
                        ->withToolResults([
                            new ToolResult(
                                toolCallId: 'call_456',
                                toolName: 'get_user_location',
                                args: [],
                                result: "User's stored location is: London"
                            ),
                        ])
                        ->withFinishReason(FinishReason::Stop)
                        ->withUsage(new Usage(15, 25))
                        ->withMeta(new Meta('fake-2', 'gpt-3.5-turbo')),
                )->toResponse(),
        ];

        Prism::fake($responses);

        // Mock session with location
        $session = new Session([
            'user_id' => 1,
            'location' => 'London',
            'lat' => 51.5074,
            'long' => -0.1278
        ]);
        $this->chatService->setSession($session);

        $this->sessionService->method('updateHistory')
            ->willReturn($session);

        // Test the chat method
        $response = $this->chatService->chat('What location do you have for me?');

        // Assertions
        $this->assertEquals('Your current location is London.', $response);
    }

    #[Test]
    public function it_handles_missing_location()
    {
        // Setup fake Prism responses
        $responses = [
            (new ResponseBuilder)
                ->addStep(
                    TextStepFake::make()
                        ->withToolCalls([
                            new ToolCall(
                                id: 'call_789',
                                name: 'get_user_location',
                                arguments: []
                            ),
                        ])
                        ->withFinishReason(FinishReason::ToolCalls)
                        ->withUsage(new Usage(10, 20))
                        ->withMeta(new Meta('fake-1', 'gpt-3.5-turbo')),
                )
                ->addStep(
                    TextStepFake::make()
                        ->withText("I don't have a location for you. Could you please tell me your location?")
                        ->withToolResults([
                            new ToolResult(
                                toolCallId: 'call_789',
                                toolName: 'get_user_location',
                                args: [],
                                result: 'Location is not stored. Ask the user for its location.'
                            ),
                        ])
                        ->withFinishReason(FinishReason::Stop)
                        ->withUsage(new Usage(15, 25))
                        ->withMeta(new Meta('fake-2', 'gpt-3.5-turbo')),
                )
                ->toResponse(),
        ];

        Prism::fake($responses);

        // Mock session without location
        $session = new Session(['user_id' => 1]);
        $this->chatService->setSession($session);

        $this->sessionService->method('updateHistory')
            ->willReturn($session);

        // Test the chat method
        $response = $this->chatService->chat('What location do you have for me?');

        // Assertions
        $this->assertStringContainsString("I don't have a location for you", $response);
    }

    #[Test]
    public function it_includes_conversation_context()
    {
        // Setup fake Prism responses
        $responses = [
            (new ResponseBuilder)
                ->addStep(
                    TextStepFake::make()
                        ->withText('I remember our previous conversation about London.')
                        ->withFinishReason(FinishReason::Stop)
                        ->withUsage(new Usage(15, 25))
                        ->withMeta(new Meta('fake-1', 'gpt-3.5-turbo')),
                )
                ->toResponse(),
        ];

        Prism::fake($responses);

        // Mock session with history
        $session = new Session([
            'user_id' => 1,
            'history' => [
                [
                    'user' => 'What is the weather in London?',
                    'assistant' => 'The weather in London is sunny.',
                    'created_at' => now()->format('d-m-Y H:i:s')
                ]
            ]
        ]);
        $this->chatService->setSession($session);

        $this->sessionService->method('updateHistory')
            ->willReturn($session);

        // Set config for context length
        config(['prism.providers.openai.conversation_context' => 1]);

        // Test the chat method
        $response = $this->chatService->chat('Do you remember our previous conversation?');

        // Assertions
        $this->assertStringContainsString('I remember our previous conversation about London', $response);
    }

    #[Test]
    public function it_handles_errors_gracefully()
    {
        // Setup fake Prism responses to simulate an error
        $responses = [
            (new ResponseBuilder)
                ->addStep(
                    TextStepFake::make()
                        ->withText('')
                        ->withFinishReason(FinishReason::Error)
                        ->withUsage(new Usage(5, 10))
                        ->withMeta(new Meta('fake-1', 'gpt-3.5-turbo')),
                )
                ->toResponse(),
        ];

        Prism::fake($responses);

        // Mock session
        $session = new Session(['user_id' => 1]);
        $this->chatService->setSession($session);

        // Test the chat method
        $response = $this->chatService->chat('What is the weather?');

        // Assertions
        $this->assertEquals('Something went wrong. Tell the user to try again later.', $response);
    }
}
