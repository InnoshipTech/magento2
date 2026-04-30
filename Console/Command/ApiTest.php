<?php

namespace InnoShip\InnoShip\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use InnoShip\InnoShip\Model\Api\Label;
use InnoShip\InnoShip\Model\Api\Order;
use InnoShip\InnoShip\Model\Config;

/**
 * Class ApiTest
 * @package InnoShip\Opportunity\Console\Command
 */
class ApiTest extends Command
{
    /** @var Order */
    protected $orderModel;

    /** @var Label */
    protected $orderLabel;

    /** @var Config */
    protected $config;

    /**
     * ApiTest constructor.
     *
     * @param Order       $orderModel
     * @param Label       $orderLabel
     * @param Config      $config
     * @param string|null $name
     */
    public function __construct(
        Order $orderModel,
        Label $orderLabel,
        Config $config,
        ?string $name = null,
    ) {
        parent::__construct($name);

        $this->orderModel = $orderModel;
        $this->orderLabel = $orderLabel;
        $this->config     = $config;
    }

    public function configure(): void
    {
        $this->setName('innoship:api:test')->setDescription('InnoShip API Test');

        parent::configure();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('START API Test');

        try {
            // Test create Order
            $this->testCreateOrder($input, $output);

            // Get order label test
            //$this->testGetOrderLabel($input, $output);

            // Test delete order
            //$this->testDeleteOrder($input, $output);

            $output->writeln('Test done!');
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
        }

        $output->writeln('END API Test');

        return self::SUCCESS;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \Exception
     */
    protected function testCreateOrder(InputInterface $input, OutputInterface $output)
    {
        $response = $this->orderModel->create($this->getCreateOrderData());

        $output->writeln('---------------');
        $output->writeln('Create Order Test');
        $output->writeln('ClientOrderId: ' . $response->getDataByKey('clientOrderId'));
        $output->writeln('CourierShipmentId: ' . $response->getDataByKey('courierShipmentId'));
        $output->writeln('Courier: ' . $response->getDataByKey('courier'));
        $output->writeln('Price: ' . json_encode($response->getDataByKey('price')));
        $output->writeln('CalculatedDeliveryDate: ' . $response->getDataByKey('calculatedDeliveryDate'));
        $output->writeln('trackPageUrl: ' . $response->getDataByKey('trackPageUrl'));
        $output->writeln('Response: ' . json_encode($response->getData()));
        $output->writeln('---------------');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \Exception
     */
    protected function testDeleteOrder(InputInterface $input, OutputInterface $output)
    {
        $response = $this->orderModel->delete(1, '900132772');

        $output->writeln('---------------');
        $output->writeln('Delete Order Test');
        $output->writeln('Response: ' . json_encode($response->getData()));
        $output->writeln('---------------');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \Exception
     */
    protected function testGetOrderLabel(InputInterface $input, OutputInterface $output)
    {
        $response = $this->orderLabel->get(1, '900132451', '/var/www/magento/pub/media/pdf/awb3.pdf');
        $output->writeln('---------------');
        $output->writeln('Get Order Label Test');
        $output->writeln('Response: ' . json_encode($response->getData()));
        $output->writeln('---------------');
    }

    /**
     * @return array
     */
    protected function getCreateOrderData(): array
    {
        return [
            "serviceId"              => 1,
            "shipmentDate"           => date('c'),
            "shipmentDateEnd"        => date('c'),
            "addressTo"              => [
                "name"          => "Popescu Eugen",
                "contactPerson" => "Popescu Eugen",
                "country"       => "RO",
                //                "countyId"        => null,
                //                "localityId"      => null,
                //                "countryId"       => null,
                "countyName"    => "Brasov",
                "localityName"  => "Brasov",
                "addressText"   => "strada 13 Decembrie, nr. 31",
                //                "postalCode"      => "123456",
                "phone"         => "+40734123456",
                //                "email"           => "contact@innoship.com",
                //                "fixedLocationId" => null,
            ],
            "payment"                => $this->config->getPayment(),
            "content"                => [
                "envelopeCount" => 0,
                "parcelsCount"  => 1,
                "palettesCount" => 0,
                "totalWeight"   => 1.0,
                "contents"      => "t-shirt",
                //                "package"          => "box",
                //                "oversizedPackage" => false,
                "parcels"       => [
                    [
                        "sequenceNo" => 1,
                        "size"       => [
                            "width"  => 20,
                            "height" => 50,
                            "length" => 20,
                        ],
                        "weight"     => 1,
                        "type"       => 2,
                        "reference1" => "Order ID: " . time(),
                        //                        "reference2"      => null,
                        //                        "customerBarcode" => null,
                    ],
                ],
            ],
            //            "extra"                  => [
            //                "bankRepaymentAmount"      => 32.12,
            //                "cashOnDeliveryAmount"     => null,
            //                "openPackage"              => true,
            //                "saturdayDelivery"         => null,
            //                "insuranceAmount"          => null,
            //                "reference1"               => null,
            //                "reference2"               => null,
            //                "returnOfDocuments"        => null,
            //                "returnOfDocumentsComment" => null,
            //                "returnPackage"            => null,
            //            ],
            //            "parameters"             => [
            //                "async"              => true,
            //                "getParcelsBarcodes" => false,
            //            ],
            "externalClientLocation" => "1",
            //            "externalOrderId"        => null,
            //            "metadata"               => "extra data information",
            //            "sourceChannel"          => "ONLINE",
            //            "observation"            => "Call one hour before",
            //            "customAttributes"       => null,
        ];
    }
}
