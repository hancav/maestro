<?php

namespace App\Support\Maestro;

use App\Support\Maestro\DTOs\AgentConfig;

class AgentPool
{
    /** @var array<string, Agent> */
    private array $agents = [];

    /**
     * @param  array<string, array>  $agentConfigs  Raw config arrays keyed by agent name
     * @param  callable(AgentConfig): Agent  $agentFactory  Factory that creates Agent from AgentConfig
     */
    public function __construct(array $agentConfigs, callable $agentFactory)
    {
        foreach ($agentConfigs as $name => $configData) {
            $configData['name'] = $configData['name'] ?? $name;
            $agentConfig = AgentConfig::fromArray($configData);
            $this->agents[$agentConfig->name] = $agentFactory($agentConfig);
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function get(string $name): Agent
    {
        if (! $this->has($name)) {
            throw new \InvalidArgumentException("Agent '{$name}' not found in pool");
        }

        return $this->agents[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->agents[$name]);
    }

    /**
     * @return array<string, Agent>
     */
    public function all(): array
    {
        return $this->agents;
    }

    /**
     * @return array<string, string>
     */
    public function getDescriptions(): array
    {
        $descriptions = [];

        foreach ($this->agents as $name => $agent) {
            $descriptions[$name] = $agent->getConfig()->description;
        }

        return $descriptions;
    }
}
