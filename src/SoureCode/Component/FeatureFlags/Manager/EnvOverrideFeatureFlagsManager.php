<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Manager;

use Doctrine\Common\Collections\Collection;

/**
 * Decorates another manager and lets environment variables short-circuit
 * isEnabled() lookups, so operators can flip a flag without touching the
 * database — e.g. FEATURE_NEW_CHECKOUT=1.
 *
 * Override semantics:
 *   - "1", "true", "on", "yes"   → enabled (case-insensitive)
 *   - "0", "false", "off", "no"  → disabled
 *   - missing or empty           → fall through to the inner manager
 *
 * The name is uppercased and "._-" characters are converted to "_" so
 * "billing.beta-rates" maps to "<PREFIX>BILLING_BETA_RATES".
 */
final class EnvOverrideFeatureFlagsManager extends AbstractFeatureFlagsManager
{
    public function __construct(
        private readonly FeatureFlagsManagerInterface $inner,
        private readonly string $prefix = 'FEATURE_',
        private readonly bool $enabled = true,
    ) {
    }

    public function isEnabled(string $name): bool
    {
        self::validateName($name);

        if ($this->enabled) {
            $override = $this->resolveOverride($name);

            if ($override !== null) {
                return $override;
            }
        }

        return $this->inner->isEnabled($name);
    }

    public function has(string $name): bool
    {
        return $this->inner->has($name);
    }

    public function enable(string $name): void
    {
        $this->inner->enable($name);
    }

    public function disable(string $name): void
    {
        $this->inner->disable($name);
    }

    public function remove(string $name): void
    {
        $this->inner->remove($name);
    }

    public function all(): Collection
    {
        return $this->inner->all();
    }

    /**
     * Translates a flag name into the env var an operator would set to
     * override it. Uppercases the name and converts ".-" to "_", then
     * prepends the configured prefix. Public so docs and tooling can show
     * the exact env var to set instead of asking operators to read the
     * source.
     *
     * Examples (with default prefix "FEATURE_"):
     *   - "billing.beta-rates"  → "FEATURE_BILLING_BETA_RATES"
     *   - "checkout.v2"         → "FEATURE_CHECKOUT_V2"
     */
    public function envKeyFor(string $name): string
    {
        return $this->prefix . strtoupper(strtr($name, '.-', '__'));
    }

    private function resolveOverride(string $name): ?bool
    {
        $key = $this->envKeyFor($name);
        $raw = getenv($key);

        if ($raw === false || $raw === '') {
            return null;
        }

        $normalized = strtolower($raw);

        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return null;
    }
}
