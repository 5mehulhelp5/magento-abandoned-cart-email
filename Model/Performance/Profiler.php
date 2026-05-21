<?php
/**
 * Etechflow_AbandonedCart - Tideways span helper.
 *
 * Per ETechFlow Module Development Standards §6, every module owns its own
 * copy of this class (intentionally duplicated, not cross-module imported —
 * keeps the bundle distributable when modules ship standalone).
 *
 * Why static, not DI-injected:
 *   Hot paths (observers on cart_save, ConfigProvider on every checkout
 *   render) call Profiler::start() per request. DI'ing a singleton through
 *   the constructor would force ObjectManager into wiring chains that don't
 *   otherwise need it. Static is cheaper and the API is side-effect free.
 *
 * Why a `class_exists()` guard cached in a static:
 *   Tideways is optional in dev / on Adobe Commerce Cloud (Blackfire and
 *   New Relic auto-instrument). The class_exists() call is fast but not
 *   free; caching the result makes the second+ call O(1). When Tideways is
 *   absent every method becomes a true no-op — zero production overhead.
 *
 * Span naming convention:
 *   Etechflow_ABC_<EntryPoint> — e.g., Etechflow_ABC_CronTick,
 *   Etechflow_ABC_RuleMatch, Etechflow_ABC_EmailSend, Etechflow_ABC_Restore.
 *   Consistent prefix makes Tideways dashboards groupable per-module.
 *
 * Usage:
 *   $span = \Etechflow\AbandonedCart\Model\Performance\Profiler::start('Etechflow_ABC_CronTick');
 *   try {
 *       // ... work ...
 *   } finally {
 *       \Etechflow\AbandonedCart\Model\Performance\Profiler::stop($span);
 *   }
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Model\Performance;

final class Profiler
{
    private static ?bool $tidewaysAvailable = null;

    public static function start(string $name): ?object
    {
        if (self::$tidewaysAvailable === null) {
            self::$tidewaysAvailable = class_exists('\\Tideways\\Profiler', false);
        }

        if (!self::$tidewaysAvailable) {
            return null;
        }

        try {
            return \Tideways\Profiler::createSpan($name);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function stop(?object $span): void
    {
        if ($span === null) {
            return;
        }

        try {
            if (method_exists($span, 'stopTimer')) {
                $span->stopTimer();
            }
        } catch (\Throwable) {
            // Instrumentation must never surface to the customer.
        }
    }
}
