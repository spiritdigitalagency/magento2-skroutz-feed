<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

/**
 * Generates the Skroutz XML feed of a website into pub/media/skroutz/<file name>.xml (and .xml.gz).
 *
 * The feed online is replaced only by a complete and checked one: both files are written under
 * temporary names, read back in full (well-formed XML, every product there), compared with the feed
 * online by the "Safety Check", and only then renamed over it. A failure at any point, including a
 * killed process or a full disk, leaves the previous feed in place.
 */
class Generator
{
    public const DIRECTORY = 'skroutz';

    /** Without these the product cannot be listed: the row is skipped */
    private const ESSENTIAL = ['id', 'link', 'price_with_vat', 'availability', 'quantity'];

    /** Required by Skroutz: the row is written, and the report lists the products missing them */
    private const REQUIRED = ['name', 'image', 'category', 'manufacturer', 'mpn', 'ean', 'description'];

    /** Product SKUs listed per problem in the report */
    private const SAMPLES = 10;

    /**
     * @var ProductLoader
     */
    private $loader;

    /**
     * @var RowBuilder
     */
    private $rowBuilder;

    /**
     * @var Writer
     */
    private $writer;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AttributeValue
     */
    private $attributeValue;

    /**
     * @var CategoryTree
     */
    private $categoryTree;

    /**
     * @var State
     */
    private $state;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var LockManagerInterface
     */
    private $lockManager;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var NotifierInterface
     */
    private $notifier;

    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var int
     */
    private $maxChildren;

    /**
     * @param ProductLoader $loader
     * @param RowBuilder $rowBuilder
     * @param Writer $writer
     * @param Config $config
     * @param AttributeValue $attributeValue
     * @param CategoryTree $categoryTree
     * @param State $state
     * @param Filesystem $filesystem
     * @param LockManagerInterface $lockManager
     * @param Emulation $emulation
     * @param TimezoneInterface $timezone
     * @param EventManager $eventManager
     * @param LoggerInterface $logger
     * @param NotifierInterface $notifier
     * @param int $batchSize products per query
     * @param int $maxChildren configurable children held in memory at once
     */
    public function __construct(
        ProductLoader $loader,
        RowBuilder $rowBuilder,
        Writer $writer,
        Config $config,
        AttributeValue $attributeValue,
        CategoryTree $categoryTree,
        State $state,
        Filesystem $filesystem,
        LockManagerInterface $lockManager,
        Emulation $emulation,
        TimezoneInterface $timezone,
        EventManager $eventManager,
        LoggerInterface $logger,
        NotifierInterface $notifier,
        int $batchSize = 500,
        int $maxChildren = 2000
    ) {
        $this->loader = $loader;
        $this->rowBuilder = $rowBuilder;
        $this->writer = $writer;
        $this->config = $config;
        $this->attributeValue = $attributeValue;
        $this->categoryTree = $categoryTree;
        $this->state = $state;
        $this->filesystem = $filesystem;
        $this->lockManager = $lockManager;
        $this->emulation = $emulation;
        $this->timezone = $timezone;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
        $this->notifier = $notifier;
        $this->batchSize = $batchSize;
        $this->maxChildren = $maxChildren;
    }

