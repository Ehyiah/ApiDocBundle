<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining request bodies.
 */
class RequestBodyBuilder
{
    private RouteBuilder $routeBuilder;

    /** @var array<string, mixed> */
    private array $definition = [];

    /** @var array<ContentBuilder> */
    private array $contentBuilders = [];

    public function __construct(RouteBuilder $routeBuilder)
    {
        $this->routeBuilder = $routeBuilder;
    }

    /**
     * Set the request body description.
     *
     * @param string $description Description of the request body
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Mark the request body as required.
     *
     * @param bool $required Whether the request body is required
     */
    public function required(bool $required = true): self
    {
        $this->definition['required'] = $required;

        return $this;
    }

    /**
     * Start building JSON content for the request body.
     */
    public function jsonContent(): ContentBuilder
    {
        $apiDocBuilder = $this->routeBuilder->getApiDocBuilder();
        $builder = new ContentBuilder($this, 'application/json', $apiDocBuilder);
        $this->contentBuilders[] = $builder;

        return $builder;
    }

    /**
     * Start building content for a specific media type.
     *
     * @param string $mediaType Media type (e.g., 'application/xml')
     */
    public function content(string $mediaType): ContentBuilder
    {
        $apiDocBuilder = $this->routeBuilder->getApiDocBuilder();
        $builder = new ContentBuilder($this, $mediaType, $apiDocBuilder);
        $this->contentBuilders[] = $builder;

        return $builder;
    }

    /**
     * Finish building this request body and return to the route builder.
     */
    public function end(): RouteBuilder
    {
        return $this->routeBuilder;
    }

    /**
     * Build the request body definition as an array.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function buildArray(): array
    {
        // Build content
        if (!empty($this->contentBuilders)) {
            $this->definition['content'] = [];
            foreach ($this->contentBuilders as $contentBuilder) {
                $contentData = $contentBuilder->buildArray();
                $mediaType = $contentBuilder->getMediaType();
                $this->definition['content'][$mediaType] = $contentData;
            }
        }

        return $this->definition;
    }
}
