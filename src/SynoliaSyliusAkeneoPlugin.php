<?php

declare(strict_types=1);

namespace Synolia\SyliusAkeneoPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Synolia\SyliusAkeneoPlugin\Builder\Asset\Attribute\AssetAttributeValueBuilderInterface;
use Synolia\SyliusAkeneoPlugin\Builder\Attribute\ProductAttributeValueValueBuilderInterface;
use Synolia\SyliusAkeneoPlugin\Builder\ProductOptionValue\DynamicOptionValueBuilderInterface;
use Synolia\SyliusAkeneoPlugin\Builder\ProductOptionValueTranslation\ProductOptionValueTranslationBuilderInterface;
use Synolia\SyliusAkeneoPlugin\DependencyInjection\Compiler\AkeneoAssetAttributeTypeMatcherCompilerPass;
use Synolia\SyliusAkeneoPlugin\DependencyInjection\Compiler\AkeneoAssetAttributeValueBuilderCompilerPass;
use Synolia\SyliusAkeneoPlugin\DependencyInjection\Compiler\AkeneoAttributeTypeMatcherCompilerPass;
use Synolia\SyliusAkeneoPlugin\DependencyInjection\Compiler\AkeneoAttributeValueValueBuilderCompilerPass;
use Synolia\SyliusAkeneoPlugin\DependencyInjection\Compiler\AkeneoDataMigrationTransformerCompilerPass;
use Synolia\SyliusAkeneoPlugin\DependencyInjection\Compiler\AkeneoReferenceentityAttributeTypeMatcherCompilerPass;
use Synolia\SyliusAkeneoPlugin\DependencyInjection\Compiler\AkeneoTaskCompilerPass;
use Synolia\SyliusAkeneoPlugin\Processor\Product\ProductProcessorInterface;
use Synolia\SyliusAkeneoPlugin\Processor\ProductAttribute\AkeneoAttributeProcessorInterface;
use Synolia\SyliusAkeneoPlugin\Processor\ProductOptionValue\OptionValuesProcessorInterface;
use Synolia\SyliusAkeneoPlugin\Processor\ProductVariant\ProductVariantProcessorInterface;
use Synolia\SyliusAkeneoPlugin\Task\AkeneoTaskInterface;
use Synolia\SyliusAkeneoPlugin\Transformer\DataMigration\DataMigrationTransformerInterface;
use Synolia\SyliusAkeneoPlugin\TypeMatcher\Asset\Attribute\AssetAttributeTypeMatcherInterface;
use Synolia\SyliusAkeneoPlugin\TypeMatcher\Attribute\AttributeTypeMatcherInterface;
use Synolia\SyliusAkeneoPlugin\TypeMatcher\ReferenceEntityAttribute\ReferenceEntityAttributeTypeMatcherInterface;

final class SynoliaSyliusAkeneoPlugin extends Bundle implements \Stringable
{
    use SyliusPluginTrait;

    public const VERSION = '3.1.1';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container
            ->registerForAutoconfiguration(AkeneoTaskInterface::class)
            ->addTag(AkeneoTaskInterface::TAG_ID)
        ;
        $container->addCompilerPass(new AkeneoTaskCompilerPass());
        $container
            ->registerForAutoconfiguration(AttributeTypeMatcherInterface::class)
            ->addTag(AttributeTypeMatcherInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(ReferenceEntityAttributeTypeMatcherInterface::class)
            ->addTag(ReferenceEntityAttributeTypeMatcherInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(ProductAttributeValueValueBuilderInterface::class)
            ->addTag(ProductAttributeValueValueBuilderInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(AssetAttributeValueBuilderInterface::class)
            ->addTag(AssetAttributeValueBuilderInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(AssetAttributeTypeMatcherInterface::class)
            ->addTag(AssetAttributeTypeMatcherInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(DataMigrationTransformerInterface::class)
            ->addTag(DataMigrationTransformerInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(ProductOptionValueTranslationBuilderInterface::class)
            ->addTag(ProductOptionValueTranslationBuilderInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(DynamicOptionValueBuilderInterface::class)
            ->addTag(DynamicOptionValueBuilderInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(AkeneoAttributeProcessorInterface::class)
            ->addTag(AkeneoAttributeProcessorInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(ProductProcessorInterface::class)
            ->addTag(ProductProcessorInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(ProductVariantProcessorInterface::class)
            ->addTag(ProductVariantProcessorInterface::TAG_ID)
        ;
        $container
            ->registerForAutoconfiguration(OptionValuesProcessorInterface::class)
            ->addTag(OptionValuesProcessorInterface::TAG_ID)
        ;

        $container->addCompilerPass(new AkeneoAttributeTypeMatcherCompilerPass());
        $container->addCompilerPass(new AkeneoReferenceentityAttributeTypeMatcherCompilerPass());
        $container->addCompilerPass(new AkeneoAttributeValueValueBuilderCompilerPass());
        $container->addCompilerPass(new AkeneoAssetAttributeValueBuilderCompilerPass());
        $container->addCompilerPass(new AkeneoAssetAttributeTypeMatcherCompilerPass());
        $container->addCompilerPass(new AkeneoDataMigrationTransformerCompilerPass());
    }

    public function __toString(): string
    {
        return 'SynoliaSyliusAkeneoPlugin';
    }
}
