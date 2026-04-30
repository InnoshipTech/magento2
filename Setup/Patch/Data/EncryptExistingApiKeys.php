<?php
/**
 * Copyright © InnoShip. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace InnoShip\InnoShip\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;

/**
 * Data patch to encrypt existing API keys that may be stored in plain text
 *
 * This patch ensures backward compatibility for installations that had API keys
 * stored before encryption was implemented.
 */
class EncryptExistingApiKeys implements DataPatchInterface
{
    /**
     * Configuration paths for API keys
     */
    private const CONFIG_PATHS = [
        'carriers/innoship/api_key',
        'carriers/innoshipcargusgo/api_key'
    ];

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * EncryptExistingApiKeys constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EncryptorInterface $encryptor
     * @param LoggerInterface $logger
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EncryptorInterface $encryptor,
        LoggerInterface $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            $connection = $this->moduleDataSetup->getConnection();
            $tableName = $this->moduleDataSetup->getTable('core_config_data');
            $encryptedCount = 0;

            foreach (self::CONFIG_PATHS as $path) {
                // Get all API key configurations (default, website, store scopes)
                $select = $connection->select()
                    ->from($tableName, ['config_id', 'scope', 'scope_id', 'path', 'value'])
                    ->where('path = ?', $path);

                $configs = $connection->fetchAll($select);

                foreach ($configs as $config) {
                    $value = $config['value'];

                    // Skip if value is empty
                    if (empty($value)) {
                        continue;
                    }

                    // Check if value is already encrypted
                    // Encrypted values from Magento's Encrypted backend model contain ':'
                    // and typically start with a version number (0: or 1:)
                    if ($this->isAlreadyEncrypted($value)) {
                        $this->logger->info(
                            sprintf(
                                'API key for path "%s" (scope: %s, scope_id: %s) is already encrypted. Skipping.',
                                $path,
                                $config['scope'],
                                $config['scope_id']
                            )
                        );
                        continue;
                    }

                    // Encrypt the plain-text API key
                    $encryptedValue = $this->encryptor->encrypt($value);

                    // Update the database with encrypted value
                    $connection->update(
                        $tableName,
                        ['value' => $encryptedValue],
                        ['config_id = ?' => $config['config_id']]
                    );

                    $this->logger->info(
                        sprintf(
                            'Successfully encrypted API key for path "%s" (scope: %s, scope_id: %s)',
                            $path,
                            $config['scope'],
                            $config['scope_id']
                        )
                    );

                    $encryptedCount++;
                }
            }

            if ($encryptedCount > 0) {
                $this->logger->info(
                    sprintf('Data patch completed: Encrypted %d API key(s).', $encryptedCount)
                );
            } else {
                $this->logger->info('Data patch completed: No plain-text API keys found.');
            }

        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to encrypt existing API keys: ' . $e->getMessage(),
                ['exception' => $e]
            );
            // Don't throw exception - allow installation to continue
            // Admins can manually re-save the API key in admin if needed
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Check if a value is already encrypted
     *
     * Magento's encrypted values have a specific format:
     * - Version 0: base64 encoded (contains only alphanumeric + / + =)
     * - Version 1: "1:{cipher}:{base64_value}" format (contains colons)
     *
     * @param string $value
     * @return bool
     */
    private function isAlreadyEncrypted(string $value): bool
    {
        // Check for version 1 encryption format (contains colons and version prefix)
        if (preg_match('/^\d+:/', $value) && strpos($value, ':') !== false) {
            return true;
        }

        // Check if value looks like base64 but validate it's actually encrypted
        // by attempting to decrypt it. If decryption returns a different value,
        // it was already encrypted.
        try {
            $decrypted = $this->encryptor->decrypt($value);
            // If decryption produces a very different value (not just trimmed),
            // it was likely encrypted
            if ($decrypted !== $value && strlen($decrypted) !== strlen($value)) {
                return true;
            }
        } catch (\Exception $e) {
            // Decryption failed - likely not encrypted or corrupted
            // Treat as plain text to be safe
            return false;
        }

        // Additional heuristic: encrypted values are typically longer than plain text API keys
        // and don't contain common plain text patterns
        if (strlen($value) > 100 && !preg_match('/[^a-zA-Z0-9+\/=]/', $value)) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
