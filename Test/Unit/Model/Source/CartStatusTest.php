<?php
/**
 * Etechflow_AbandonedCart - CartStatus source tests.
 *
 * Verifies (a) every status enum has a corresponding option array entry
 * and (b) getOptionText() returns Phrase objects with matching labels +
 * empty Phrase for unknown values.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Test\Unit\Model\Source;

use Etechflow\AbandonedCart\Api\Data\AbandonedCartInterface;
use Etechflow\AbandonedCart\Model\Source\CartStatus;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

class CartStatusTest extends TestCase
{
    private CartStatus $source;

    protected function setUp(): void
    {
        $this->source = new CartStatus();
    }

    public function testToOptionArrayContainsAllStatuses(): void
    {
        $options = $this->source->toOptionArray();

        $values = array_column($options, 'value');
        $this->assertContains(AbandonedCartInterface::STATUS_PENDING, $values);
        $this->assertContains(AbandonedCartInterface::STATUS_PROCESSING, $values);
        $this->assertContains(AbandonedCartInterface::STATUS_RECOVERED, $values);
        $this->assertContains(AbandonedCartInterface::STATUS_EXPIRED, $values);
        $this->assertContains(AbandonedCartInterface::STATUS_UNSUBSCRIBED, $values);
        $this->assertCount(5, $options);
    }

    public function testEveryOptionHasLabel(): void
    {
        foreach ($this->source->toOptionArray() as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertInstanceOf(Phrase::class, $option['label']);
            $this->assertNotEmpty((string) $option['label']);
        }
    }

    public function testGetOptionTextReturnsMatchingLabel(): void
    {
        $label = $this->source->getOptionText(AbandonedCartInterface::STATUS_RECOVERED);
        $this->assertInstanceOf(Phrase::class, $label);
        $this->assertSame('Recovered', (string) $label);
    }

    public function testGetOptionTextForUnknownStatusReturnsEmptyPhrase(): void
    {
        $label = $this->source->getOptionText(999);
        $this->assertInstanceOf(Phrase::class, $label);
        $this->assertSame('', (string) $label);
    }
}
