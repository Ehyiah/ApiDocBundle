<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining OpenAPI tags.
 *
 * Tags are used to group operations in the Swagger UI.
 */
class TagBuilder
{
    private ApiDocBuilder $parentBuilder;

    private string $name;

    private ?string $description = null;

    private ?string $externalDocsUrl = null;

    private ?string $externalDocsDescription = null;

    public function __construct(ApiDocBuilder $parentBuilder, string $name)
    {
        $this->parentBuilder = $parentBuilder;
        $this->name = $name;
    }

    /**
     * Set the tag description.
     *
     * @param string $description Tag description (supports Markdown)
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set external documentation for this tag.
     *
     * @param string $url External documentation URL
     * @param string|null $description External documentation description
     */
    public function externalDocs(string $url, ?string $description = null): self
    {
        $this->externalDocsUrl = $url;
        $this->externalDocsDescription = $description;

        return $this;
    }

    /**
     * Finish building this tag and return to the parent builder.
     */
    public function end(): ApiDocBuilder
    {
        $this->parentBuilder->registerTag($this->buildArray());

        return $this->parentBuilder;
    }

    /**
     * Build the tag definition as an array.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function buildArray(): array
    {
        $tag = ['name' => $this->name];

        if (null !== $this->description) {
            $tag['description'] = $this->description;
        }

        if (null !== $this->externalDocsUrl) {
            $tag['externalDocs'] = ['url' => $this->externalDocsUrl];
            if (null !== $this->externalDocsDescription) {
                $tag['externalDocs']['description'] = $this->externalDocsDescription;
            }
        }

        return $tag;
    }
}
