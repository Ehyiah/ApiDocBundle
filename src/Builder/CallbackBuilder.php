<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining OpenAPI Callback objects.
 *
 * A map of out-of-band callbacks related to the parent operation.
 * Each entry is an expression that evaluates to a Path Item Object.
 */
class CallbackBuilder
{
    /** @var RouteBuilder|ApiDocBuilder */
    private $parentBuilder;

    private string $name;

    /** @var array<string, mixed> */
    private array $definition = [];

    /**
     * @param RouteBuilder|ApiDocBuilder $parentBuilder The parent builder
     * @param string $name The callback name
     */
    public function __construct($parentBuilder, string $name)
    {
        $this->parentBuilder = $parentBuilder;
        $this->name = $name;
    }

    /**
     * Add a path item under an expression.
     *
     * @param string $expression The key expression (e.g., '{$request.body#/callbackUrl}')
     * @param array<string, mixed> $pathItem A Path Item Object definition
     */
    public function pathItem(string $expression, array $pathItem): self
    {
        $this->definition[$expression] = $pathItem;

        return $this;
    }

    /**
     * Finish building this callback and return to the parent builder.
     *
     * @return RouteBuilder|ApiDocBuilder
     */
    public function end()
    {
        if ($this->parentBuilder instanceof ApiDocBuilder) {
            $this->parentBuilder->registerCallback($this->name, $this->definition);
        }

        return $this->parentBuilder;
    }

    /**
     * Get the callback name.
     *
     * @internal
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Build the callback definition as an array.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function buildArray(): array
    {
        return $this->definition;
    }
}
