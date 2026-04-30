<?php
namespace InnoShip\InnoShip\Console;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use InnoShip\InnoShip\Model\Config;
use Magento\Framework\App\Cache\Manager;
use InnoShip\InnoShip\Helper\ExternalSync;
use Magento\Framework\App\State;

class Importexternalid extends Command
{
    /**
     * @var ExternalSync
     */
    protected $externalSync;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Manager
     */
    protected $cacheManager;

    /**
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    protected $configWriter;

    /**
     * @var State
     */
    protected $state;

    /**
     * @param ExternalSync $externalSync
     * @param Config $config
     * @param Manager $cacheManager
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     * @param State $state
     * @param string|null $name
     */
    public function __construct(
        ExternalSync $externalSync,
        Config $config,
        Manager $cacheManager,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        State $state,
        ?string $name = null,
    ) {
        $this->externalSync = $externalSync;
        $this->config = $config;
        $this->cacheManager = $cacheManager;
        $this->configWriter = $configWriter;
        $this->state = $state;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('innoship:externalid');
        $this->setDescription('Import all ExternalID values and sync to database');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area code already set
        }

        $output->writeln("<info>Starting external ID synchronization...</info>");
        $output->writeln("<comment>Truncating existing external locations table...</comment>");

        // Synchronize external locations to database
        $result = $this->externalSync->synchronizeExternalLocations();

        if ($result['success']) {
            $output->writeln("<info>Successfully synchronized {$result['synced']} external locations.</info>");
            if ($result['errors'] > 0) {
                $output->writeln("<comment>Failed to sync {$result['errors']} locations.</comment>");
            }

            // Also update the config value for backwards compatibility
            $apiKey = $this->config->getApiKey();
            $externalIdList = $this->getExternalIdInnoship($apiKey);
            $externalIdString = '';
            foreach ($externalIdList as $externalIDObj) {
                if (isset($externalIDObj['externalLocationId']) && strlen($externalIDObj['externalLocationId']) > 1) {
                    $externalIdString .= $externalIDObj['externalLocationId'] . ",";
                }
            }

            if (strlen($externalIdString) > 0) {
                $externalIdString = substr($externalIdString, 0, -1);
                $this->configWriter->save(
                    'carriers/innoship/external_id_send',
                    $externalIdString,
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0
                );
                $this->cacheManager->clean(["config"]);
                $output->writeln("<info>Config value updated: {$externalIdString}</info>");
                $output->writeln("<info>Config cache cleaned.</info>");
            }
        } else {
            $output->writeln("<error>Synchronization failed: {$result['error']}</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }

    /**
     * Get external location IDs from InnoShip API
     *
     * @param string $apiKey
     * @return array
     */
    private function getExternalIdInnoship($apiKey)
    {
        $url = "https://api.innoship.com/api/Location/ClientLocations";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers = [
            "X-Api-Key: " . $apiKey,
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($curl);
        curl_close($curl);
        return json_decode($resp, true) ?: [];
    }
}
