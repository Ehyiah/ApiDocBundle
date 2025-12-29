<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining OpenAPI base configuration.
 *
 * Handles: openapi version, info (title, description, version, contact, license),
 * servers, and global security requirements.
 */
class InfoBuilder
{
    private ApiDocBuilder $parentBuilder;

    private string $openApiVersion = '3.0.0';

    /** @var array<string, mixed> */
    private array $info = [];

    /** @var array<array<string, mixed>> */
    private array $servers = [];

    /** @var array<array<string, array<string>>> */
    private array $security = [];

    public function __construct(ApiDocBuilder $parentBuilder)
    {
        $this->parentBuilder = $parentBuilder;
    }

    /**
     * Set the OpenAPI specification version.
     *
     * @param string $version OpenAPI version (e.g., '3.0.0', '3.1.0')
     */
    public function openApiVersion(string $version): self
    {
        $this->openApiVersion = $version;

        return $this;
    }

    /**
     * Set the API title.
     *
     * @param string $title API title
     */
    public function title(string $title): self
    {
        $this->info['title'] = $title;

        return $this;
    }

    /**
     * Set the API description.
     *
     * @param string $description API description (supports Markdown)
     */
    public function description(string $description): self
    {
        $this->info['description'] = $description;

        return $this;
    }

    /**
     * Set the API version.
     *
     * @param string $version API version (e.g., '1.0.0')
     */
    public function version(string $version): self
    {
        $this->info['version'] = $version;

        return $this;
    }

    /**
     * Set the terms of service URL.
     *
     * @param string $url Terms of service URL
     */
    public function termsOfService(string $url): self
    {
        $this->info['termsOfService'] = $url;

        return $this;
    }

    /**
     * Set contact information.
     *
     * @param string|null $name Contact name
     * @param string|null $email Contact email
     * @param string|null $url Contact URL
     */
    public function contact(?string $name = null, ?string $email = null, ?string $url = null): self
    {
        $contact = [];
        if (null !== $name) {
            $contact['name'] = $name;
        }
        if (null !== $email) {
            $contact['email'] = $email;
        }
        if (null !== $url) {
            $contact['url'] = $url;
        }

        if (!empty($contact)) {
            $this->info['contact'] = $contact;
        }

        return $this;
    }

    /**
     * Set license information.
     *
     * @param string $name License name (e.g., 'MIT', 'Apache 2.0')
     * @param string|null $url License URL
     */
    public function license(string $name, ?string $url = null): self
    {
        $this->info['license'] = ['name' => $name];
        if (null !== $url) {
            $this->info['license']['url'] = $url;
        }

        return $this;
    }

    /**
     * Add a server.
     *
     * @param string $url Server URL
     * @param string|null $description Server description
     * @param array<string, array<string, mixed>> $variables Server variables
     */
    public function server(string $url, ?string $description = null, array $variables = []): self
    {
        $server = ['url' => $url];
        if (null !== $description) {
            $server['description'] = $description;
        }
        if (!empty($variables)) {
            $server['variables'] = $variables;
        }

        $this->servers[] = $server;

        return $this;
    }

    /**
     * Set global security requirements.
     *
     * @param string $schemeName Security scheme name
     * @param array<string> $scopes Required scopes (for OAuth2)
     */
    public function addSecurityRequirement(string $schemeName, array $scopes = []): self
    {
        $this->security[] = [$schemeName => $scopes];

        return $this;
    }

    /**
     * Finish building and return to the parent builder.
     */
    public function end(): ApiDocBuilder
    {
        $this->parentBuilder->registerInfo($this->buildArray());

        return $this->parentBuilder;
    }

    /**
     * Build the configuration as an array.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function buildArray(): array
    {
        $config = [
            'openapi' => $this->openApiVersion,
        ];

        if (!empty($this->info)) {
            $config['info'] = $this->info;
        }

        if (!empty($this->servers)) {
            $config['servers'] = $this->servers;
        }

        if (!empty($this->security)) {
            $config['security'] = $this->security;
        }

        return $config;
    }
}
