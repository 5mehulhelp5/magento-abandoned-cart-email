<?php
/**
 * Etechflow_AbandonedCart - Per-row actions for the admin Popup Rules grid.
 *
 * Edit (GET) + Delete (with confirm dialog). Mirrors [[Etechflow\AbandonedCart\Ui\Component\Listing\Column\RuleActions]]
 * but routes to the popuprule controllers.
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

class PopupRuleActions extends Column
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
            if (!isset($item['rule_id'])) {
                continue;
            }
            $id = (int) $item['rule_id'];

            $item[$name] = [
                'edit' => [
                    'href'  => $this->urlBuilder->getUrl(
                        'etechflow_abandonedcart/popuprule/edit',
                        ['rule_id' => $id]
                    ),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href'    => $this->urlBuilder->getUrl(
                        'etechflow_abandonedcart/popuprule/delete',
                        ['rule_id' => $id]
                    ),
                    'label'   => __('Delete'),
                    'confirm' => [
                        'title'   => __('Delete popup rule?'),
                        'message' => __('Are you sure you want to delete the popup rule "%1"? This cannot be undone.', $item['name'] ?? ''),
                    ],
                ],
            ];
        }

        return $dataSource;
    }
}
