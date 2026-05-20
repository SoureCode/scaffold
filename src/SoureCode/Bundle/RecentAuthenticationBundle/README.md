# sourecode/recent-authentication-bundle

Session-backed "re-authentication" gate for Symfony. Routes that need a fresh password confirmation can require the `IS_AUTHENTICATED_RECENTLY` security attribute; users who don't have it are redirected to login and bounced back automatically.

## When to use

Protecting sensitive actions behind a fresh password prompt — change email, delete account, payout, admin impersonation.

## When not to use

You want to replace the firewall, gate stateless APIs, or build a full step-up MFA flow. This bundle only marks "the user just confirmed credentials" and lets voters consume that signal.

## Install

Part of the `scaffold` monorepo. Symfony Flex registers the bundle automatically.

## Configuration

```yaml
recent_authentication:
    ttl: 900             # seconds; how long a fresh auth stays valid
    login_route: app_login
```

| key | default | meaning |
|-----|---------|---------|
| `ttl` | `900` | Lifetime of a recent-auth marker, in seconds. Must be `>= 1`. |
| `login_route` | `app_login` | Route name to redirect to when `IS_AUTHENTICATED_RECENTLY` is denied. |

## Minimal example

```php
use SoureCode\Bundle\RecentAuthenticationBundle\Security\Voter\RecentAuthenticationVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(RecentAuthenticationVoter::IS_AUTHENTICATED_RECENTLY)]
public function changeEmail(): Response { … }
```

```twig
{% if is_authenticated_recently() %}
    <a href="{{ path('account_delete') }}">Delete account</a>
{% endif %}
```

## Public surface

| Class / id | Role |
|------------|------|
| `RecentAuthenticationVoter::IS_AUTHENTICATED_RECENTLY` | Security attribute string. |
| `RecentAuthentication` | Inject for manual control: `mark()`, `clear()`, `isActive()`, `setReturnPath()`, `takeReturnPath()`. |
| `is_authenticated_recently()` | Twig function, same semantics as the voter. |

## Behavior

- A denied `IS_AUTHENTICATED_RECENTLY` check redirects to `login_route` and remembers where the user came from.
- A successful login (Symfony `LoginSuccessEvent`) marks the session and bounces the user back to the original URL — one round trip.
- The marker has a hard TTL; calls to a protected route do not extend it.
- `isActive()` is `false` on stateless / sub-requests.

### Manual mark

Use when running a dedicated "confirm password" form:

```php
public function confirmPassword(RecentAuthentication $recent): Response
{
    // validate the submitted password against the current user
    $recent->mark();
}
```

## Composition

- **Firewall** — combine with `IS_AUTHENTICATED_FULLY` if you need both.
- [`Authorable`](../../Component/Authorable/README.md) — pair with `#[ChangedBy]` to record *who* performed the recently-confirmed change.

## Limits

- Requires a session. Stateless firewalls see `isActive()` as `false`.
- The remembered return path is whatever URI the user hit. Don't put secrets in the query string.

## Stability

`IS_AUTHENTICATED_RECENTLY`, the `RecentAuthentication` service shape, the Twig function, and the config keys are stable.
