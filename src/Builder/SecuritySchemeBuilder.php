<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining OpenAPI security schemes.
 *
 * Security schemes define the authentication methods available for the API.
 */
class SecuritySchemeBuilder
{
    private ApiDocBuilder $parentBuilder;

    private string $name;

    /** @var array<string, mixed> */
    private array $definition = [];

    public function __construct(ApiDocBuilder $parentBuilder, string $name)
    {
        $this->parentBuilder = $parentBuilder;
        $this->name = $name;
    }

    /**
     * Configure as a Bearer token authentication (JWT).
     *
     * @param string $bearerFormat Bearer format (e.g., 'JWT')
     */
    public function bearer(string $bearerFormat = 'JWT'): self
    {
        $this->definition = [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => $bearerFormat,
        ];

        return $this;
    }

    /**
     * Configure as Basic authentication.
     */
    public function basic(): self
    {
        $this->definition = [
            'type' => 'http',
            'scheme' => 'basic',
        ];

        return $this;
    }

    /**
     * Configure as an API Key authentication.
     *
     * @param string $keyName The name of the header, query or cookie parameter
     * @param string $in Location of the API key: 'header', 'query', or 'cookie'
     */
    public function apiKey(string $keyName, string $in = 'header'): self
    {
        $this->definition = [
            'type' => 'apiKey',
            'name' => $keyName,
            'in' => $in,
        ];

        return $this;
    }

    /**
     * Configure as OAuth2 authentication.
     *
     * @param array<string, array<string, mixed>> $flows OAuth2 flows configuration
     */
    public function oauth2(array $flows): self
    {
        $this->definition = [
            'type' => 'oauth2',
            'flows' => $flows,
        ];

        return $this;
    }

    /**
     * Configure OAuth2 with Authorization Code flow.
     *
     * @param string $authorizationUrl Authorization endpoint URL
     * @param string $tokenUrl Token endpoint URL
     * @param array<string, string> $scopes Available scopes (name => description)
     * @param string|null $refreshUrl Refresh token URL
     */
    public function oauth2AuthorizationCode(
        string $authorizationUrl,
        string $tokenUrl,
        array $scopes = [],
        ?string $refreshUrl = null,
    ): self {
        $flow = [
            'authorizationUrl' => $authorizationUrl,
            'tokenUrl' => $tokenUrl,
            'scopes' => $scopes,
        ];

        if (null !== $refreshUrl) {
            $flow['refreshUrl'] = $refreshUrl;
        }

        $this->definition = [
            'type' => 'oauth2',
            'flows' => [
                'authorizationCode' => $flow,
            ],
        ];

        return $this;
    }

    /**
     * Configure OAuth2 with Client Credentials flow.
     *
     * @param string $tokenUrl Token endpoint URL
     * @param array<string, string> $scopes Available scopes (name => description)
     * @param string|null $refreshUrl Refresh token URL
     */
    public function oauth2ClientCredentials(
        string $tokenUrl,
        array $scopes = [],
        ?string $refreshUrl = null,
    ): self {
        $flow = [
            'tokenUrl' => $tokenUrl,
            'scopes' => $scopes,
        ];

        if (null !== $refreshUrl) {
            $flow['refreshUrl'] = $refreshUrl;
        }

        $this->definition = [
            'type' => 'oauth2',
            'flows' => [
                'clientCredentials' => $flow,
            ],
        ];

        return $this;
    }

    /**
     * Configure OAuth2 with Implicit flow.
     *
     * @param string $authorizationUrl Authorization endpoint URL
     * @param array<string, string> $scopes Available scopes (name => description)
     * @param string|null $refreshUrl Refresh token URL
     */
    public function oauth2Implicit(
        string $authorizationUrl,
        array $scopes = [],
        ?string $refreshUrl = null,
    ): self {
        $flow = [
            'authorizationUrl' => $authorizationUrl,
            'scopes' => $scopes,
        ];

        if (null !== $refreshUrl) {
            $flow['refreshUrl'] = $refreshUrl;
        }

        $this->definition = [
            'type' => 'oauth2',
            'flows' => [
                'implicit' => $flow,
            ],
        ];

        return $this;
    }

    /**
     * Configure OAuth2 with Password flow.
     *
     * @param string $tokenUrl Token endpoint URL
     * @param array<string, string> $scopes Available scopes (name => description)
     * @param string|null $refreshUrl Refresh token URL
     */
    public function oauth2Password(
        string $tokenUrl,
        array $scopes = [],
        ?string $refreshUrl = null,
    ): self {
        $flow = [
            'tokenUrl' => $tokenUrl,
            'scopes' => $scopes,
        ];

        if (null !== $refreshUrl) {
            $flow['refreshUrl'] = $refreshUrl;
        }

        $this->definition = [
            'type' => 'oauth2',
            'flows' => [
                'password' => $flow,
            ],
        ];

        return $this;
    }

    /**
     * Configure as OpenID Connect authentication.
     *
     * @param string $openIdConnectUrl OpenID Connect discovery URL
     */
    public function openIdConnect(string $openIdConnectUrl): self
    {
        $this->definition = [
            'type' => 'openIdConnect',
            'openIdConnectUrl' => $openIdConnectUrl,
        ];

        return $this;
    }

    /**
     * Set a description for this security scheme.
     *
     * @param string $description Scheme description
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Finish building this security scheme and return to the parent builder.
     */
    public function end(): ApiDocBuilder
    {
        $this->parentBuilder->registerSecurityScheme($this->name, $this->definition);

        return $this->parentBuilder;
    }

    /**
     * Build the security scheme definition as an array.
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
