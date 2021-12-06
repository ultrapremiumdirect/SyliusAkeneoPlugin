<?php

declare(strict_types=1);

namespace Synolia\SyliusAkeneoPlugin\Task\ProductModel;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Synolia\SyliusAkeneoPlugin\Entity\ProductGroup;
use Synolia\SyliusAkeneoPlugin\Event\Product\AfterProcessingProductEvent;
use Synolia\SyliusAkeneoPlugin\Event\Product\BeforeProcessingProductEvent;
use Synolia\SyliusAkeneoPlugin\Logger\Messages;
use Synolia\SyliusAkeneoPlugin\Payload\PipelinePayloadInterface;
use Synolia\SyliusAkeneoPlugin\Payload\ProductModel\ProductModelPayload;
use Synolia\SyliusAkeneoPlugin\Processor\Product\ProductProcessorChainInterface;
use Synolia\SyliusAkeneoPlugin\Repository\ProductGroupRepository;
use Synolia\SyliusAkeneoPlugin\Task\AbstractBatchTask;

final class BatchProductModelTask extends AbstractBatchTask
{
    private const ONE_VARIATION_AXIS = 1;

    private ProductRepositoryInterface $productRepository;

    private ProductFactoryInterface $productFactory;

    private ProductGroupRepository $productGroupRepository;

    private LoggerInterface $logger;

    private string $type;

    private EventDispatcherInterface $dispatcher;

    private ProductProcessorChainInterface $productProcessorChain;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ProductFactoryInterface $productFactory,
        ProductRepositoryInterface $productRepository,
        ProductGroupRepository $productGroupRepository,
        LoggerInterface $akeneoLogger,
        EventDispatcherInterface $dispatcher,
        ProductProcessorChainInterface $productProcessorChain
    ) {
        parent::__construct($entityManager);

        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productGroupRepository = $productGroupRepository;
        $this->logger = $akeneoLogger;
        $this->dispatcher = $dispatcher;
        $this->productProcessorChain = $productProcessorChain;
    }

    /**
     * @param ProductModelPayload $payload
     */
    public function __invoke(PipelinePayloadInterface $payload): PipelinePayloadInterface
    {
        $this->logger->debug(self::class);
        $this->type = $payload->getType();
        $this->logger->notice(Messages::createOrUpdate($this->type));

        $query = $this->getSelectStatement($payload);
        $query->executeStatement();

        while ($results = $query->fetchAll()) {
            foreach ($results as $result) {
                $resource = json_decode($result['values'], true);

                try {
                    $this->dispatcher->dispatch(new BeforeProcessingProductEvent($resource));

                    $this->entityManager->beginTransaction();
                    $product = $this->process($payload, $resource);

                    $this->dispatcher->dispatch(new AfterProcessingProductEvent($resource, $product));

                    $this->entityManager->flush();
                    $this->entityManager->commit();
                    $this->entityManager->clear();

                    unset($resource, $product);
                    $this->removeEntry($payload, (int) $result['id']);
                } catch (\Throwable $throwable) {
                    $this->entityManager->rollback();
                    $this->logger->warning($throwable->getMessage());
                    $this->removeEntry($payload, (int) $result['id']);
                }
            }
        }

        return $payload;
    }

    private function process(PipelinePayloadInterface $payload, array &$resource): ProductInterface
    {
        if ('' === $resource['code'] || null === $resource['code']) {
            throw new \LogicException('Attribute code is missing.');
        }

        $product = $this->productRepository->findOneByCode($resource['code']);

        if (!$product instanceof ProductInterface) {
            /** @var ProductInterface $product */
            $product = $this->productFactory->createNew();
            $product->setCode($resource['code']);

            $this->entityManager->persist($product);
            $this->addOrUpdate($payload, $product, $resource);

            $this->logger->info(Messages::hasBeenCreated($this->type, (string) $product->getCode()));

            return $product;
        }

        $this->addOrUpdate($payload, $product, $resource);
        $this->logger->info(Messages::hasBeenUpdated($this->type, (string) $resource['code']));

        return $product;
    }

    private function addOrUpdate(PipelinePayloadInterface $payload, ProductInterface $product, array &$resource): void
    {
        if (!isset($resource['family'])) {
            throw new \LogicException('Missing family attribute on product');
        }

        $payloadProductGroup = $payload->getAkeneoPimClient()->getFamilyVariantApi()->get(
            $resource['family'],
            $resource['family_variant']
        );

        $numberOfVariationAxis = isset($payloadProductGroup['variant_attribute_sets']) ? \count($payloadProductGroup['variant_attribute_sets']) : 0;

        if (null === $resource['parent'] && $numberOfVariationAxis > self::ONE_VARIATION_AXIS) {
            return;
        }

        $this->productProcessorChain->chain($product, $resource);
        $this->addProductGroup($resource, $product);
    }

    private function addProductGroup(array &$resource, ProductInterface $product): void
    {
        $productGroup = $this->productGroupRepository->findOneBy(['productParent' => $resource['parent']]);

        if ($productGroup instanceof ProductGroup && 0 === $this->productGroupRepository->isProductInProductGroup($product, $productGroup)) {
            $productGroup->addProduct($product);
        }
    }
}
