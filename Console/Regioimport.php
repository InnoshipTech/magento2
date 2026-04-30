<?php

namespace InnoShip\InnoShip\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\DirectoryList;
use InnoShip\InnoShip\Model\Config;

class Regioimport extends Command
{
    const ARGUMENT_COUNTRY_CODE = 'country_code';
    const OPTION_RESTORE = 'restore';

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var string
     */
    protected $backupDir;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param State $state
     * @param DirectoryList $directoryList
     * @param string|null $name
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Config $config,
        State $state,
        DirectoryList $directoryList,
        ?string $name = null,
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->config = $config;
        $this->state = $state;
        $this->directoryList = $directoryList;
        $this->backupDir = $this->directoryList->getRoot() . '/app/code/InnoShip/InnoShip/RegionBackup';
        parent::__construct($name);
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('innoship:regioimport');
        $this->setDescription('Import regions and cities for a specific country from InnoShip API');
        $this->addArgument(
            self::ARGUMENT_COUNTRY_CODE,
            InputArgument::REQUIRED,
            'Country code (e.g., PL, RO, etc.)'
        );
        $this->addOption(
            self::OPTION_RESTORE,
            'r',
            InputOption::VALUE_NONE,
            'Restore regions from backup instead of importing'
        );

        parent::configure();
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area code already set
        }

        $countryCode = strtoupper(trim($input->getArgument(self::ARGUMENT_COUNTRY_CODE)));

        // SECURITY FIX: Validate country code format (ISO 3166-1 alpha-2)
        if (!$this->isValidCountryCode($countryCode)) {
            $output->writeln("<error>Invalid country code format. Must be a 2-letter ISO code (e.g., RO, PL, DE).</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        // SECURITY FIX: Validate against list of supported countries
        $supportedCountries = ['RO', 'PL', 'BG', 'HU', 'CZ', 'SK', 'DE', 'AT', 'IT', 'FR', 'ES', 'GB', 'NL', 'BE', 'PT', 'GR'];
        if (!in_array($countryCode, $supportedCountries, true)) {
            $output->writeln("<error>Unsupported country code: {$countryCode}. Supported: " . implode(', ', $supportedCountries) . "</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        $isRestore = $input->getOption(self::OPTION_RESTORE);

        // SECURITY FIX: Validate backup directory path before creating
        $realBackupDir = realpath(dirname($this->backupDir));
        $expectedBase = realpath($this->directoryList->getRoot() . '/app/code/InnoShip/InnoShip');

        if ($realBackupDir === false || strpos($realBackupDir, $expectedBase) !== 0) {
            $output->writeln("<error>Invalid backup directory path. Security violation detected.</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
            $output->writeln("<info>Created backup directory: {$this->backupDir}</info>");
        }

        if ($isRestore) {
            return $this->restoreRegions($countryCode, $output);
        } else {
            return $this->importRegions($countryCode, $output);
        }
    }

    /**
     * Validate country code format
     *
     * @param string $countryCode
     * @return bool
     */
    protected function isValidCountryCode($countryCode)
    {
        // Must be exactly 2 uppercase letters (ISO 3166-1 alpha-2)
        return preg_match('/^[A-Z]{2}$/', $countryCode) === 1;
    }

    /**
     * Import regions and cities for a country
     *
     * @param string $countryCode
     * @param OutputInterface $output
     * @return int
     */
    protected function importRegions($countryCode, OutputInterface $output)
    {
        $output->writeln("<info>Starting region import for country: {$countryCode}</info>");

        $connection = $this->resourceConnection->getConnection();
        $tableRegion = $this->resourceConnection->getTableName('directory_country_region');
        $tableRegionName = $this->resourceConnection->getTableName('directory_country_region_name');
        $tableCities = $this->resourceConnection->getTableName('innoship_citys');

        // Step 1: Backup existing data
        $output->writeln("<info>Creating backup of existing regions...</info>");
        $backupResult = $this->backupRegions($countryCode, $connection, $tableRegion, $tableRegionName, $output);
        if (!$backupResult) {
            $output->writeln("<error>Backup failed. Aborting import.</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        // Step 2: Fetch data from InnoShip API
        $output->writeln("<info>Fetching data from InnoShip API...</info>");
        $apiKey = $this->config->getApiKey();
        $apiData = $this->getAllCountryDetails($countryCode, $apiKey);

        if (isset($apiData['status']) && (int)$apiData['status'] === 404) {
            $output->writeln("<error>Country {$countryCode} not found in InnoShip API</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        if (!isset($apiData['counties']) || !is_array($apiData['counties'])) {
            $output->writeln("<error>Invalid API response for country {$countryCode}</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        $counties = $apiData['counties'];
        $output->writeln("<info>Found " . count($counties) . " counties/regions in API response</info>");

        // Step 3: Delete existing data for this country
        $output->writeln("<info>Deleting existing regions for {$countryCode}...</info>");

        // Get region IDs to delete from region_name table
        $existingRegions = $connection->fetchAll(
            "SELECT region_id FROM {$tableRegion} WHERE country_id = ?",
            [$countryCode]
        );

        if (!empty($existingRegions)) {
            $regionIds = array_column($existingRegions, 'region_id');

            // SECURITY FIX: Validate all region IDs are integers
            $validRegionIds = array_filter($regionIds, function($id) {
                return is_numeric($id) && $id > 0;
            });
            $validRegionIds = array_map('intval', $validRegionIds);

            if (!empty($validRegionIds)) {
                // SECURITY FIX: Use parameterized query with IN clause
                $placeholders = implode(',', array_fill(0, count($validRegionIds), '?'));
                $connection->query(
                    "DELETE FROM {$tableRegionName} WHERE region_id IN ({$placeholders})",
                    $validRegionIds
                );
                $output->writeln("<info>Deleted region names for " . count($validRegionIds) . " regions</info>");
            }
        }

        // Delete from region table
        $connection->query("DELETE FROM {$tableRegion} WHERE country_id = ?", [$countryCode]);
        $output->writeln("<info>Deleted regions from directory_country_region</info>");

        // Delete cities
        $connection->query("DELETE FROM {$tableCities} WHERE country = ?", [$countryCode]);
        $output->writeln("<info>Deleted cities from innoship_citys</info>");

        // Step 4: Insert new regions
        $output->writeln("<info>Importing regions...</info>");
        $insertedRegions = 0;
        $insertedCities = 0;

        foreach ($counties as $county) {
            $countyNameLocalized = $county['countyNameLocalized'] ?? '';
            $countyName = $county['countyName'] ?? '';
            $countyId = $county['countyId'] ?? 0;

            if (empty($countyNameLocalized)) {
                continue;
            }

            // Generate code from countyName (alphanumeric only)
            $code = $this->generateRegionCode($countyName);

            // Insert into directory_country_region
            $connection->insert($tableRegion, [
                'country_id' => $countryCode,
                'code' => $code,
                'default_name' => $countyNameLocalized
            ]);

            $regionId = $connection->lastInsertId();

            // Insert into directory_country_region_name (en_US locale)
            $connection->insert($tableRegionName, [
                'locale' => 'en_US',
                'region_id' => $regionId,
                'name' => $countyNameLocalized
            ]);

            $insertedRegions++;

            // Insert cities for this region
            // SECURITY FIX: Use insertMultiple with proper data array instead of building raw SQL
            if (isset($county['localities']) && is_array($county['localities'])) {
                $cityData = [];

                foreach ($county['localities'] as $locality) {
                    $localityName = $locality['localityName'] ?? '';
                    $postalCode = $locality['streets'][0]['postalCode'] ?? '';

                    if (!empty($localityName)) {
                        $cityData[] = [
                            'country' => $countryCode,
                            'regioId' => $regionId,
                            'regioCode' => $code,
                            'localitate' => $localityName,
                            'codPostal' => $postalCode
                        ];
                    }
                }

                if (!empty($cityData)) {
                    // Insert in chunks of 500 to avoid query size issues
                    $chunks = array_chunk($cityData, 500);
                    foreach ($chunks as $chunk) {
                        $connection->insertMultiple($tableCities, $chunk);
                        $insertedCities += count($chunk);
                    }
                }
            }

            if ($insertedRegions % 10 === 0) {
                $output->writeln("<comment>Processed {$insertedRegions} regions...</comment>");
            }
        }

        $output->writeln("<info>Successfully imported {$insertedRegions} regions and {$insertedCities} cities for {$countryCode}</info>");
        $output->writeln("<info>Backup saved to: {$this->backupDir}</info>");

        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }

    /**
     * Backup existing regions to SQL files
     *
     * @param string $countryCode
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $tableRegion
     * @param string $tableRegionName
     * @param OutputInterface $output
     * @return bool
     */
    protected function backupRegions($countryCode, $connection, $tableRegion, $tableRegionName, $output)
    {
        try {
            // SECURITY FIX: Validate country code again to prevent path traversal
            if (!$this->isValidCountryCode($countryCode)) {
                $output->writeln("<error>Invalid country code in backup operation.</error>");
                return false;
            }

            $timestamp = date('Y-m-d_H-i-s');

            // SECURITY FIX: Use basename to prevent directory traversal
            $safeCountryCode = basename($countryCode);
            $regionBackupFile = $this->backupDir . "/region_{$safeCountryCode}_{$timestamp}.sql";
            $regionNameBackupFile = $this->backupDir . "/region_name_{$safeCountryCode}_{$timestamp}.sql";

            // SECURITY FIX: Verify the final path is within backup directory
            $realRegionFile = realpath(dirname($regionBackupFile)) . '/' . basename($regionBackupFile);
            $realBackupDir = realpath($this->backupDir);

            if (strpos($realRegionFile, $realBackupDir) !== 0) {
                $output->writeln("<error>Path traversal attempt detected in backup operation.</error>");
                return false;
            }

            // Backup directory_country_region
            $regions = $connection->fetchAll(
                "SELECT * FROM {$tableRegion} WHERE country_id = ?",
                [$countryCode]
            );

            $regionSql = "-- Backup of directory_country_region for {$countryCode}\n";
            $regionSql .= "-- Created: {$timestamp}\n\n";

            if (!empty($regions)) {
                foreach ($regions as $region) {
                    $regionSql .= sprintf(
                        "INSERT INTO %s (`region_id`, `country_id`, `code`, `default_name`) VALUES (%d, '%s', '%s', '%s');\n",
                        $tableRegion,
                        $region['region_id'],
                        $this->escapeSql($region['country_id']),
                        $this->escapeSql($region['code']),
                        $this->escapeSql($region['default_name'])
                    );
                }

                file_put_contents($regionBackupFile, $regionSql);
                $output->writeln("<info>Backed up " . count($regions) . " regions to: {$regionBackupFile}</info>");

                // Backup directory_country_region_name
                // SECURITY FIX: Use parameterized query for region IDs
                $regionIds = array_column($regions, 'region_id');

                $select = $connection->select()
                    ->from($tableRegionName)
                    ->where('region_id IN (?)', $regionIds);

                $regionNames = $connection->fetchAll($select);

                $regionNameSql = "-- Backup of directory_country_region_name for {$countryCode}\n";
                $regionNameSql .= "-- Created: {$timestamp}\n\n";

                if (!empty($regionNames)) {
                    foreach ($regionNames as $regionName) {
                        $regionNameSql .= sprintf(
                            "INSERT INTO %s (`locale`, `region_id`, `name`) VALUES ('%s', %d, '%s');\n",
                            $tableRegionName,
                            $this->escapeSql($regionName['locale']),
                            $regionName['region_id'],
                            $this->escapeSql($regionName['name'])
                        );
                    }

                    file_put_contents($regionNameBackupFile, $regionNameSql);
                    $output->writeln("<info>Backed up " . count($regionNames) . " region names to: {$regionNameBackupFile}</info>");
                }
            } else {
                $output->writeln("<comment>No existing regions found for {$countryCode}, skipping backup</comment>");
            }

            return true;
        } catch (\Exception $e) {
            $output->writeln("<error>Backup error: " . $e->getMessage() . "</error>");
            return false;
        }
    }

    /**
     * Restore regions from backup
     *
     * @param string $countryCode
     * @param OutputInterface $output
     * @return int
     */
    protected function restoreRegions($countryCode, OutputInterface $output)
    {
        $output->writeln("<info>Starting region restore for country: {$countryCode}</info>");

        // Find the most recent backup files
        $regionBackupFiles = glob($this->backupDir . "/region_{$countryCode}_*.sql");
        $regionNameBackupFiles = glob($this->backupDir . "/region_name_{$countryCode}_*.sql");

        if (empty($regionBackupFiles)) {
            $output->writeln("<error>No backup files found for country {$countryCode}</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        // Sort to get the most recent
        rsort($regionBackupFiles);
        rsort($regionNameBackupFiles);

        $regionBackupFile = $regionBackupFiles[0];
        $regionNameBackupFile = $regionNameBackupFiles[0] ?? null;

        $output->writeln("<info>Restoring from backup: {$regionBackupFile}</info>");

        $connection = $this->resourceConnection->getConnection();

        try {
            // Read and execute region backup
            $regionSql = file_get_contents($regionBackupFile);
            $statements = $this->parseSqlStatements($regionSql);

            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $connection->query($statement);
                }
            }

            $output->writeln("<info>Restored regions from: {$regionBackupFile}</info>");

            // Read and execute region name backup
            if ($regionNameBackupFile && file_exists($regionNameBackupFile)) {
                $output->writeln("<info>Restoring region names from: {$regionNameBackupFile}</info>");
                $regionNameSql = file_get_contents($regionNameBackupFile);
                $statements = $this->parseSqlStatements($regionNameSql);

                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        $connection->query($statement);
                    }
                }

                $output->writeln("<info>Restored region names from: {$regionNameBackupFile}</info>");
            }

            $output->writeln("<info>Restore completed successfully for {$countryCode}</info>");
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Restore failed: " . $e->getMessage() . "</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }

    /**
     * Parse SQL statements from file content
     *
     * @param string $sql
     * @return array
     */
    protected function parseSqlStatements($sql)
    {
        // Remove comments
        $sql = preg_replace('/^--.*$/m', '', $sql);

        // Split by semicolon
        $statements = explode(';', $sql);

        return array_filter(array_map('trim', $statements));
    }

    /**
     * Get all country details from InnoShip API
     *
     * @param string $countryCode
     * @param string $apiKey
     * @return array
     */
    protected function getAllCountryDetails($countryCode, $apiKey)
    {
        $url = "https://api.innoship.com/api/Location/Postalcodes/Country/" . strtolower($countryCode);
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

    /**
     * Generate region code from county name (alphanumeric only)
     *
     * @param string $countyName
     * @return string
     */
    protected function generateRegionCode($countyName)
    {
        // Remove diacritics and special characters
        $code = $this->eliminateDiacritice($countyName);

        // Keep only alphanumeric characters
        $code = preg_replace('/[^a-zA-Z0-9]/', '', $code);

        // Limit to 32 characters (database constraint)
        $code = substr($code, 0, 32);

        return $code;
    }

    /**
     * Remove diacritics from string
     *
     * @param string $string
     * @return string
     */
    protected function eliminateDiacritice($string)
    {
        return str_replace(
            ["ș", "ş", "Ș", "ț", "ţ", "Ț", "Â", "â", "Ă", "ă", "Î", "î", "ą", "ę", "ć", "ł", "ń", "ó", "ś", "ź", "ż", "Ą", "Ę", "Ć", "Ł", "Ń", "Ó", "Ś", "Ź", "Ż"],
            ["s", "s", "S", "t", "t", "T", "A", "a", "A", "a", "I", "i", "a", "e", "c", "l", "n", "o", "s", "z", "z", "A", "E", "C", "L", "N", "O", "S", "Z", "Z"],
            $string
        );
    }

    /**
     * Clean string for SQL insertion
     *
     * @param string $string
     * @return string
     */
    protected function cleanString($string)
    {
        return str_replace(["'"], ["`"], $string);
    }

    /**
     * Escape SQL string
     *
     * @param string $string
     * @return string
     */
    protected function escapeSql($string)
    {
        return addslashes($string);
    }
}
