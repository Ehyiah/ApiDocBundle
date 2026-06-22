<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining API responses.
 */
class ResponseBuilder
{
    /** @var RouteBuilder|ApiDocBuilder */
    private $parentBuilder;

    private ?int $statusCode = null;

    private ?string $responseName = null;

    /** @var array<string, mixed> */
    private array $definition = [];

    /** @var array<ContentBuilder> */
    private array $contentBuilders = [];

    /** @var array<HeaderBuilder> */
    private array $headerBuilders = [];

    /**
     * @param RouteBuilder|ApiDocBuilder $parentBuilder
     * @param int|null $statusCode The HTTP status code (when used inline in a route)
     * @param string|null $responseName The component name (when used as a reusable component)
     */
    public function __construct(RouteBuilder|ApiDocBuilder $parentBuilder, ?int $statusCode = null, ?string $responseName = null)
    {
        $this->parentBuilder = $parentBuilder;
        $this->statusCode = $statusCode;
        $this->responseName = $responseName;
    }

    /**
     * Set the response description.
     *
     * @param string $description Description of the response
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Start building JSON content for the response.
     */
    public function jsonContent(): ContentBuilder
    {
        $apiDocBuilder = $this->parentBuilder instanceof ApiDocBuilder
            ? $this->parentBuilder
            : $this->parentBuilder->getApiDocBuilder();
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
        $apiDocBuilder = $this->parentBuilder instanceof ApiDocBuilder
            ? $this->parentBuilder
            : $this->parentBuilder->getApiDocBuilder();
        $builder = new ContentBuilder($this, $mediaType, $apiDocBuilder);
        $this->contentBuilders[] = $builder;

        return $builder;
    }

    /**
     * Start building a response header using the fluent builder.
     *
     * Common headers: X-Rate-Limit-Limit, X-Rate-Limit-Remaining, X-Rate-Limit-Reset,
     * X-Request-ID, ETag, Last-Modified, Location, Retry-After, X-Total-Count
     *
     * @param string $name Header name (e.g., 'X-Rate-Limit-Remaining')
     */
    public function header(string $name): HeaderBuilder
    {
        $builder = new HeaderBuilder($this, $name);
        $this->headerBuilders[] = $builder;

        return $builder;
    }

    /**
     * Add a response header using an array definition.
     *
     * @param string $name Header name
     * @param array<string, mixed> $definition Header definition
     */
    public function headerArray(string $name, array $definition): self
    {
        $builder = new HeaderBuilder($this, $name);
        // We need to store the raw definition, so we'll handle this differently
        $this->definition['headers'][$name] = $definition;

        return $this;
    }

    /**
     * Finish building this response and return to the parent builder.
     *
     * @return RouteBuilder|ApiDocBuilder
     */
    public function end(): RouteBuilder|ApiDocBuilder
    {
        if ($this->parentBuilder instanceof ApiDocBuilder && null !== $this->responseName) {
            $this->parentBuilder->registerResponse($this->responseName, $this->buildArray());
        }

        return $this->parentBuilder;
    }

    /**
     * Add a link to another operation.
     *
     * Links allow outgoing requests to other operations for request/response pairs.
     *
     * @param string $name Link name
     * @param array<string, mixed> $link Link definition
     */
    public function link(string $name, array $link): self
    {
        $this->definition['links'][$name] = $link;

        return $this;
    }

    /**
     * Get the status code for this response.
     *
     * @internal
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Build the response definition as an array.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function buildArray(): array
    {
        // Build headers from HeaderBuilder instances
        if (!empty($this->headerBuilders)) {
            if (!isset($this->definition['headers'])) {
                $this->definition['headers'] = [];
            }
            foreach ($this->headerBuilders as $headerBuilder) {
                $headerName = $headerBuilder->getName();
                $this->definition['headers'][$headerName] = $headerBuilder->buildArray();
            }
        }

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
