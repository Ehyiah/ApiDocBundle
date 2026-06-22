<?php

namespace Ehyiah\ApiDocBundle\Attributes;

use Attribute;

/**
 * Identifies a PHP configuration class for API documentation auto-discovery.
 *
 * This attribute is automatically emitted by the PHP code generator so that
 * DI auto-discovery and component lookup can reliably find the class even
 * when the filename does not match the component name (e.g., routes).
 *
 * Usage (auto-generated):
 *   #[ApiDocConfig(component: 'api_doc', type: 'routes')]
 *   class ApiDoc implements ApiDocConfigInterface { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiDocConfig
{
    /**
     * @param string $component The component identifier (e.g., route name, schema name)
     * @param string|null $type The component type (e.g., 'schemas', 'routes', 'parameters')
     */
    public function __construct(
        public readonly string $component,
        public readonly ?string $type = null,
    ) {
    }
}
