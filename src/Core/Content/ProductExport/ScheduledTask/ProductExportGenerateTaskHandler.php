<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class ProductExportGenerateTaskHandler extends ScheduledTaskHandler
{
    private AbstractSalesChannelContextFactory $salesChannelContextFactory;

    private EntityRepository $salesChannelRepository;

    private EntityRepository $productExportRepository;

    private MessageBusInterface $messageBus;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        EntityRepository $salesChannelRepository,
        EntityRepository $productExportRepository,
        MessageBusInterface $messageBus
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->productExportRepository = $productExportRepository;
        $this->messageBus = $messageBus;
    }

    /**
     * @return iterable<class-string>
     */
    public static function getHandledMessages(): iterable
    {
        return [
            ProductExportGenerateTask::class,
        ];
    }

    public function run(): void
    {
        $salesChannelIds = $this->fetchSalesChannelIds();

        foreach ($salesChannelIds as $salesChannelId) {
            $productExports = $this->fetchProductExports($salesChannelId);

            if (\count($productExports) === 0) {
                continue;
            }

            $now = new \DateTimeImmutable('now');

            foreach ($productExports as $productExport) {
                if (!$this->shouldBeRun($productExport, $now)) {
                    continue;
                }

                $this->messageBus->dispatch(
                    new ProductExportPartialGeneration($productExport->getId(), $salesChannelId)
                );
            }
        }
    }

    /**
     * @return array<string>
     */
    private function fetchSalesChannelIds(): array
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->addFilter(new EqualsFilter('active', true));

        /**
         * @var array<string>
         */
        return $this->salesChannelRepository
            ->searchIds($criteria, Context::createDefaultContext())
            ->getIds();
    }

    /**
     * @return array<ProductExportEntity>
     */
    private function fetchProductExports(string $salesChannelId): array
    {
        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

        $criteria = new Criteria();
        $criteria
            ->addAssociation('salesChannel')
            ->addFilter(
                new MultiFilter(
                    'AND',
                    [
                        new EqualsFilter('generateByCronjob', true),
                        new EqualsFilter('salesChannel.active', true),
                    ]
                )
            )
            ->addFilter(
                new MultiFilter(
                    'OR',
                    [
                        new EqualsFilter('storefrontSalesChannelId', $salesChannelId),
                        new EqualsFilter('salesChannelDomain.salesChannel.id', $salesChannelId),
                    ]
                )
            );

        /**
         * @var array<ProductExportEntity>
         */
        return $this->productExportRepository->search($criteria, $salesChannelContext->getContext())->getElements();
    }

    private function shouldBeRun(ProductExportEntity $productExport, \DateTimeImmutable $now): bool
    {
        if ($productExport->getIsRunning()) {
            return false;
        }

        if ($productExport->getGeneratedAt() === null) {
            return true;
        }

        return $now->getTimestamp() - $productExport->getGeneratedAt()->getTimestamp() >= $productExport->getInterval();
    }
}
