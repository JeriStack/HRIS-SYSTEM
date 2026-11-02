---
target: app/views/home/index.php
total_score: 10
max_score: 32
na_heuristics: 7,10
p0_count: 2
p1_count: 1
target_identity: "file:C:\\xampp\\htdocs\\GitHub_Clone_File_Projects\\HRIS-SYSTEM\\app\\views\\home\\index.php"
target_fingerprint: "sha256:8726bd7a81efc896daf7d38447cc612dfbed43962904e94a13edf4cfa754b430"
target_path: "C:\\xampp\\htdocs\\GitHub_Clone_File_Projects\\HRIS-SYSTEM\\app\\views\\home\\index.php"
timestamp: 2026-11-01T06-13-03Z
slug: app-views-home-index-php
---
#### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|:-----:|-----------|
| 1 | Visibility of System Status | 2 | Static mockup elements appear clickable; mobile menu lacks accessible state announcements |
| 2 | Match System / Real World | 1 | Severe vocabulary clash: dumps academic CS Big-O notation, memory stacks, and sorting benchmarks on corporate HR users |
| 3 | User Control and Freedom | 2 | Mobile viewports completely hide the header CTA and omit a "Sign In" link from the mobile drawer |
| 4 | Consistency and Standards | 0 | Total token violation: `landing.css` implements an unapproved dark purple/violet template (`#8b5cf6`), defying `DESIGN.md`'s Cobalt Blue (`#1D4ED8`) |
| 5 | Error Prevention | 2 | Secondary CTA links directly route to `/hr/dashboard` and `/employee/dashboard` without clarifying authentication requirements |
| 6 | Recognition Rather Than Recall | 2 | Visual overload across 20+ scattered cards; unreadable 6px–8px micro-typography inside the hero browser preview |
| 7 | Flexibility and Efficiency | n/a | Persuade-mode landing surface; administrative accelerators apply to authenticated dashboards |
| 8 | Aesthetic and Minimalist Design | 1 | Extreme visual clutter: 7 distinct sections competing with purple glow radial gradients, neon badges, and redundant card grids |
| 9 | Error Recovery | 2 | Informational page; prototype and demo links lack explanatory pre-flight notices when bouncing to auth |
| 10 | Help and Documentation | n/a | Public landing surface; operational documentation and user guides live within the authenticated portal |
| **Total** | | **10 / 32** | **Poor (Major UX and token overhaul required)** |

*Scored 8 of 10 applicable heuristics (applicable maximum: 32). Rating band: Poor (31.2%).*

#### Design Specificity Verdict

**LLM Assessment:** The current landing page suffers from an acute identity crisis and visual dissociation from the rest of the application. While [`DESIGN.md`](DESIGN.md) specifies **"The Reliable Instrument"**—a calm, high-contrast, slate-and-canvas design language (`#F8FAFC`, `#0F172A`) with disciplined 10% Cobalt Blue accents (`#1D4ED8`) and native system typography—the landing page has hijacked the product with a generic "Dark Web3 / Developer SaaS" theme (`#0a0a0a` background, `#8b5cf6` violet gradients, floating radial neon glows, and external Google Fonts CDN imports). Furthermore, the narrative is split between an enterprise HR compliance portal and an undergraduate CS data structures lab (`CustomStack.php`, Big-O complexity badges, sorting benchmarks), creating an incoherent story that dilutes credibility for corporate buyers and employees alike.

**Deterministic Scan:** The automated detector revealed **78 static CSS violations** in [`public/assets/css/landing.css`](public/assets/css/landing.css) (including 31 hardcoded off-palette colors, 37 off-ramp font sizes, and unapproved web fonts) and **71 rendered DOM violations** on [`app/views/home/index.php`](app/views/home/index.php). Key automated catches include:
- `ai-color-palette` & `design-system-color`: Pervasive use of `#8b5cf6`, `#a855f7`, and `#6366f1` gradient buttons and purple glow halos.
- `low-contrast`: 4.0:1 contrast on hero gradient buttons (`#ffffff` on `#a855f7`), failing WCAG AA (4.5:1).
- `skipped-heading`: Accessibility outline breaks by jumping directly from `<h1>` to `<h3>` (skipping `<h2>`).
- `undersized-ui-text`: 32 instances of sub-11px text (ranging down to 6px) inside the hero browser preview.
*(Note: A direct CLI regex scan on the raw `.php` template returned 0 inline findings because styles are cleanly separated into `landing.css`, where the 78 violations reside).*

**Visual Overlays:** No live dev-server browser injection was attached in this CLI session; findings were evaluated via static AST parsing and rendered DOM analysis.

#### Overall Impression
The landing page has strong structural bones and impressive interactive ambition (such as the live browser mockup), but it is wearing the wrong clothes. It looks like an AI crypto developer tool rather than an enterprise Human Resources platform, and it completely traps mobile users by omitting a sign-in button. Bringing the palette into strict alignment with `DESIGN.md` and clarifying the primary HR conversion path will dramatically improve authority and conversion.

#### What's Working
1. **Semantic HTML5 Foundation:** Proper document structure using native landmark tags (`<header>`, `<main>`, `<section>`, `<article>`, `<footer>`, `<nav>`) with clean structural separation.
2. **Detailed Hero Mockup Archetype:** The dashboard browser preview (`.browser-mockup`) with realistic employee data (John Cruz, Ana Reyes, FIFO Queue) effectively grounds the product in reality rather than abstract marketing illustrations.
3. **Responsive Grid & Reduced Motion:** The stylesheet includes a clean mobile grid collapse mechanism and already provides an initial `@media (prefers-reduced-motion: reduce)` safety block.

