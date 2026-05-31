<?php
/**
 * Etechflow_AbandonedCart - Form data provider for the PopupRule edit/new page.
 *
 * Magento's form UI Component asks this class for the data array to
 * pre-populate fields. Returns:
 *   - empty array      → "new" mode, form fields start blank
 *   - [id => [...]]    → "edit" mode, fields populated from existing rule
 *
 * Magento determines mode via the `rule_id` URL parameter (set as
 * requestFieldName in the form ui_component XML).
 *
 * CSV columns (store_ids, customer_group_ids) are split back into arrays
 * before handing to the form so the multiselect widgets pre-tick the
 * correct options.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\PopupRule;

use Etechflow\AbandonedCart\Api\Data\PopupRuleInterface;
use Etechflow\AbandonedCart\Model\ResourceModel\PopupRule\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int, array>
     */
    private array $loadedData = [];

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        /** @var PopupRuleInterface[] $items */
        $items = $this->collection->getItems();
        foreach ($items as $rule) {
            $row = $rule->getData();
            $row['store_ids']          = $this->splitCsv($row['store_ids'] ?? '0');
            $row['customer_group_ids'] = $this->splitCsv($row['customer_group_ids'] ?? '0');
            $this->loadedData[$rule->getId()] = $row;
        }

        return $this->loadedData;
    }

    /**
     * @return string[]
     */
    private function splitCsv(string $value): array
    {
        if ($value === '' || $value === '0') {
            return ['0'];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
