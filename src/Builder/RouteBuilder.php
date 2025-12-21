<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining API route/path operations.
 */
class RouteBuilder
{
    private ApiDocBuilder $apiDocBuilder;
    private string $path = '';
    private string $method = 'GET';

    /** @var array<string, mixed> */
    private array $definition = [];

    /** @var array<string> */
    private array $tags = [];

    /** @var array<ParameterBuilder> */
    private array $parameterBuilders = [];

    /** @var array<ResponseBuilder> */
    private array $responseBuilders = [];

    private ?RequestBodyBuilder $requestBodyBuilder = null;

    public function __construct(ApiDocBuilder $apiDocBuilder)
    {
        $this->apiDocBuilder = $apiDocBuilder;
    }

    /**
     * Set the path for this route.
     *
     * @param string $path The route path (e.g., '/api/users/{id}')
     */
    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Set the HTTP method for this route.
     *
     * @param string $method The HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    public function method(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * Set the operation ID.
     *
     * @param string $operationId Unique operation identifier
     */
    public function operationId(string $operationId): self
    {
        $this->definition['operationId'] = $operationId;

        return $this;
    }

    /**
     * Set the summary for this route.
     *
     * @param string $summary Short summary of the operation
     */
    public function summary(string $summary): self
    {
        $this->definition['summary'] = $summary;

        return $this;
    }

    /**
     * Set the description for this route.
     *
     * @param string $description Detailed description of the operation
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Add a tag to this route.
     *
     * @param string $tag Tag name
     */
    public function tag(string $tag): self
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Start building a parameter for this route.
     */
    public function parameter(): ParameterBuilder
    {
        $builder = new ParameterBuilder($this);
        $this->parameterBuilders[] = $builder;

        return $builder;
    }

    /**
     * Start building a request body for this route.
     */
    public function requestBody(): RequestBodyBuilder
    {
        $this->requestBodyBuilder = new RequestBodyBuilder($this);

        return $this->requestBodyBuilder;
    }

    /**
     * Start building a response for this route.
     *
     * @param int $statusCode HTTP status code
     */
    public function response(int $statusCode): ResponseBuilder
    {
        $builder = new ResponseBuilder($this, $statusCode);
        $this->responseBuilders[] = $builder;

        return $builder;
    }

    /**
     * Finish building this route and return to the main builder.
     */
    public function end(): ApiDocBuilder
    {
        // Build tags
        if (!empty($this->tags)) {
            $this->definition['tags'] = $this->tags;
        }

        // Build parameters
        if (!empty($this->parameterBuilders)) {
            $this->definition['parameters'] = [];
            foreach ($this->parameterBuilders as $paramBuilder) {
                $this->definition['parameters'][] = $paramBuilder->buildArray();
            }
        }

        // Build request body
        if (null !== $this->requestBodyBuilder) {
            $this->definition['requestBody'] = $this->requestBodyBuilder->buildArray();
        }

        // Build responses
        if (!empty($this->responseBuilders)) {
            $this->definition['responses'] = [];
            foreach ($this->responseBuilders as $responseBuilder) {
                $responseData = $responseBuilder->buildArray();
                $statusCode = $responseBuilder->getStatusCode();
                $this->definition['responses'][(string)$statusCode] = $responseData;
            }
        }

        // Register the route
        $this->apiDocBuilder->registerRoute($this->path, $this->method, $this->definition);

        return $this->apiDocBuilder;
    }

    /**
     * Get the root ApiDocBuilder instance.
     *
     * @internal
     */
    public function getApiDocBuilder(): ApiDocBuilder
    {
        return $this->apiDocBuilder;
    }
}