#### Priority Issues

- **[P0] Mobile Conversion Failure: Missing Sign-In CTA on Mobile**
  - **Why it matters:** On screens `< 768px`, `.header-cta` is set to `display: none`, and the `#mobileDrawer` navigation list contains only page anchors (`#home`, `#features`, `#roles`), completely omitting a "Sign In" link. Workforce employees visiting on mobile devices cannot log in.
  - **Fix:** Keep a compact "Sign In" button visible in the mobile header next to the hamburger toggle, and insert an unmissable primary "Sign In" button inside the mobile drawer.
  - **Suggested command:** `/impeccable harden app/views/home/index.php`

- **[P0] Brand & Design System Disconnect (Dark Purple Neon Drift)**
  - **Why it matters:** Violates `DESIGN.md` ("The Reliable Instrument") and breaks visual continuity with the authenticated dashboard. When users sign in, they jump from a dark violet template (`#0a0a0a` / `#8b5cf6`) to a crisp light slate interface (`#F8FAFC` / `#1D4ED8`), creating extreme visual whiplash.
  - **Fix:** Purge violet/purple gradients and dark neon glow halos. Reskin `landing.css` into the official enterprise palette: neutral slate canvas (`#F8FAFC`), deep navy text (`#0F172A`), Cobalt Blue primary actions (`#1D4ED8`), and crisp hairline borders (`#E2E8F0`).
  - **Suggested command:** `/impeccable polish public/assets/css/landing.css`

- **[P1] Domain Identity Crisis: Alienating Enterprise Buyers with CS Lab Noise**
  - **Why it matters:** Front-and-center technical jargon (`CustomStack.php`, `LinkedList.php`, `O(1)`, Big-O complexity tables) confuses workforce employees seeking leave management and triggers skepticism in enterprise HR directors evaluating security and compliance.
  - **Fix:** Dedicate 85% of the page narrative to core HR capabilities: workforce directories, ACID leave approval integrity, 2FA security, and self-service. Demote the DSA Laboratory into an isolated, elegant secondary "Technical Architecture & Algorithm Lab" section.
  - **Suggested command:** `/impeccable distill app/views/home/index.php`

- **[P2] Micro-Typography & Unreadable Sizing (6px–8px)**
  - **Why it matters:** Sub-pixel typography inside `.browser-mockup` (6px table text, 7px address bar, 8px badges) fails WCAG legibility guidelines and creates severe cognitive eye strain.
  - **Fix:** Increase the hero mockup scale and enforce an accessibility floor of at least 11px for badges and 13px–14px for preview tables, or mark purely illustrative elements with `aria-hidden="true"`.
  - **Suggested command:** `/impeccable typeset public/assets/css/landing.css`

- **[P2] Heading Level Accessibility Skip**
  - **Why it matters:** Jumping directly from `<h1>` to `<h3>` breaks screen reader document outlines and violates WCAG 2.1 SC 1.3.1 (Info and Relationships).
  - **Fix:** Restructure heading levels in `app/views/home/index.php` so all section titles use `<h2>` before nested feature cards use `<h3>`.
  - **Suggested command:** `/impeccable layout app/views/home/index.php`

#### Persona Red Flags

- **Jordan (First-Timer / Workforce Employee):**
  - *Goal:* Access employee self-service to check vacation leave and submit a request.
  - *Failure:* Visits on a mobile phone. There is no "Sign In" button in the header or in the mobile hamburger drawer. Scrolls down and encounters "Stack LIFO Memory Optimization" and "Merge Sort Benchmarks", assuming they arrived at a developer homework site. **Result: Immediate drop-off.**

- **Alex (Executive HR Director / Corporate Buyer):**
  - *Goal:* Evaluate the platform for data integrity, compliance audit trails, and workforce governance.
  - *Failure:* Encounters glowing cyberpunk violet gradients, floating ambient blobs, and footer disclaimers stating *"Academic Software Project. Prototype Views."* **Result: Loss of institutional trust.**

- **Sam (Accessibility-Dependent User):**
  - *Goal:* Navigate via keyboard and screen reader.
  - *Failure:* Screen reader heading navigation breaks due to skipped `<h2>` tags. Unlabelled buttons inside `.browser-mockup` trap keyboard focus and announce meaningless non-functional clicks. Primary buttons fail the 4.5:1 contrast floor. **Result: Accessibility roadblock.**

#### Minor Observations
1. **Database Schema Leak:** The footer explicitly outputs `Database: hris_db (MySQL/InnoDB)`, exposing internal database naming to unauthenticated visitors.
2. **External Google Fonts CDN:** Importing external Google Fonts over HTTP introduces unnecessary network roundtrips and potential layout shifts, violating the System Native Rule.
3. **Excessive Radial Gradients:** Seven consecutive sections repeat `::before` / `::after` radial glow pseudo-elements, adding unnecessary GPU composite layer overhead.

#### Questions to Consider
1. Should the landing page transition completely to the clean, institutional light-mode slate aesthetic of the authenticated dashboard to provide an honest, seamless preview of the real product?
2. Would isolating the DSA Benchmarks into a dedicated "CS Architecture Showcase" tab or sub-page allow the core HRIS value proposition to breathe and speak directly to enterprise buyers?
3. What is the single conversion action we want from every visitor: signing into the portal, or exploring an interactive guided demo?
