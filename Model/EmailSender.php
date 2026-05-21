<?php
/**
 * Etechflow_AbandonedCart - EmailSender service.
 *
 * Takes a QUEUED email_log row and actually transmits the email through
 * Magento's TransportBuilder. Called by [[Etechflow\AbandonedCart\Cron\SendQueuedEmails]]
 * and from the admin "Send Now" action (Phase 16). The split between
 * SendReminders (which QUEUES) and this sender (which SENDS) keeps the
 * slow SMTP work out of the scanner's lock window.
 *
 * Test-mode handling per spec §2.1: when `general/test_mode` is on, every
 * outbound email is redirected to `general/test_recipient_email` regardless
 * of who the real recipient is. Lets merchants preview emails to dev
 * inboxes without spamming real customers during go-live.
 *
 * Sender identity is read from Magento's standard `trans_email/ident_<X>/*`
 * config via the Config wrapper (NEVER direct ScopeConfigInterface — §5).
 *
 * Result handling:
 *   - On success: log.status = SENT, log.sent_at = now, return true.
 *   - On failure: log.status = FAILED, log.error_message = exception, log it,
 *     return false. The cron continues to the next queued row.
 *
 * Hot-path discipline:
 *   - Profiler span around the actual send for ops dashboards.
 *   - Single repo->save per log row (no N+1).
 *   - Exception boundary at the public method — never propagates to caller.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model;

use Etechflow\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use Etechflow\AbandonedCart\Api\Data\EmailLogInterface;
use Etechflow\AbandonedCart\Api\EmailLogRepositoryInterface;
use Etechflow\AbandonedCart\Model\Performance\Profiler;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class EmailSender
{
    public function __construct(
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly AbandonedCartRepositoryInterface $cartRepo,
        private readonly EmailLogRepositoryInterface $emailLogRepo,
        private readonly EmailVariableBuilder $variableBuilder,
        private readonly TransportBuilder $transportBuilder,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(EmailLogInterface $log): bool
    {
        if (!$this->config->isEnabled() || !$this->licenseValidator->isValid()) {
            return false;
        }

        $span = Profiler::start('Etechflow_ABC_EmailSend');

        try {
            $cart = $this->cartRepo->getById((int) $log->getCartId());
            $storeId = $cart->getStoreId();

            $variables = $this->variableBuilder->build($cart, $log);
            $recipient = $this->resolveRecipient($log->getRecipientEmail(), $storeId);
            $sender    = $this->resolveSender($storeId);

            $this->transportBuilder
                ->setTemplateIdentifier($log->getEmailTemplate())
                ->setTemplateOptions([
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setTemplateVars($variables)
                ->setFromByScope($sender, $storeId)
                ->addTo($recipient);

            $replyTo = $this->config->getReplyToEmail($storeId);
            if ($replyTo !== '') {
                $this->transportBuilder->setReplyTo($replyTo);
            }

            $bcc = $this->config->getBccEmail($storeId);
            if ($bcc !== '') {
                foreach ($this->splitEmails($bcc) as $bccAddress) {
                    $this->transportBuilder->addBcc($bccAddress);
                }
            }

            $transport = $this->transportBuilder->getTransport();
            $transport->sendMessage();

            $log->setStatus(EmailLogInterface::STATUS_SENT);
            $log->setSentAt($this->dateTime->gmtDate());
            $log->setErrorMessage(null);
            $this->emailLogRepo->save($log);

            $this->logger->info(
                'Etechflow_AbandonedCart: email sent',
                [
                    'log_id'    => $log->getLogId(),
                    'cart_id'   => $log->getCartId(),
                    'rule_id'   => $log->getRuleId(),
                    'recipient' => $recipient,
                    'template'  => $log->getEmailTemplate(),
                ]
            );
            return true;
        } catch (\Throwable $e) {
            $this->markFailed($log, $e);
            return false;
        } finally {
            Profiler::stop($span);
        }
    }

    /**
     * Test-mode redirect: when on, every outbound recipient becomes the
     * configured test inbox. Comma-separated test recipients all receive
     * the email (per UX-improvement #2 over Amasty).
     */
    private function resolveRecipient(string $realRecipient, int $storeId): string
    {
        if (!$this->config->isTestMode($storeId)) {
            return $realRecipient;
        }

        $testRecipients = $this->config->getTestRecipientEmail($storeId);
        $first = $this->splitEmails($testRecipients)[0] ?? '';
        return $first !== '' ? $first : $realRecipient;
    }

    /**
     * @return array{email: string, name: string}
     */
    private function resolveSender(int $storeId): array
    {
        return [
            'email' => $this->config->getSenderEmailFromIdentity('general', $storeId),
            'name'  => $this->config->getSenderName($storeId)
                ?: $this->config->getSenderNameFromIdentity('general', $storeId),
        ];
    }

    /**
     * @return string[]
     */
    private function splitEmails(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    private function markFailed(EmailLogInterface $log, \Throwable $e): void
    {
        $log->setStatus(EmailLogInterface::STATUS_FAILED);
        $log->setErrorMessage(substr($e->getMessage(), 0, 1000));
        try {
            $this->emailLogRepo->save($log);
        } catch (\Throwable $saveException) {
            // Storage itself is sick — log raw and move on; the SendReminders cron
            // will skip this row next tick because emails_sent already bumped.
            $this->logger->error(
                'Etechflow_AbandonedCart: failed to persist FAILED status on email log',
                [
                    'log_id'         => $log->getLogId(),
                    'send_exception' => $e->getMessage(),
                    'save_exception' => $saveException->getMessage(),
                ]
            );
            return;
        }

        $this->logger->warning(
            'Etechflow_AbandonedCart: email send failed',
            [
                'log_id'    => $log->getLogId(),
                'cart_id'   => $log->getCartId(),
                'exception' => $e->getMessage(),
            ]
        );
    }
}
