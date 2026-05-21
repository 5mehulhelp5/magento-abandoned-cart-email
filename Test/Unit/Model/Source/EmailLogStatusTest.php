<?php
/**
 * Etechflow_AbandonedCart - EmailLogStatus source tests.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Test\Unit\Model\Source;

use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Model\Source\EmailLogStatus;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

class EmailLogStatusTest extends TestCase
{
    private EmailLogStatus $source;

    protected function setUp(): void
    {
        $this->source = new EmailLogStatus();
    }

    public function testToOptionArrayContainsAllSixStatuses(): void
    {
        $options = $this->source->toOptionArray();
        $values = array_column($options, 'value');

        $this->assertContains(EmailLogInterface::STATUS_QUEUED, $values);
        $this->assertContains(EmailLogInterface::STATUS_SENT, $values);
        $this->assertContains(EmailLogInterface::STATUS_FAILED, $values);
        $this->assertContains(EmailLogInterface::STATUS_OPENED, $values);
        $this->assertContains(EmailLogInterface::STATUS_CLICKED, $values);
        $this->assertContains(EmailLogInterface::STATUS_CONVERTED, $values);
        $this->assertCount(6, $options);
    }

    public function testGetOptionTextForConvertedReturnsRichLabel(): void
    {
        $label = $this->source->getOptionText(EmailLogInterface::STATUS_CONVERTED);
        $this->assertSame('Converted to Order', (string) $label);
    }

    public function testGetOptionTextForUnknownReturnsEmpty(): void
    {
        $this->assertSame('', (string) $this->source->getOptionText(42));
    }
}
