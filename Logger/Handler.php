<?php

namespace InnoShip\InnoShip\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Class Handler
 * @package InnoShip\InnoShip\Logger
 */
class Handler extends Base
{
    /** @var string */
    protected const FILE_LOG_NAME = 'innoShipLogs';

    /** @var string */
    protected const FILE_LOG_EXTENSION = '.log';

    /** @var string */
    protected const FILE_LOG_PATH = '/var/log/';

    /** @var int */
    protected $loggerType = Logger::INFO;

    /**
     * Handler constructor.
     *
     * @param DriverInterface $filesystem
     * @param string|null     $filePath
     * @param string|null     $fileName
     *
     * @throws \Exception
     */
    public function __construct(
        DriverInterface $filesystem,
        ?string $filePath = null,
        ?string $fileName = null,
    ) {
        $this->fileName = $this->setFileLogName();

        parent::__construct($filesystem, $filePath, $fileName);
    }

    /**
     * Set file name
     *
     * @return string
     */
    protected function setFileLogName(): string
    {
        $dirTree = [
            date('Y'),
            date('m'),
            date('d'),
            self::FILE_LOG_NAME . self::FILE_LOG_EXTENSION,
        ];

        return self::FILE_LOG_PATH . implode('/', $dirTree);
    }
}
