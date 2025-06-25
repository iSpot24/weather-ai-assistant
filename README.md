# Weather AI Assistant

A simple CLI weather AI chat using Laravel, OpenAI, and Open-Meteo API.

## Setup

1. Clone the repository
2. Run `composer install`
3. Copy `.env.example` to `.env` and configure:
    - `OPENAI_API_KEY` - Your OpenAI API key
    - `OPENAI_MAX_TOKENS` - Optional for setting max tokens (default: 500)
    - `OPENAI_MAX_STEPS` - Optional for setting the number of interactions. (min: 2, default: 2)
    - `OPENAI_CONV_CONTEXT_LENGTH` - The number of chat exchanges to be added as context. Can also be disabled if not set.
    - `OPEN_METEO_WEATHER_URL` - Open-Meteo weather endpoint. Setting is optional
    - `OPEN_METEO_GEOCODING_URL` - Open-Meteo Geocoding endpoint for location coordinates. Setting is optional
4. Run `php artisan migrate` and create SQLITE file when asked
5. Run `php artisan chat:weather` to start the chatting
6. Run `php artisan chat:weather --new-session` to start with a new session for a user

## Usage

- Provide name that will be used as authentication and to restore last session
- Ask about the weather (ex: "What's the weather at my location?")
- Provide a city name when asked
- The AI will remember the location for future sessions
- It can be asked for a different location in same session
- Specific locations like `Cluj-Napoca, Romania` work as well
- It can be asked what location does he already know

## What's Not Implemented

- Configurable different units for weather information
- Different weather conditions. It will fetch the same information every time

## Possible improvements

1. Add more tests
2. Dynamic weather data points depending on what the user asks
3. It would be possible to get past or future weather data. Right now it fetches recent data.
4. Retrieved data from apis could be more strictly validated.
5. Improve the conversation flow with more natural interactions
6. Add more tools for the AI to work with to cover more scenarios.

## Design Decisions

- Dependency injection pattern seemed fit for this scenario
- Kept weather data basic to focus on the functionality
- Used OpenAI GPT-3.5 for cost/performance balance
- Feature tests for models and Prism framework for demonstration purposes
