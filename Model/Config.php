<?php

namespace InnoShip\InnoShip\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Config
 * @package InnoShip\InnoShip\Model
 */
class Config
{
    /** @var string */
    const XML_PATH = 'carriers/innoship/';
    const XML_PATH_GO = 'carriers/innoshipcargusgo/';

    /** @var ScopeConfigInterface */
    protected $config;

    /** @var EncryptorInterface */
    protected $encryptor;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $config
     * @param EncryptorInterface   $encryptor
     */
    public function __construct(ScopeConfigInterface $config, EncryptorInterface $encryptor)
    {
        $this->config    = $config;
        $this->encryptor = $encryptor;
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getActive(?int $storeId = null): int
    {
        return $this->getValue('active', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getTitle(?int $storeId = null): string
    {
        return $this->getValue('title', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getMethodName(?int $storeId = null): string
    {
        return $this->getValue('name', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getAllowMethods(?int $storeId = null): string
    {
        return $this->getValue('allowed_methods', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getAllowMethodsGo(?int $storeId = null): string
    {
        return $this->getValueGo('allowed_methods', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string|null
     */
    public function getExceptionMethods(?int $storeId = null): ?string
    {
        return $this->getValue('card_payment', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string|null
     */
    public function getPaymentRestriction(?int $storeId = null): ?string
    {
        return $this->getValueGo('innoship_cargus_go_payment_restriction', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string|null
     */
    public function getExternalIdSend(?int $storeId = null): ?string
    {
        return $this->getValue('external_id_send', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string|null
     */
    public function getHandlingFeeExternal(?int $storeId = null): ?string
    {
        return $this->getValue('handling_fee_external', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string|null
     */
    public function getDefaultOrderContent(?int $storeId = null): ?string
    {
        return $this->getValue('default_order_content', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getGatewayUrl(?int $storeId = null): string
    {
        return $this->getValue('gateway_url', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getApiKey(?int $storeId = null): string
    {
        return $this->encryptor->decrypt(
            $this->getValue('api_key', $storeId)
        );
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getApiVersion(?int $storeId = null): string
    {
        return $this->getValue('api_version', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getSaturdayDelivery(?int $storeId = null): int
    {
        return $this->getValue('saturday_delivery', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getShowcourierlist(?int $storeId = null): int
    {
        return (int)$this->getValue('showcourierlist', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getOpenPackage(?int $storeId = null): int
    {
        return $this->getValue('open_package', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getInsuranceIncluded(?int $storeId = null): int
    {
        return $this->getValue('insurance_included', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getHandlingFee(?int $storeId = null): int
    {
        return $this->getValue('handling_fee', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getFreeShippingEnable(?int $storeId = null): int
    {
        return $this->getValue('free_shipping_enable', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getFreeShippingSubtotal(?int $storeId = null): ?int
    {
        return $this->getValue('free_shipping_subtotal', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getPayment(?int $storeId = null): string
    {
        return $this->getValue('payment', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getRePayment(?int $storeId = null): string
    {
        return $this->getValue('repayment', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getApplicableCountries(?int $storeId = null): int
    {
        return $this->getValue('sallowspecific', $storeId);
    }

    public function getSpecificCountries(?int $storeId = null)
    {
        return $this->getValue('specificcountry', $storeId);
    }

    public function getSpecificCountriesGo(?int $storeId = null)
    {
        return $this->getValueGo('specificcountry', $storeId);
    }
    /**
     * @param int|null $storeId
     *
     * @return string|null
     */
    public function getLabelformat(?int $storeId = null): string
    {
        $format = $this->getValue('labelformat', $storeId);
        if(!$format){
            $format = "A4";
        }
        return $format;
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getShowMethod(?int $storeId = null): int
    {
        return $this->getValue('showmethod', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getShowErrorMessage(?int $storeId = null): string
    {
        return $this->getValue('specificerrmsg', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getDebug(?int $storeId = null): int
    {
        return $this->getValue('debug', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int|null
     */
    public function getShortOrder(?int $storeId = null): ?int
    {
        return $this->getValue('sort_order', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getLungime(?int $storeId = null)
    {
        return $this->getValue('lungime', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getLatime(?int $storeId = null)
    {
        return $this->getValue('latime', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getInaltime(?int $storeId = null)
    {
        return $this->getValue('inaltime', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getAutomaticPrice(?int $storeId = null): ?int
    {
        return $this->getValue('automatic_price', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getCheckIfAvailable(?int $storeId = null): ?int
    {
        return $this->getValueGo('check_if_available', $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return float|null
     */
    public function getMultiplicator(?int $storeId = null): ?float
    {
        $value = $this->getValue('multiplicator', $storeId);
        return $value !== null ? (float)$value : null;
    }

    /**
     * @param string   $name
     * @param int|null $storeId
     *
     * @return mixed
     */
    protected function getValue(string $name, ?int $storeId = null)
    {
        return $this->config->getValue($this->getXmlPath($name), ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param string   $name
     * @param int|null $storeId
     *
     * @return mixed
     */
    protected function getValueGo(string $name, ?int $storeId = null)
    {
        return $this->config->getValue($this->getXmlPathGo($name), ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function getXmlPath(string $path): string
    {
        return self::XML_PATH . $path;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function getXmlPathGo(string $path): string
    {
        return self::XML_PATH_GO . $path;
    }
}
