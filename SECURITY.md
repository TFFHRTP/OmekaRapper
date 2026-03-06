# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 0.1.x | :white_check_mark: |
| < 0.1.0 | :x: |

## Reporting a vulnerability

Do not open public issues for suspected vulnerabilities.

Instead, report privately to project maintainers with:

- Affected version(s)
- Reproduction steps / proof of concept
- Impact assessment
- Suggested mitigation (if available)

If private reporting infrastructure is not yet configured, use repository owner contact and mark the message as a security disclosure.

## Response targets

- Acknowledgement: within 3 business days
- Initial triage: within 7 business days
- Fix timeline: depends on severity and release constraints

## Security requirements for this module

- API keys must stay server-side.
- Never expose provider credentials to browser clients.
- Validate and bound user-submitted text before provider calls.
- Log safely; do not store sensitive source text unless explicitly required.
