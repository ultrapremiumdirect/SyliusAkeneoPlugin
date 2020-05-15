<?php

declare(strict_types=1);

namespace Synolia\SyliusAkeneoPlugin\Builder;

use Synolia\SyliusAkeneoPlugin\Provider\AkeneoAttributePropertiesProvider;
use Synolia\SyliusAkeneoPlugin\TypeMatcher\Attribute\AttributeTypeMatcher;
use Synolia\SyliusAkeneoPlugin\TypeMatcher\Attribute\BooleanAttributeTypeMatcher;

final class BooleanProductAttributeValueValueBuilder implements ProductAttributeValueValueBuilderInterface
{
    /**
     * @var \Synolia\SyliusAkeneoPlugin\Provider\AkeneoAttributePropertiesProvider
     */
    private $akeneoAttributePropertiesProvider;
    /**
     * @var \Synolia\SyliusAkeneoPlugin\TypeMatcher\Attribute\AttributeTypeMatcher
     */
    private $attributeTypeMatcher;

    public function __construct(
        AkeneoAttributePropertiesProvider $akeneoAttributePropertiesProvider,
        AttributeTypeMatcher $attributeTypeMatcher
    ) {
        $this->akeneoAttributePropertiesProvider = $akeneoAttributePropertiesProvider;
        $this->attributeTypeMatcher = $attributeTypeMatcher;
    }

    public function support(string $attributeCode): bool
    {
        return $this->attributeTypeMatcher->match($this->akeneoAttributePropertiesProvider->getType($attributeCode)) instanceof BooleanAttributeTypeMatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function build($value)
    {
        return (bool) $value;
    }
}
