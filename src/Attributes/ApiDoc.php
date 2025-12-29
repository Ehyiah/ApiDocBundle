<?php

namespace Ehyiah\ApiDocBundle\Attributes;

use Attribute;

/**
 * Link a controller or method to its Fluent PHP API documentation configuration class.
 *
 * Usage:
 *   #[ApiDoc(UserApiDocConfig::class)]
 *   #[ApiDoc(UserApiDocConfig::class, 'configureGetUser')]
 *
 * Ctrl+Click on the class reference in your IDE to navigate to the documentation.
 *
 * @template T of \Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class ApiDoc
{
    /**
     * @param class-string<T> $configClass The API documentation configuration class
     * @param string|null $method Optional method name in the config class for more precise navigation
     */
    public function __construct(
        public readonly string $configClass,
        public readonly ?string $method = null,
    ) {
    }
}
