# Security Policy

## Supported versions

Security fixes land on the latest minor release. While LibxaFrame is pre-1.0,
the minor number is the compatibility boundary, so "latest minor" is the only
supported line.

| Version | Supported |
|---|---|
| 0.8.x | ✅ |
| < 0.8 | ❌ — please upgrade |

Once 1.0 ships, the two most recent minor releases will be supported and this
table will say so.

## Reporting a vulnerability

**Please do not open a public issue, pull request, or discussion for a security
problem.** A public report is a disclosure, and it puts every application
running LibxaFrame at risk before a fix exists.

Report privately, either way:

- **GitHub Security Advisory** *(preferred)* — the
  [Report a vulnerability](https://github.com/libxa-framework/libxa/security/advisories/new)
  button on the Security tab. This gives us a private fork to develop and review
  the fix in.
- **Email** — `libxa@vyloxi.com`, subject prefixed `[SECURITY]`.

### What to include

The more of this you can provide, the faster it gets fixed:

- The affected version(s) and component.
- A description of the impact — what an attacker gains.
- Steps to reproduce, ideally the smallest possible proof of concept.
- Any mitigation you have found.

### What to expect

| Stage | Target |
|---|---|
| Acknowledgement of your report | 48 hours |
| Initial assessment and severity | 5 working days |
| Fix released, or a plan with a date | 30 days |

We will keep you updated as the fix progresses, credit you in the advisory and
the changelog unless you would rather stay anonymous, and let you know before we
publish.

### Disclosure

We follow coordinated disclosure. Once a fix is released we publish a GitHub
Security Advisory with a CVE where appropriate. Please give us the 30-day window
above before disclosing publicly — and tell us if you are working to a different
deadline, so we can plan around it rather than be surprised by it.

## Scope

In scope — this repository, and:

- Authentication and authorisation bypass
- SQL injection, XSS, CSRF, SSRF
- Remote code execution, including deserialisation
- Directory traversal and arbitrary file read/write
- Session fixation or hijacking
- Cryptographic weaknesses in `Libxa\Security`
- Injection through the Blade compiler

Out of scope:

- Vulnerabilities in **your** application code rather than the framework
- Anything requiring `APP_DEBUG=true` in production — that is documented as a
  development-only setting, and the debug error page deliberately shows stack
  traces
- Missing hardening headers that are the application's responsibility to set
- Denial of service through sheer request volume (that is an infrastructure
  concern)
- Automated scanner output with no demonstrated impact

## Security-relevant defaults

Worth knowing when you assess a report:

- `APP_DEBUG` **must** be `false` in production. With it on, the error page
  intentionally exposes stack traces and file paths.
- `APP_KEY` must be a real 32-byte key. `php libxa key:generate` produces one;
  the encrypter refuses to start without a valid key.
- `TRUSTED_PROXIES` is empty by default, so `X-Forwarded-For` is ignored. Only
  set it if you actually run a reverse proxy, and list its addresses — a value
  of `*` means anyone can spoof their client IP.
- Session cookies default to `HttpOnly` and `SameSite=Lax`. Set
  `SESSION_SECURE_COOKIE=true` when serving over HTTPS.
- CSRF protection is global middleware. Exempt specific routes with
  `session.csrf_except` rather than removing the middleware.
