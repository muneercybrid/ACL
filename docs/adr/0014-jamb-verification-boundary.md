# ADR-0014: Normalize JAMB Verification Behind an ACL-Owned Boundary

## Status

Accepted — 2026-09-10 (updated 2026-09-15: direct HTTP provider)

## Context

JAMB has no official API available to ACL. The JAMB "CheckMatriculationList"
page is an ASP.NET Web Forms application whose search is a server-side postback
(`__doPostBack('lnkSearch','')`). Because the verification operation does not
require JavaScript execution in a browser, ACL communicates with JAMB using
direct HTTP requests:

1. `GET /CheckMatriculationList` — establishes a session and returns the
   ASP.NET hidden state fields (`__VIEWSTATE`, `__VIEWSTATEGENERATOR`,
   `__EVENTVALIDATION`) and the examination dropdown options.
2. `POST /CheckMatriculationList` — resubmits the hidden state plus
   `__EVENTTARGET=lnkSearch`, the resolved examination value, and the
   registration number.
3. HTML parsing — the response is interpreted into a normalized ACL
   verification result.

No browser automation (Selenium, Playwright, Chromium, Puppeteer, or a
Zyte-style extraction service) is required or used for this workflow.

The successful matriculation status returned by JAMB
("Congratulations, you are on the Matriculation List") is authoritative for
this verification boundary. ACL does not second-guess whether JAMB has placed
the candidate on the list.

## Decision

Student registration calls `JambMatriculationVerificationService`, which
implements the direct HTTP Web Forms workflow above. The service returns a
normalized result array consumed by `StudentRegistrationController::verify()`:

- `verified` (bool)
- `status` (provider state: `verified`, `not_found`, `invalid_input`,
  `provider_timeout`, `provider_unavailable`, `temporary_failure`,
  `manual_verification_required`, `ambiguous`, `pending`)
- `name`, `institution`, `programme` (extracted only on success)
- diagnostic keys (`provider_status_code`, `provider_url`, `raw_text`)

Failure states are kept distinct:

| Scenario                                       | Result                    |
|------------------------------------------------|---------------------------|
| Matriculation success                          | `verified = true`         |
| "You Did not Register for this Examination"    | `verified = false`, `not_found` |
| Malformed local input                          | `invalid_input`           |
| HTTP timeout                                   | `provider_timeout`        |
| DNS/connect/provider unreachable               | `provider_unavailable`    |
| Unexpected HTTP/server response                | `temporary_failure`       |
| Page received but status cannot be interpreted | `ambiguous`               |

A provider outage or technical failure is never evidence that a student is
invalid, and is never reported as `not_found`.

Each verification uses a fresh, isolated HTTP session (its own cookie jar);
`__VIEWSTATE`/`__EVENTVALIDATION`/cookies are never shared across requests or
cached globally. Examination-option values are parsed live from the JAMB
dropdown and cached for a bounded time; they are never derived
mathematically.

## Consequences

- Browser automation and Zyte extraction are no longer required for the JAMB
  boundary; the integration is a direct HTTP client with realistic headers,
  TLS verification enabled, and configurable timeouts.
- Transport and parsing failures continue to be distinguishable from an
  invalid candidate.
- Registrations remain isolated per request; one candidate's session state
  cannot leak to another.
- A future official JAMB API can replace the HTTP workflow inside the same
  service boundary without rewriting registration controllers.

## Security Implications

Provider HTML is not persisted as normal registration metadata. The `raw_text`
key carries only a short, safe classification label (e.g.
`jamb_matriculation_success`, `jamb_candidate_not_registered`). The encrypted
registration number remains only on the verification record; the account
stores its deterministic SHA-256 hash for conflict control. Registration
numbers are never logged, included in exception messages, or exposed through
the public registration API.

## Future Considerations

A manual-review user interface belongs to the institution-admin phase. This
decision intentionally supplies only the safe pending/manual state now.