<?php

namespace App\Services;

use App\Models\Session;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 *
 */
class WeatherService
{
    /**
     *
     */
    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * @param Session $session
     * @param string $location
     * @return string|array
     */
    public function getWeather(Session $session, string $location): string|array
    {
        $lat = $session->getAttribute('lat');
        $long = $session->getAttribute('long');

        if (!$lat && !$long || $location !== $session->getAttribute('location')) {
            try {
                $locationData = $this->getLocationCoordinates($location);
            } catch (GuzzleException $e) {
                Log::error("Weather service getLocationCoordinates error: {$e->getMessage()}");
                return "You couldn't fetch location coordinates. Tell the user to try again later.";
            }

            if (!$locationData) {
                return "You couldn't find the location $location. Tell the user to check misspelling or try again.";
            }

            $location = $locationData['location'];
            $lat = $locationData['lat'];
            $long = $locationData['long'];
        }

        try {
            $weatherData = $this->getWeatherData($lat, $long);
        } catch (GuzzleException $e) {
            Log::error('Weather service getWeatherData error: ' . $e->getMessage());
            return "You couldn't fetch weather information. Tell the user to try again later.";
        }

        if (!$weatherData || !isset($weatherData['current'])) {
            return "You couldn't fetch weather information. Tell the user to try again later.";
        }

        return [
            'weather' => $this->formatWeatherResponse($location, $weatherData),
            'lat' => $lat,
            'long' => $long
        ];
    }

    /**
     * @param string $location
     * @return array|null
     * @throws GuzzleException
     */
    private function getLocationCoordinates(string $location): ?array
    {
        $response = $this->client->get(config('services.open_meteo.geocoding_url'), [
            'query' => [
                'name' => $location,
                'count' => 1
            ]
        ]);

        $data = json_encode($response->getBody()->getContents(), true);

        if (isset($data['error']) || !isset($data['results']) || !isset($data['results'][0])) {
            return null;
        }

        $locationData = $data['results'][0];

        return [
            'location' => $locationData['name'],
            'lat' => $locationData['latitude'],
            'long' => $locationData['longitude']
        ];
    }

    /**
     * @param mixed $lat
     * @param mixed $long
     * @return array
     * @throws GuzzleException
     */
    private function getWeatherData(mixed $lat, mixed $long): array
    {
        $response = $this->client->get(config('services.open_meteo.weather_url'), [
            'query' => [
                'latitude' => $lat,
                'longitude' => $long,
                'current' => 'temperature_2m,apparent_temperature,precipitation_probability,relative_humidity_2m,cloud_cover,wind_speed_10m'
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param mixed $location
     * @param array $weather
     * @return string
     */
    private function formatWeatherResponse(mixed $location, array $weather): string
    {
        $units = $weather['current_units'];
        $weather = $weather['current'];

        $text = "Current weather in $location: ";
        $text .= "Temperature: {$weather['temperature_2m']}{$units['temperature_2m']} ";
        $text .= "(feels like {$weather['apparent_temperature']}{$units['apparent_temperature']})";
        $text .= "Precipitation probability: {$weather['precipitation_probability']}{$units['precipitation_probability']} ";
        $text .= "Relative humidity: {$weather['relative_humidity_2m']}{$units['relative_humidity_2m']} ";
        $text .= "Cloud cover: {$weather['cloud_cover']}{$units['cloud_cover']} ";
        $text .= "Wind speed: {$weather['wind_speed_10m']} {$units['wind_speed_10m']}";

        return $text;
    }
}
