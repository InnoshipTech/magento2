<?php

namespace InnoShip\InnoShip\Cron;

use InnoShip\InnoShip\Helper\ExternalSync;
use Psr\Log\LoggerInterface;

class ExternalIdSync
{
    /**
     * @var ExternalSync
     */
    protected $externalSync;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ExternalSync $externalSync
     * @param LoggerInterface $logger
     */
    public function __construct(
        ExternalSync $externalSync,
        LoggerInterface $logger
    ) {
        $this->externalSync = $externalSync;
        $this->logger = $logger;
    }

    /**
     * Execute the cron job
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info('InnoShip External ID Sync cron started');

        try {
            $result = $this->externalSync->synchronizeExternalLocations();

            if ($result['success']) {
                $this->logger->info(
                    "InnoShip External ID Sync completed successfully. " .
                    "Synced: {$result['synced']}, Errors: {$result['errors']}, Total: {$result['total']}"
                );
            } else {
                $this->logger->error(
                    "InnoShip External ID Sync failed: {$result['error']}"
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'InnoShip External ID Sync cron error: ' . $e->getMessage()
            );
        }

        $this->logger->info('InnoShip External ID Sync cron finished');
    }
}
