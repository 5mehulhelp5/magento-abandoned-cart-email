<?php
/**
 * Etechflow_AbandonedCart
 *
 * Abandoned Cart Email extension for Magento 2.
 * Compatible with Luma, Hyva, and Adobe Commerce.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 * @author     ETechFlow <etechflow0@gmail.com>
 * @copyright  Copyright (c) ETechFlow (https://etechflow.com)
 * @license    Proprietary
 */
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Etechflow_AbandonedCart',
    __DIR__
);
