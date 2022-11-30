<?php

declare(strict_types=1);

namespace Synolia\SyliusAkeneoPlugin\Processor\ProductAttribute;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Synolia\SyliusAkeneoPlugin\Component\Attribute\AttributeType\AssetAttributeType;
use Synolia\SyliusAkeneoPlugin\Entity\AssetInterface;
use Synolia\SyliusAkeneoPlugin\Exceptions\Attribute\MissingLocaleTranslationException;
use Synolia\SyliusAkeneoPlugin\Exceptions\Attribute\MissingLocaleTranslationOrScopeException;
use Synolia\SyliusAkeneoPlugin\Exceptions\Attribute\MissingScopeException;
use Synolia\SyliusAkeneoPlugin\Exceptions\Attribute\TranslationNotFoundException;
use Synolia\SyliusAkeneoPlugin\Provider\AkeneoAttributeDataProviderInterface;
use Synolia\SyliusAkeneoPlugin\Provider\AkeneoAttributePropertiesProvider;
use Synolia\SyliusAkeneoPlugin\Provider\SyliusAkeneoLocaleCodeProvider;
use Synolia\SyliusAkeneoPlugin\Transformer\AkeneoAttributeToSyliusAttributeTransformerInterface;

/**
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
final class AssetAttributeProcessor implements AkeneoAttributeProcessorInterface
{
    private SyliusAkeneoLocaleCodeProvider $syliusAkeneoLocaleCodeProvider;

    private AkeneoAttributeToSyliusAttributeTransformerInterface $akeneoAttributeToSyliusAttributeTransformer;

    private RepositoryInterface $productAttributeRepository;

    private LoggerInterface $logger;

    private AkeneoAttributePropertiesProvider $akeneoAttributePropertiesProvider;

    private RepositoryInterface $akeneoAssetRepository;

    private EntityManagerInterface $entityManager;

    private RepositoryInterface $productAttributeValueRepository;

    private FactoryInterface $productAttributeValueFactory;

    private AkeneoAttributeDataProviderInterface $akeneoAttributeDataProvider;

    public function __construct(
        SyliusAkeneoLocaleCodeProvider $syliusAkeneoLocaleCodeProvider,
        AkeneoAttributeToSyliusAttributeTransformerInterface $akeneoAttributeToSyliusAttributeTransformer,
        RepositoryInterface $productAttributeRepository,
        LoggerInterface $akeneoLogger,
        AkeneoAttributePropertiesProvider $akeneoAttributePropertiesProvider,
        RepositoryInterface $akeneoAssetRepository,
        EntityManagerInterface $entityManager,
        RepositoryInterface $productAttributeValueRepository,
        FactoryInterface $productAttributeValueFactory,
        AkeneoAttributeDataProviderInterface $akeneoAttributeDataProvider
    ) {
        $this->syliusAkeneoLocaleCodeProvider = $syliusAkeneoLocaleCodeProvider;
        $this->akeneoAttributeToSyliusAttributeTransformer = $akeneoAttributeToSyliusAttributeTransformer;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->logger = $akeneoLogger;
        $this->akeneoAttributePropertiesProvider = $akeneoAttributePropertiesProvider;
        $this->akeneoAssetRepository = $akeneoAssetRepository;
        $this->entityManager = $entityManager;
        $this->productAttributeValueRepository = $productAttributeValueRepository;
        $this->productAttributeValueFactory = $productAttributeValueFactory;
        $this->akeneoAttributeDataProvider = $akeneoAttributeDataProvider;
    }

    public static function getDefaultPriority(): int
    {
        return 100;
    }

    public function support(string $attributeCode, array $context = []): bool
    {
        $transformedAttributeCode = $this->akeneoAttributeToSyliusAttributeTransformer->transform($attributeCode);

        /** @var AttributeInterface $attribute */
        $attribute = $this->productAttributeRepository->findOneBy(['code' => $transformedAttributeCode]);

        if ($attribute instanceof AttributeInterface && $attribute->getType() === AssetAttributeType::TYPE) {
            return true;
        }

        return false;
    }

    public function process(string $attributeCode, array $context = []): void
    {
        $this->logger->debug(\sprintf(
            'Attribute "%s" is being processed by "%s"',
            $attributeCode,
            static::class
        ));

        if (!$context['model'] instanceof ProductInterface) {
            return;
        }

        $transformedAttributeCode = $this->akeneoAttributeToSyliusAttributeTransformer->transform($attributeCode);

        /** @var AttributeInterface $attribute */
        $attribute = $this->productAttributeRepository->findOneBy(['code' => $transformedAttributeCode]);

        foreach ($context['data'] as $translation) {
            if (null !== $translation['locale'] && false === $this->syliusAkeneoLocaleCodeProvider->isActiveLocale($translation['locale'])) {
                continue;
            }

            if (null === $translation['locale']) {
                foreach ($this->syliusAkeneoLocaleCodeProvider->getUsedLocalesOnBothPlatforms() as $locale) {
                    try {
                        $this->setAttributeTranslation(
                            $context['model'],
                            $attribute,
                            $context['data'],
                            $locale,
                            $attributeCode,
                            $context['scope']
                        );
                    } catch (TranslationNotFoundException|MissingScopeException|MissingLocaleTranslationOrScopeException|MissingLocaleTranslationException $e) {
                    }
                }

                continue;
            }

            try {
                $this->setAttributeTranslation(
                    $context['model'],
                    $attribute,
                    $context['data'],
                    $translation['locale'],
                    $attributeCode,
                    $context['scope']
                );
            } catch (TranslationNotFoundException|MissingScopeException|MissingLocaleTranslationOrScopeException|MissingLocaleTranslationException $e) {
            }
        }

        $assetAttributeProperties = $this->akeneoAttributePropertiesProvider->getProperties($attributeCode);

        foreach ($context['data'] as $assetCodes) {
            foreach ($this->syliusAkeneoLocaleCodeProvider->getUsedLocalesOnBothPlatforms() as $locale) {
                foreach ($assetCodes['data'] as $assetCode) {
                    $asset = $this->akeneoAssetRepository->findOneBy([
                        'familyCode' => $assetAttributeProperties['reference_data_name'],
                        'assetCode' => $assetCode,
                        'scope' => $context['scope'],
                        'locale' => $locale,
                    ]);

                    if (!$asset instanceof AssetInterface) {
                        continue;
                    }

                    $asset->addOwner($context['model']);
                }
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @throws MissingLocaleTranslationOrScopeException
     * @throws MissingLocaleTranslationException
     * @throws MissingScopeException
     * @throws TranslationNotFoundException
     */
    private function setAttributeTranslation(
        ProductInterface $product,
        AttributeInterface $attribute,
        array $translations,
        string $locale,
        string $attributeCode,
        string $scope
    ): void {
        $attributeValue = $this->productAttributeValueRepository->findOneBy([
            'subject' => $product,
            'attribute' => $attribute,
            'localeCode' => $locale,
        ]);

        if (!$attributeValue instanceof ProductAttributeValueInterface) {
            /** @var ProductAttributeValueInterface $attributeValue */
            $attributeValue = $this->productAttributeValueFactory->createNew();
        }

        $attributeValue->setLocaleCode($locale);
        $attributeValue->setAttribute($attribute);
        $attributeValueValue = $this->akeneoAttributeDataProvider->getData($attributeCode, $translations, $locale, $scope);
        $attributeValue->setValue($attributeValueValue);
        $product->addAttribute($attributeValue);
    }
}