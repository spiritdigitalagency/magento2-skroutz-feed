<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Spirit\SkroutzFeed\Model\AnalyticsCheck;
use Spirit\SkroutzFeed\Model\Config;
use Spirit\SkroutzFeed\Model\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento spirit:skroutz:feed [--website=<code>]
 */
class Generate extends Command
{
    /**
     * @var AppState
     */
    private $appState;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Generator
     */
    private $generator;

    /**
     * @var AnalyticsCheck
     */
    private $analyticsCheck;

    /**
     * @param AppState $appState
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param Generator $generator
     * @param AnalyticsCheck $analyticsCheck
     */
    public function __construct(
        AppState $appState,
        StoreManagerInterface $storeManager,
        Config $config,
        Generator $generator,
        AnalyticsCheck $analyticsCheck
    ) {
        $this->appState = $appState;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->generator = $generator;
        $this->analyticsCheck = $analyticsCheck;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('spirit:skroutz:feed')
            ->setDescription('Generate the Skroutz XML feed of the websites where it is enabled')
            ->addOption(
                'website',
                'w',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Website code (default: every website with the feed enabled)'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Publish even when the Safety Check finds too few products'
            );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        } catch (LocalizedException $e) {
            // The area is already set when another command runs this one
            unset($e);
        }
        $codes = (array)$input->getOption('website');
        $websites = $codes ? array_map([$this->storeManager, 'getWebsite'], $codes) : array_filter(
            $this->storeManager->getWebsites(),
            function ($website): bool {
                return $this->config->isEnabled((int)$website->getId());
            }
        );
        if (!$websites) {
            $output->writeln('<comment>The Skroutz feed is not enabled in any website.</comment>');
            return 0;
        }
        $failed = false;
        foreach ($websites as $website) {
            $store = $this->config->getStore($website);
            $output->writeln(sprintf('<info>%s</info> (store view %s)', $website->getCode(), $store->getCode()));
            try {
                $report = $this->generator->generate($website, function (int $products) use ($output): void {
                    $output->write(sprintf("\r  %d products", $products));
                }, (bool)$input->getOption('force'));
            } catch (\Throwable $e) {
                $output->writeln(sprintf("\n  <error>%s</error>", $e->getMessage()));
                $failed = true;
                continue;
            }
            $output->writeln(sprintf(
                "\r  %d products, %d variations in %ss, %dMB peak memory",
                $report['products'],
                $report['variations'],
                $report['duration'],
                $report['memory']
            ));
            $output->writeln(sprintf('  %s (%s KB)', $report['url'], round($report['size'] / 1024)));
            foreach ($report['skipped'] as $reason => $count) {
                $output->writeln(sprintf('  not listed, %s: %d', $reason, $count));
            }
            foreach ($report['missing'] as $field => $entry) {
                $output->writeln(sprintf(
                    '  <comment>%s: %d</comment> (%s)',
                    $field,
                    $entry['count'],
                    implode(', ', $entry['samples'])
                ));
            }
            $summary = $this->analyticsCheck->getSummary((int)$store->getId());
            if ($summary !== null) {
                $output->writeln('  ' . $summary);
            }
            foreach ($this->analyticsCheck->getWarnings((int)$store->getId(), $report) as $warning) {
                $output->writeln('  <comment>Skroutz Analytics: ' . $warning . '</comment>');
            }
        }

        return $failed ? 1 : 0;
    }
}