    /**
     * Generate the feed of a website and save its report.
     *
     * @param WebsiteInterface $website
     * @param callable|null $progress called with the number of products written so far
     * @param bool $force publish even when the Safety Check fails
     * @return array<string, mixed> the report
     * @throws LocalizedException when the feed of the website is already being generated
     * @throws \Throwable when generation fails; the previous feed is kept
     */
    public function generate(WebsiteInterface $website, ?callable $progress = null, bool $force = false): array
    {
        $websiteId = (int)$website->getId();
        $lock = 'spirit_skroutzfeed_' . $websiteId;
        if (!$this->lockManager->lock($lock, 0)) {
            throw new LocalizedException(
                __('The Skroutz feed of website "%1" is already being generated.', $website->getCode())
            );
        }
        $store = $this->config->getStore($website);
        $storeId = (int)$store->getId();
        $started = microtime(true);
        $report = [
            'website' => $website->getCode(),
            'store' => $store->getCode(),
            'started' => time(),
            'products' => 0,
            'variations' => 0,
            'modes' => [],
            'skipped' => [],
            'missing' => [],
            'error' => null,
        ];
        $previous = $this->state->getReport($websiteId) ?? [];
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $name = $this->config->getFileName($website) . '.xml';
        $file = self::DIRECTORY . '/' . $name;
        $temporary = self::DIRECTORY . '/.' . $name . '.tmp';
        $gzTemporary = self::DIRECTORY . '/.' . $name . '.gz.tmp';
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            $clash = $this->config->getFileNameClash($website);
            if ($clash) {
                throw new LocalizedException(__(
                    'Website "%1" writes its feed to the same file, %2: give each website its own file name.',
                    $clash->getCode(),
                    $name
                ));
            }
            $this->config->setStore($storeId);
            $this->attributeValue->setStore($storeId);
            $this->categoryTree->load($store);
            $media->create(self::DIRECTORY);
            $this->writer->open(
                $media->getAbsolutePath($temporary),
                $this->timezone->date()->format('Y-m-d H:i')
            );
            $this->writeProducts($store, $report, $progress);
            $this->writer->close();
            $this->gzip($media, $temporary, $gzTemporary);
            // Read back what is on disk: a truncated write (full disk) or a broken file fails here
            $this->verify($media->getAbsolutePath($temporary), $report['products']);
            $this->verify('compress.zlib://' . $media->getAbsolutePath($gzTemporary), $report['products']);
            if (!$force) {
                $this->checkDrop($report['products'], (int)($previous['published'] ?? 0));
            }
            $media->renameFile($gzTemporary, $file . '.gz');
            $media->renameFile($temporary, $file);
            /** @var Store $store */
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true);
            $report += [
                'published' => $report['products'],
                'url' => $baseUrl . $file,
                'gz_url' => $baseUrl . $file . '.gz',
                'size' => (int)($media->stat($file)['size'] ?? 0),
                'gz_size' => (int)($media->stat($file . '.gz')['size'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->writer->abort();
            foreach ([$temporary, $gzTemporary] as $path) {
                if ($media->isExist($path)) {
                    $media->delete($path);
                }
            }
            // The previous feed is still online: keep showing it
            $report += array_intersect_key($previous, array_flip(['published', 'url', 'gz_url', 'size', 'gz_size']));
            $report['error'] = $e->getMessage();
            $this->logger->error('Skroutz feed of ' . $website->getCode() . ' failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $this->notifier->addMajor(
                (string)__('The Skroutz feed of website "%1" failed', $website->getCode()),
                $e->getMessage()
            );
            throw $e;
        } finally {
            $this->emulation->stopEnvironmentEmulation();
            $report['duration'] = round(microtime(true) - $started, 1);
            $report['memory'] = round(memory_get_peak_usage(true) / 1048576);
            $this->state->saveReport($websiteId, $report);
            $this->lockManager->unlock($lock);
        }
        $this->eventManager->dispatch('spirit_skroutzfeed_generate_after', [
            'website' => $website,
            'store' => $store,
            'report' => $report,
        ]);

        return $report;
    }

    /**
     * Write every product of the website.
     *
     * @param StoreInterface $store
     * @param mixed[] $report
     * @param callable|null $progress
     * @return void
     */
    private function writeProducts(StoreInterface $store, array &$report, ?callable $progress): void
    {
        $ids = $variationIds = [];
        foreach ($this->loader->load($store, $this->batchSize, $this->maxChildren) as [$products, $children]) {
            foreach ($products as $id => $product) {
                if (!$this->categoryTree->isAllowed($product->getData(CategoryTree::KEY) ?: [])) {
                    $this->count($report['skipped'], 'category_filter');
                    continue;
                }
                $rows = $this->rowBuilder->build($product, $children[$id] ?? []);
                if (!$rows) {
                    $this->count($report['skipped'], 'hidden_availability');
                    continue;
                }
                foreach ($rows as $row) {
                    $transport = new DataObject(['row' => $row, 'skip' => false]);
                    $this->eventManager->dispatch('spirit_skroutzfeed_row', [
                        'transport' => $transport,
                        'product' => $product,
                        'store' => $store,
                    ]);
                    $row = $transport->getData('row');
                    if ($transport->getData('skip')) {
                        $this->count($report['skipped'], 'by_extension');
                        continue;
                    }
                    if (!$this->check($row, $report, $ids, $variationIds)) {
                        continue;
                    }
                    $this->writer->write($row);
                    $report['products']++;
                    $report['variations'] += count($row['variations'] ?? []);
                    $this->count($report['modes'], (string)($row['_mode'] ?? 'simple'));
                }
            }
            $this->writer->flush();
            if ($progress) {
                $progress($report['products']);
            }
        }
    }

    /**
     * Report the problems of a row; false when it cannot be written.
     *
     * @param mixed[] $row
     * @param mixed[] $report
     * @param bool[] $ids Unique IDs written so far
     * @param bool[] $variationIds variation Unique IDs written so far
     * @return bool
     */
    private function check(array &$row, array &$report, array &$ids, array &$variationIds): bool
    {
        $sku = (string)($row['_sku'] ?? '');
        $essential = self::ESSENTIAL;
        if ($this->config->get('feed_products/exclude_no_image')) {
            // Checked on the row: a configurable without an image of its own may take one from its children
            $essential[] = 'image';
        }
        foreach ($essential as $field) {
            if (($row[$field] ?? null) === null || $row[$field] === '') {
                $this->miss($report, $field, $sku, 'skipped');
                return false;
            }
        }
        if (isset($ids[$row['id']])) {
            // Skroutz updates only the first product of a Unique ID
            $this->miss($report, 'id', $sku, 'duplicate');
            return false;
        }
        $ids[$row['id']] = true;
        foreach (self::REQUIRED as $field) {
            if (($row[$field] ?? null) === null || $row[$field] === '') {
                $this->miss($report, $field, $sku);
            }
        }
        foreach ($row['variations'] ?? [] as $key => $variation) {
            $variationId = $variation['variationid'] ?? '';
            if ($variationId === '' || isset($variationIds[$variationId])) {
                $this->miss($report, 'variationid', $sku, 'duplicate');
                unset($row['variations'][$key]);
                continue;
            }
            $variationIds[$variationId] = true;
        }

        return true;
    }

    /**
     * Count a product missing a field.
     *
     * @param mixed[] $report
     * @param string $field
     * @param string $sku
     * @param string $type "missing", "skipped" (missing and not written) or "duplicate"
     * @return void
     */
    private function miss(array &$report, string $field, string $sku, string $type = 'missing'): void
    {
        $key = $type === 'missing' ? $field : $field . ':' . $type;
        $entry = $report['missing'][$key] ?? ['count' => 0, 'samples' => []];
        $entry['count']++;
        if ($sku !== '' && count($entry['samples']) < self::SAMPLES && !in_array($sku, $entry['samples'], true)) {
            $entry['samples'][] = $sku;
        }
        $report['missing'][$key] = $entry;
        if ($type === 'skipped') {
            $this->count($report['skipped'], 'missing_' . $field);
        }
    }

    /**
     * Increment a counter.
     *
     * @param int[] $counters
     * @param string $key
     * @return void
     */
    private function count(array &$counters, string $key): void
    {
        $counters[$key] = ($counters[$key] ?? 0) + 1;
    }

    /**
     * Write the gzip copy of a feed: Skroutz requires compression above 10MB.
     *
     * @param WriteInterface $media
     * @param string $file
     * @param string $gzFile
     * @return void
     */
    private function gzip(WriteInterface $media, string $file, string $gzFile): void
    {
        $in = $media->openFile($file, 'r');
        $out = $media->openFile($gzFile, 'w');
        $deflate = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
        if ($deflate === false) {
            throw new LocalizedException(__('The gzip copy of the feed cannot be written.'));
        }
        do {
            $eof = $in->eof();
            $chunk = deflate_add($deflate, $eof ? '' : $in->read(1048576), $eof ? ZLIB_FINISH : ZLIB_NO_FLUSH);
            if ($chunk === false) {
                throw new LocalizedException(__('The gzip copy of the feed cannot be written.'));
            }
            $out->write($chunk);
        } while (!$eof);
        $in->close();
        $out->close();
    }

    /**
     * Read a written feed back in full: it must be well-formed XML holding every product written.
     *
     * @param string $path
     * @param int $products
     * @return void
     * @throws LocalizedException
     */
    private function verify(string $path, int $products): void
    {
        $reader = new \XMLReader();
        $useErrors = libxml_use_internal_errors(true);
        $found = 0;
        try {
            if (!$reader->open($path, null, LIBXML_NONET)) {
                throw new LocalizedException(__('The new feed cannot be read back; the previous one is kept.'));
            }
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->depth === 2 && $reader->name === 'product') {
                    $found++;
                }
            }
            $error = libxml_get_last_error();
            $reader->close();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);
        }
        if ($error) {
            throw new LocalizedException(
                __('The new feed is not valid XML (%1); the previous one is kept.', trim($error->message))
            );
        }
        if ($found !== $products) {
            throw new LocalizedException(__(
                'The new feed holds %1 of the %2 products written; the previous one is kept.',
                $found,
                $products
            ));
        }
    }

    /**
     * The Safety Check: a feed losing too many products at once is not published.
     *
     * @param int $products products in the new feed
     * @param int $published products in the feed online
     * @return void
     * @throws LocalizedException
     */
    private function checkDrop(int $products, int $published): void
    {
        $maxDrop = (int)$this->config->get('feed/max_drop');
        if ($maxDrop > 0 && $published > 0 && $products < $published * (100 - $maxDrop) / 100) {
            throw new LocalizedException(__(
                'The new feed has %1 products against %2 in the feed online, more than %3 percent fewer, so it '
                . 'was not published and Skroutz keeps the previous one. If the drop is expected, generate it '
                . 'with "bin/magento spirit:skroutz:feed --force", or lower the Safety Check.',
                $products,
                $published,
                $maxDrop
            ));
        }
    }
}
