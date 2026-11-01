"""
HRIS-SYSTEM — End-to-End Testing Configuration Settings
Manages environment variables, browser settings, target URLs, and test credentials.
"""

import os
from pathlib import Path
from dotenv import load_dotenv

# Base Directory: root of e2e-tests
E2E_ROOT = Path(__file__).resolve().parent.parent
REPO_ROOT = E2E_ROOT.parent

# Load .env if present in e2e-tests or repo root
load_dotenv(E2E_ROOT / ".env")
load_dotenv(REPO_ROOT / ".env")

class Settings:
    # Target Application URL (defaults to running local Docker Nginx stack on port 8080)
    BASE_URL: str = os.getenv("HRIS_BASE_URL", "http://localhost:8080").rstrip("/")

    # Browser Engine: 'brave' (default), 'chrome', or 'remote'
    BROWSER: str = os.getenv("SELENIUM_BROWSER", "brave").lower()

    # Headless Execution: False for visible local debugging, True in CI
    HEADLESS: bool = os.getenv("SELENIUM_HEADLESS", "false").lower() in ("true", "1", "yes")

    # Remote WebDriver URL (used when BROWSER='remote')
    REMOTE_URL: str = os.getenv("SELENIUM_REMOTE_URL", "http://localhost:4444/wd/hub")

    # Explicit Brave binary override
    BRAVE_BINARY: str = os.getenv("BRAVE_BINARY", "")

    # Timeouts (in seconds)
    EXPLICIT_WAIT: int = int(os.getenv("SELENIUM_EXPLICIT_WAIT", "10"))
    PAGE_LOAD_TIMEOUT: int = int(os.getenv("SELENIUM_PAGE_LOAD_TIMEOUT", "60"))


    # Artifact Directories
    REPORTS_DIR: Path = E2E_ROOT / "reports"
    SCREENSHOTS_DIR: Path = E2E_ROOT / "screenshots"

    # Standard Seed Test Accounts
    HR_EMAIL: str = os.getenv("TEST_HR_EMAIL", "marcus.tan@company.local")
    HR_PASSWORD: str = os.getenv("TEST_HR_PASSWORD", "Admin@123")
    HR_NAME: str = "Marcus Tan"

    EMP_EMAIL: str = os.getenv("TEST_EMP_EMAIL", "david.cruz@company.local")
    EMP_PASSWORD: str = os.getenv("TEST_EMP_PASSWORD", "Employee@123")
    EMP_NAME: str = "David Cruz"

    # Database Configuration (Docker MySQL published port 3307 on host, 3306 in Docker network)
    DB_HOST: str = os.getenv("DB_HOST", "127.0.0.1")
    DB_PORT: int = int(os.getenv("DB_PORT", "3307"))
    DB_NAME: str = os.getenv("DB_DATABASE", "hris_db")
    DB_USER: str = os.getenv("DB_USERNAME", "hris_user")
    DB_PASSWORD: str = os.getenv("DB_PASSWORD", "hris_password")

    # Mailpit REST API (optional for live OTP reading)

    MAILPIT_API_URL: str = os.getenv("MAILPIT_API_URL", "http://localhost:8025/api/v1").rstrip("/")


settings = Settings()
