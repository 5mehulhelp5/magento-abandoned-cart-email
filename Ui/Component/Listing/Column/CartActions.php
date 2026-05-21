<?php
/**
 * Etechflow_AbandonedCart - Per-row actions column for the admin Carts grid.
 *
 * UI Component's `<actionsColumn>` element calls prepareDataSource() on its
 * configured class to populate each row's "Actions" cell with a dropdown
 * of action links. We provide two:
 *   - View      → opens the per-cart detail page
 *   - Send Now  → triggers the manual send-reminder controller
 *
 * Send Now is a "post" action so it goes through Magento's standard CSRF
 * form-key check. The view action is a regular GET link.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class CartActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entity_id'])) {
                continue;
            }
            $id = (int) $item['entity_id'];

            $item[$name] = [
                'view' => [
                    'href'  => $this->urlBuilder->getUrl(
                        'etechflow_abandonedcart/cart/view',
                        ['id' => $id]
                    ),
                    'label' => __('View'),
                ],
                'send_now' => [
                    'href'    => $this->urlBuilder->getUrl(
                        'etechflow_abandonedcart/cart/sendNow',
                        ['id' => $id]
                    ),
                    'label'   => __('Send Now'),
                    'confirm' => [
                        'title'   => __('Send recovery email now?'),
                        'message' => __('A reminder email will be queued for delivery to this cart\'s customer immediately, bypassing the rule schedule.'),
                    ],
                ],
            ];
        }

        return $dataSource;
    }
}
