"""
HRIS-SYSTEM — pytest Configuration & Shared Fixtures
Manages WebDriver lifecycle, temporary profile cleanup, screenshot capture on failure,
and test credentials injection.
"""

import os
import shutil
import sys
from datetime import datetime
from pathlib import Path
from typing import Generator, Dict, Any

import pytest
from selenium import webdriver

# Ensure e2e-tests root is on python path for clean relative imports
E2E_ROOT = Path(__file__).resolve().parent
if str(E2E_ROOT) not in sys.path:
    sys.path.insert(0, str(E2E_ROOT))

from config.settings import settings
from config.browser_factory import BrowserFactory


def pytest_addoption(parser: pytest.Parser) -> None:
    """CLI options for flexible test execution."""
    parser.addoption(
        "--browser",
        action="store",
        default=settings.BROWSER,
        help="Target browser: brave (default), chrome, or remote",
    )
    parser.addoption(
        "--headless",
        action="store_true",
        default=settings.HEADLESS,
        help="Run browser in headless mode",
    )
    parser.addoption(
        "--app-url",
        action="store",
        default=settings.BASE_URL,
        help="Base application URL (default: http://localhost:8080)",
    )


def pytest_configure(config: pytest.Config) -> None:
    """Sync CLI --app-url into settings.BASE_URL and anchor HTML report path under e2e-tests/reports/."""
    app_url = config.getoption("--app-url", default=None)
    if app_url:
        settings.BASE_URL = str(app_url).rstrip("/")

    if hasattr(config.option, "htmlpath") and not config.option.htmlpath:
        settings.REPORTS_DIR.mkdir(parents=True, exist_ok=True)
        config.option.htmlpath = str(settings.REPORTS_DIR / "report.html")


@pytest.fixture(scope="session", autouse=True)
def setup_directories() -> None:
    """Ensure artifacts directories exist."""
    settings.REPORTS_DIR.mkdir(parents=True, exist_ok=True)
    settings.SCREENSHOTS_DIR.mkdir(parents=True, exist_ok=True)


@pytest.fixture(scope="function")
def driver(request: pytest.FixtureRequest) -> Generator[webdriver.Remote, None, None]:
    """
    Initializes an isolated browser instance for each test.
    Cleans up the WebDriver and its temporary user profile on teardown.
    """
    browser_choice = request.config.getoption("--browser") or settings.BROWSER
    headless_choice = request.config.getoption("--headless")

    driver_instance, temp_profile = BrowserFactory.create_driver(
        browser=browser_choice,
        headless=headless_choice
    )

    # Attach driver to request node for screenshot hook access
    request.node.driver = driver_instance

    try:
        yield driver_instance
    finally:
        try:
            driver_instance.quit()
        except Exception as e:
            print(f"[conftest] Warning closing driver: {e}", file=sys.stderr)

        if temp_profile and os.path.exists(temp_profile):
            try:
                shutil.rmtree(temp_profile, ignore_errors=True)
            except Exception as e:
                print(f"[conftest] Warning removing temp profile: {e}", file=sys.stderr)


@pytest.fixture(scope="session")
def base_url(request: pytest.FixtureRequest) -> str:
    """Returns the base application URL."""
    return str(request.config.getoption("--app-url") or settings.BASE_URL).rstrip("/")


@pytest.fixture(scope="session")
def hr_credentials() -> Dict[str, str]:
    """Standard seeded HR Administrator test credentials."""
    return {
        "email": settings.HR_EMAIL,
        "password": settings.HR_PASSWORD,
        "name": settings.HR_NAME,
        "role": "hr"
    }


@pytest.fixture(scope="session")
def employee_credentials() -> Dict[str, str]:
    """Standard seeded Employee test credentials."""
    return {
        "email": settings.EMP_EMAIL,
        "password": settings.EMP_PASSWORD,
        "name": settings.EMP_NAME,
        "role": "employee"
    }


@pytest.fixture(scope="function")
def authenticated_hr(driver):
    """Logs in as HR and returns the authenticated driver at /hr/dashboard."""
    from utils.auth_helper import AuthHelper
    AuthHelper.login_as_hr(driver)
    return driver


@pytest.fixture(scope="function")
def authenticated_employee(driver):
    """Logs in as Employee and returns the authenticated driver at /employee/dashboard."""
    from utils.auth_helper import AuthHelper
    AuthHelper.login_as_employee(driver)
    return driver



@pytest.hookimpl(tryfirst=True, hookwrapper=True)
def pytest_runtest_makereport(item: pytest.Item, call: pytest.CallInfo):
    """
    Captures a screenshot automatically on test failure and saves it to screenshots/.
    Integrates failure screenshot directly into pytest-html report.
    """
    outcome = yield
    report = outcome.get_result()

    if report.when == "call" and report.failed:
        driver_instance = getattr(item, "driver", None)
        if driver_instance:
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            safe_name = item.name.replace("[", "_").replace("]", "_").replace("/", "_")
            screenshot_path = settings.SCREENSHOTS_DIR / f"FAILURE_{safe_name}_{timestamp}.png"

            try:
                driver_instance.save_screenshot(str(screenshot_path))
                print(f"\n[Screenshot] Saved failure evidence to: {screenshot_path}")

                # Attach to pytest-html report if plugin is active (pytest-html 4.x uses report.extras)
                extras = list(getattr(report, "extras", []))
                pytest_html = item.config.pluginmanager.getplugin("html")
                if pytest_html is not None:
                    relative_path = os.path.relpath(screenshot_path, settings.REPORTS_DIR)
                    extras.append(pytest_html.extras.image(relative_path))
                    report.extras = extras
            except Exception as e:
                print(f"[conftest] Failed to capture screenshot on test failure: {e}", file=sys.stderr)

            # Best-effort capture of severe browser console errors for faster CI diagnosis
            try:
                logs = driver_instance.get_log("browser")
                severe = [entry for entry in logs if entry.get("level") == "SEVERE"]
                if severe:
                    print("\n[Browser Console SEVERE]", file=sys.stderr)
                    for entry in severe[:10]:
                        print(f"  - {entry.get('message', '')}", file=sys.stderr)
            except Exception:
                pass
