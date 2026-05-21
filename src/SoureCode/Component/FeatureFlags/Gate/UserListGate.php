<?php

declare(strict_types=1);

namespace SoureCode\Component\FeatureFlags\Gate;

/**
 * Allow-list / deny-list by user identifier. Allow wins over deny when both
 * match (use either, not both, unless you really want that ordering).
 *
 * @phpstan-type Lists array{allow?: list<string>, deny?: list<string>}
 */
final class UserListGate implements FeatureGateInterface
{
    /**
     * @param array<string, Lists> $rules flag name → {allow: [...], deny: [...]}
     */
    public function __construct(
        private readonly array $rules,
    ) {
    }

    public function decide(string $name, array $context = []): ?bool
    {
        if (!array_key_exists($name, $this->rules)) {
            return null;
        }

        $userId = $context['user_id'] ?? null;

        if ($userId === null) {
            return null;
        }

        $rule = $this->rules[$name];
        $userId = (string) $userId;

        if (in_array($userId, $rule['allow'] ?? [], true)) {
            return true;
        }

        if (in_array($userId, $rule['deny'] ?? [], true)) {
            return false;
        }

        return null;
    }
}
