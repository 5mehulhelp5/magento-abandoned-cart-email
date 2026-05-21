<?php
/**
 * Etechflow_AbandonedCart - Per-row actions for the admin Rules grid.
 *
 * Edit (GET) + Delete (with confirm dialog). Edit goes to the form page;
 * Delete posts to the Delete controller, which redirects back to the
 * grid with a flash message.
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

class RuleActions extends Column
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
                        'etechflow_abandonedcart/rule/edit',
                        ['rule_id' => $id]
                    ),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href'    => $this->urlBuilder->getUrl(
                        'etechflow_abandonedcart/rule/delete',
                        ['rule_id' => $id]
                    ),
                    'label'   => __('Delete'),
                    'confirm' => [
                        'title'   => __('Delete rule?'),
                        'message' => __('Are you sure you want to delete the rule "%1"? This cannot be undone.', $item['name'] ?? ''),
                    ],
                ],
            ];
        }

        return $dataSource;
    }
}
