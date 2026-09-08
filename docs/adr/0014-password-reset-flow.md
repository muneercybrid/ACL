# ADR-0014: Password Reset Flow

- **Status:** Accepted
- **Date:** 2026-09-08
- **Decision type:** Security / Authentication

## Context

ACL previously provided password authentication but did not provide a password
recovery workflow. Users who forgot their passwords had no supported way to
regain access.

The existing database already contains Laravel's `password_reset_tokens`
table, and the authentication configuration already defines a password broker
with a 60-minute token expiry.

Production email delivery is configured through Resend SMTP using ACL's
verified `no-reply@aclacademy.me` sender.

## Decision

ACL will use Laravel's native password broker and reset-token mechanism.

The public password-reset workflow will provide:

1. `GET /forgot-password` for requesting a reset link.
2. `POST /forgot-password` for submitting an email address.
3. A generic response that does not reveal whether the submitted email
   corresponds to an ACL account.
4. `GET /reset-password/{token}` for displaying the password-reset form.
5. `POST /reset-password` for securely applying the new password.
6. Existing 60-minute password-reset token expiry.
7. Token invalidation after a successful password reset.
8. Password confirmation and a minimum password length of eight characters.
9. Reset-request rate limiting to reduce abuse.
10. Regeneration of the user's remember token after a successful reset.
11. Password-reset links from the existing login page.

## Alternatives considered

### Custom reset-token implementation

Rejected because Laravel already provides a maintained password broker,
token generation, expiry handling, token verification, and invalidation.

### Longer-lived reset tokens

Rejected because a shorter lifetime reduces the window in which a stolen
reset link can be abused. The existing 60-minute configuration is retained.

### Account-existence-specific responses

Rejected because distinguishing valid and invalid email addresses would
create an account-enumeration channel.

## Security considerations

- Reset requests do not disclose account existence.
- Reset requests are rate limited.
- Tokens are handled by Laravel's password broker rather than application
  code.
- Expired and invalid tokens are rejected.
- Successfully consumed tokens are invalidated by the broker.
- Passwords are passed through Laravel's normal password hashing mechanism.
- The user's remember token is regenerated after a successful reset.
- Reset links use the application's configured URL and mail transport.
- No password-reset credentials or mail-provider secrets are exposed to the
  frontend.

## Consequences

### Positive

- Users can recover access without administrator intervention.
- The implementation fits the existing Laravel authentication architecture.
- No database migration is required.
- Existing production email infrastructure can deliver reset messages.
- The account-enumeration risk is reduced.

### Trade-offs

- Users must have access to the email address associated with their ACL
  account.
- Password reset depends on production mail delivery being available.
- Rate limiting may temporarily prevent repeated legitimate requests.

## Verification

The implementation includes feature tests covering:

- reset form rendering;
- generic responses for existing and unknown email addresses;
- reset-link generation;
- successful password reset;
- token invalidation after use;
- password confirmation;
- rejection of invalid reset tokens.

## Affected areas

- Authentication routes
- Authentication controller
- Authentication views
- Password-reset tests
- Security architecture documentation
