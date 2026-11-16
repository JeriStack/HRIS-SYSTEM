# Security Policy

NEXORA HR handles employee, payroll, leave, and confidential complaint data. We take security reports seriously.

- **Repository:** https://github.com/JeriStack/HRIS-SYSTEM
- **Lead Maintainer:** Jeril Mark Estinsu
- **Core Maintainer:** Kevin Quijano
- **Security Contact:** jerilpalin@gmail.com

## Supported Versions

Security fixes are applied to the latest code on the default branch. Older commits and forks do not receive patches.

## Reporting a Vulnerability

Do not open a public issue, pull request, or discussion for a security problem.

Report it privately by either method:

1. Use GitHub's private reporting: the **Security** tab of the repository, then **Report a vulnerability**.
2. Email jerilpalin@gmail.com with the subject `[SECURITY] NEXORA HR`.

Include as much of the following as you can:

- A description of the issue and its impact
- The affected page, endpoint, or file
- Steps to reproduce, or a proof of concept
- The role needed to exploit it (unauthenticated, Employee, HR, or Admin)
- Your suggested fix, if any

Use synthetic data only. Do not include real employee, payroll, or complaint data in your report. If you encountered real data while testing, stop, do not copy or share it, and tell us in your report.

## What to Expect

| Step | Target time |
| --- | --- |
| Acknowledgement of your report | 3 business days |
| Initial assessment and severity | 7 business days |
| Fix or mitigation plan | Based on severity, shared with you |

We will keep you informed, credit you in the fix notes if you want that, and ask you to wait until a fix is available before disclosing publicly.

## Scope

In scope:

- Authentication, session handling, and password or recovery flows
- Role-based access control bypass (for example, an Employee reaching HR or Admin data)
- Access to confidential support complaints by an unauthorized or conflicted user
- SQL injection, XSS, CSRF, file upload and path traversal flaws
- Exposure of personal data, secrets, or attachments

Out of scope:

- Denial of service and volumetric testing
- Social engineering or physical attacks
- Findings that need a compromised administrator account or a modified local setup
- Missing best-practice headers with no demonstrated impact
- Vulnerabilities in third-party dependencies with no reachable path in this project (report those upstream)

## Safe Harbor

If you act in good faith, test only against your own local or staging installation, avoid accessing other people's data, and follow this policy, we will not pursue action against you for your research.

## Deployment Guidance

When running your own instance:

- Keep `.env` files out of version control and rotate any secret that was exposed.
- Use the `.env.production.example` file as the starting point, and set strong unique credentials.
- Serve the application over HTTPS only.
- Keep PHP, MySQL, and Composer dependencies up to date.
