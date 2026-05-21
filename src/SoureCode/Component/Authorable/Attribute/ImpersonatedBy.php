<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Attribute;

/**
 * Opt-in marker: the listener stamps the real user behind a `switch_user`
 * impersonation onto this property. When the security token is NOT an
 * impersonation, the value is left at null.
 *
 * Configure an `ImpersonatorProviderInterface` to expose the impersonator
 * to the listener.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ImpersonatedBy
{
}
