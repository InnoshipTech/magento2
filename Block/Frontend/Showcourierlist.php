<?php

namespace InnoShip\InnoShip\Block\Frontend;
use InnoShip\InnoShip\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;


class Showcourierlist extends Template
{

    private $config;

    public function __construct(Context $context, Config $config, array $data)
    {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    public function getConfigValue()
    {
        return (int)$this->config->getShowcourierlist();
    }
}
