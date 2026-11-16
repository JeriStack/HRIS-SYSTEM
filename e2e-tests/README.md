# HRIS-SYSTEM — End-to-End & UI Automation Testing Framework

Comprehensive, maintainable, production-quality UI automation suite using **Selenium WebDriver 4**, **Python 3.11+**, and **pytest**, specifically designed for **Brave Browser** as the primary local browser on Windows 11, with seamless fallback to Chrome and containerized execution.

---

## 1. Architecture Overview

The testing layer follows the industry-standard **Page Object Model (POM)** and is completely decoupled from the PHP backend and PHPUnit test layers:

```text
e2e-tests/
│
├── config/
│   ├── __init__.py
│   ├── settings.py             # Environment configuration, timeouts, database credentials
│   └── browser_factory.py      # Brave discovery, temporary profile sandbox, Chrome/Remote grid
│
├── pages/                      # Page Object Model encapsulating DOM interactions
│   ├── __init__.py
│   ├── base_page.py            # Reusable explicit waits, click/type, visibility helpers
│   ├── login_page.py           # /login page, password toggle, error banners
│   ├── otp_page.py             # /verify-otp screen, split digit inputs, resend
│   ├── hr_dashboard_page.py    # /hr/dashboard, metrics overview, sidebar navigation
│   ├── employee_dashboard_page.py # /employee/dashboard, balances, self-service actions
│   ├── employee_management_page.py # /hr/employees directory, filters, add employee form
│   ├── department_page.py      # /hr/departments directory, position alignment, add form
│   └── leave_management_page.py# /leaves/apply filing, /leaves HR review queue & FIFO
│
├── tests/                      # pytest test suites
│   ├── test_login.py           # Login UI, password masking toggle, invalid credentials
│   ├── test_otp.py             # OTP direct access guards, digit validations
│   ├── test_hr_dashboard.py    # HR operational widgets and navigation
│   ├── test_employee_dashboard.py # Employee self-service widgets and balance cards
│   ├── test_employee_management.py # Directory table, search filtering, registration
│   ├── test_department_position.py # Organizational departments, creation validations
│   ├── test_leave_workflow.py  # Leave application form, business days, HR review queue
│   └── test_rbac.py            # RBAC route guards for unauthenticated visitors
│
├── utils/
│   ├── __init__.py
│   └── auth_helper.py          # Real 2FA flow orchestrator, OTP synchronization
│
├── reports/                    # Self-contained HTML test execution reports
├── screenshots/                # Automatic failure screenshot captures
├── .env.example                # Configuration template
├── conftest.py                 # Shared fixtures, driver lifecycle, screenshot hooks
├── pytest.ini                  # Strict markers, test paths, reporting flags
├── requirements.txt            # Python dependencies (Selenium, pytest, pytest-html, pymysql)
└── README.md                   # This documentation
```

---

## 2. Brave Browser Integration (Primary Local Browser)

Brave is the user's default browser and is configured as the **primary local browser** on Windows 11.

### Automatic Binary Discovery
The browser factory inspects the following paths in priority order:
1. `BRAVE_BINARY` environment variable.
2. `%LOCALAPPDATA%\BraveSoftware\Brave-Browser\Application\brave.exe`
3. `C:\Program Files\BraveSoftware\Brave-Browser\Application\brave.exe`
4. `C:\Program Files (x86)\BraveSoftware\Brave-Browser\Application\brave.exe`
5. Linux paths (`/usr/bin/brave-browser`, etc.)

### Driver Management & Profile Isolation
- Uses Selenium Manager to resolve the exact compatible ChromeDriver for Brave's Chromium version.
- **Dedicated Temporary Profile**: Every test execution launches with a clean `tempfile.mkdtemp(prefix="brave_test_profile_")` passed via `--user-data-dir`. Automation never touches, inspects, or corrupts personal bookmarks, extensions, cookies, or saved credentials.
- Temporary profiles are automatically purged on test teardown.

---

## 3. Quick Start (Local Execution on Windows 11)

### Prerequisites
- Python 3.10+ installed.
- Brave Browser installed.
- Running HRIS Docker stack (`http://localhost:8080`).

### Setup Environment
```powershell
# Navigate to e2e-tests root
cd e2e-tests

# Create virtual environment
python -m venv .venv

# Activate virtual environment
.\.venv\Scripts\Activate.ps1

# Install dependencies
pip install -r requirements.txt

# Copy environment variables
cp .env.example .env
```

### Running Test Suites

```powershell
# Run smoke tests in headless Brave (fastest)
pytest tests/ -m smoke --headless

# Run tests with visible Brave window for interactive debugging
pytest tests/test_login.py

# Run all test suites
pytest tests/ -v

# Run with custom application URL or fallback Chrome
pytest tests/ --browser=chrome --headless --app-url=http://localhost:8080
```

---

## 4. Test Markers

| Marker | Description |
|---|---|
| `@pytest.mark.smoke` | Quick smoke validation across core workflows |
| `@pytest.mark.auth` | Authentication, 2FA OTP, password toggling |
| `@pytest.mark.dashboard` | HR and Employee dashboard widgets and metrics |
| `@pytest.mark.employee` | Employee directory and registration |
| `@pytest.mark.department`| Department directory and position alignments |
| `@pytest.mark.leave` | Leave application form and HR review queue |
| `@pytest.mark.rbac` | Role-based access control and unauthorized route redirects |
| `@pytest.mark.support` | Employee Help & Support and confidential complaint E2E workflow tests |
| `@pytest.mark.announcements` | HR Announcements Management and Employee Announcement Feed E2E workflow tests |

Example filtered run:
```powershell
pytest tests/ -m "smoke and not leave"
```

---

## 5. Docker Integration (`docker-compose.e2e.yml`)

To run isolated containerized UI tests or capture emails:

```powershell
# Launch Selenium Standalone Chrome and Mailpit on existing hris_network
docker compose -f docker-compose.e2e.yml up -d

# Run tests targeting the remote Selenium Grid
pytest tests/ --browser=remote
```

- Selenium Grid Hub: `http://localhost:4444`
- Live VNC Web View: `http://localhost:7900` (password: `secret`)
- Mailpit UI: `http://localhost:8025`

---

## 6. GitHub Actions CI/CD Integration

Automated in `.github/workflows/e2e-tests.yml`:
- Triggered on push and pull requests to `main`, `development`, and `HRIS-*` feature branches.
- Starts PHP application and MySQL service.
- Executes headless Chrome across all test suites.
- Generates and uploads `report.html` and failure screenshots as workflow artifacts.

