<?php

namespace App\Services\Ai;

use InvalidArgumentException;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use App\Services\Ai\Providers\GroqProvider;
use App\Services\Ai\Providers\MockProvider;

class AiManager
{
    protected $providers = [];

    /**
     * Get a provider instance.
     *
     * @param string|null $name
     * @return \App\Services\Ai\AiProviderInterface
     */
    public function provider($name = null)
    {
        $name = $name ?: config('ai.default', 'gemini');

        if (!isset($this->providers[$name])) {
            $this->providers[$name] = $this->createProvider($name);
        }

        return $this->providers[$name];
    }

    /**
     * Create a provider instance.
     *
     * @param string $name
     * @return \App\Services\Ai\AiProviderInterface
     */
    protected function createProvider($name)
    {
        $config = config("ai.providers.{$name}");

        if (!$config && $name !== 'mock') {
            throw new InvalidArgumentException("AI Provider [{$name}] is not configured.");
        }

        if ($name === 'mock') {
            $config = config("ai.providers.mock", []);
        }

        switch ($name) {
            case 'gemini':
                return new GeminiProvider($config);
            case 'groq':
                return new GroqProvider($config);
            case 'mock':
                return new MockProvider($config);
            default:
                throw new InvalidArgumentException("AI Provider [{$name}] is not supported.");
        }
    }

    /**
     * Dynamically pass methods to the default provider.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->provider()->$method(...$parameters);
    }
}
