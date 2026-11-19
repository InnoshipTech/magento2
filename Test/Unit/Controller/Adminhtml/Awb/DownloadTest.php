<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Controller\Adminhtml\Awb;

use InnoShip\InnoShip\Controller\Adminhtml\Awb\Download;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DownloadTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/innoship-download-' . uniqid('', true);
        mkdir($this->root . '/media/innoship', 0777, true);
        mkdir($this->root . '/media_old', 0777, true);
        file_put_contents($this->root . '/media/innoship/awb.pdf', '%PDF-1.4 test');
        file_put_contents($this->root . '/media_old/secret.pdf', '%PDF-1.4 secret');
    }

    protected function tearDown(): void
    {
        foreach (['/media/innoship/awb.pdf', '/media_old/secret.pdf'] as $file) {
            @unlink($this->root . $file);
        }
        @rmdir($this->root . '/media/innoship');
        @rmdir($this->root . '/media');
        @rmdir($this->root . '/media_old');
        @rmdir($this->root);
    }

    public function testServesPdfInsideMediaAndNeverDeletesIt(): void
    {
        $fileFactory = $this->createMock(FileFactory::class);
        $fileFactory->expects(self::once())
            ->method('create')
            ->with(
                'awb.pdf',
                [
                    'type' => 'filename',
                    'value' => realpath($this->root . '/media/innoship/awb.pdf'),
                    'rm' => false,
                ],
                DirectoryList::MEDIA
            )
            ->willReturn($this->createMock(ResponseInterface::class));

        $this->controller($fileFactory, 'innoship/', 'awb.pdf')->execute();

        self::assertFileExists($this->root . '/media/innoship/awb.pdf');
    }

    public function testRefusesPathOutsideMedia(): void
    {
        $fileFactory = $this->createMock(FileFactory::class);
        $fileFactory->expects(self::never())->method('create');

        $this->controller($fileFactory, '../media_old/', 'secret.pdf', true)->execute();

        self::assertFileExists($this->root . '/media_old/secret.pdf');
    }

    public function testRefusesNonPdf(): void
    {
        $fileFactory = $this->createMock(FileFactory::class);
        $fileFactory->expects(self::never())->method('create');

        $this->controller($fileFactory, 'innoship/', 'awb.php', true)->execute();
    }

    private function controller(FileFactory $fileFactory, string $path, string $file, bool $expectRedirect = false): Download
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            ['file', null, $file],
            ['path', null, $path],
        ]);

        $media = $this->createMock(ReadInterface::class);
        $media->method('getAbsolutePath')->willReturn($this->root . '/media/');
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->with(DirectoryList::MEDIA)->willReturn($media);

        $controller = $this->getMockBuilder(Download::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_redirect'])
            ->getMock();
        $controller->expects($expectRedirect ? self::once() : self::never())
            ->method('_redirect')
            ->willReturn($this->createMock(ResponseInterface::class));

        $properties = [
            '_request' => $request,
            'messageManager' => $this->createMock(ManagerInterface::class),
            'fileFactory' => $fileFactory,
            'filesystem' => $filesystem,
            'logger' => $this->createMock(LoggerInterface::class),
        ];
        foreach ($properties as $name => $value) {
            $class = new \ReflectionClass(Download::class);
            while (!$class->hasProperty($name)) {
                $class = $class->getParentClass();
            }
            $property = $class->getProperty($name);
            $property->setValue($controller, $value);
        }

        return $controller;
    }
}
