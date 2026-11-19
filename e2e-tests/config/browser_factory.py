"""
HRIS-SYSTEM — Selenium Browser Factory
Initializes WebDriver sessions for Brave (default), Chrome, or Remote Selenium Grid.
Guarantees isolated temporary user profiles, clean shutdowns, and explicit error reporting.
"""

import os
import shutil
import tempfile
from pathlib import Path
from typing import Optional, Tuple

from selenium import webdriver
from selenium.webdriver.chrome.options import Options as ChromeOptions
from selenium.webdriver.chrome.service import Service as ChromeService

from config.settings import settings


class BrowserFactory:
    """Factory for creating configured Selenium WebDriver instances."""

    BRAVE_WINDOWS_SEARCH_PATHS = [
        Path(os.environ.get("LOCALAPPDATA", "")) / "BraveSoftware" / "Brave-Browser" / "Application" / "brave.exe",
        Path("C:/Program Files/BraveSoftware/Brave-Browser/Application/brave.exe"),
        Path("C:/Program Files (x86)/BraveSoftware/Brave-Browser/Application/brave.exe"),
    ]

    BRAVE_LINUX_SEARCH_PATHS = [
        Path("/usr/bin/brave-browser"),
        Path("/usr/bin/brave"),
        Path("/opt/brave.com/brave/brave-browser"),
    ]

    @classmethod
    def resolve_brave_binary(cls) -> Path:
        """
        Locates the Brave browser executable.
        Checks explicit BRAVE_BINARY env var, then known standard installation paths.
        Raises FileNotFoundError if Brave is missing.
        """
        # 1. Explicit override via settings / environment variable
        if settings.BRAVE_BINARY:
            custom_path = Path(settings.BRAVE_BINARY)
            if custom_path.is_file():
                return custom_path
            raise FileNotFoundError(
                f"Configured BRAVE_BINARY does not exist: {settings.BRAVE_BINARY}"
            )

        # 2. Windows known paths
        if os.name == "nt":
            for candidate in cls.BRAVE_WINDOWS_SEARCH_PATHS:
                try:
                    if candidate.is_file():
                        return candidate
                except (PermissionError, OSError):
                    continue

        # 3. Linux known paths
        else:
            for candidate in cls.BRAVE_LINUX_SEARCH_PATHS:
                if candidate.is_file():
                    return candidate

        searched = [str(p) for p in (cls.BRAVE_WINDOWS_SEARCH_PATHS if os.name == "nt" else cls.BRAVE_LINUX_SEARCH_PATHS)]
        raise FileNotFoundError(
            "Brave Browser executable was not found on this system.\n"
            f"Searched standard paths:\n  - " + "\n  - ".join(searched) + "\n"
            "To resolve:\n"
            "  1. Install Brave Browser from https://brave.com/, or\n"
            "  2. Set the BRAVE_BINARY environment variable pointing to brave.exe, or\n"
            "  3. Run tests with SELENIUM_BROWSER=chrome if Google Chrome is installed."
        )

    @classmethod
    def create_driver(
        cls,
        browser: Optional[str] = None,
        headless: Optional[bool] = None
    ) -> Tuple[webdriver.Remote, Optional[str]]:
        """
        Creates a WebDriver instance according to target browser.
        Returns a tuple of (driver, temp_profile_dir).
        The caller is responsible for deleting temp_profile_dir on teardown.
        """
        browser_type = (browser or settings.BROWSER).lower()
        is_headless = settings.HEADLESS if headless is None else headless
        temp_profile_dir: Optional[str] = None

        if browser_type == "brave":
            brave_path = cls.resolve_brave_binary()
            options = ChromeOptions()
            options.binary_location = str(brave_path)

            # Create an isolated temporary browser profile so automation never touches user data
            temp_profile_dir = tempfile.mkdtemp(prefix="brave_test_profile_")
            options.add_argument(f"--user-data-dir={temp_profile_dir}")

            cls._apply_common_chrome_options(options, is_headless)
            try:
                driver = webdriver.Chrome(options=options)
            except Exception:
                shutil.rmtree(temp_profile_dir, ignore_errors=True)
                raise

        elif browser_type == "chrome":
            options = ChromeOptions()
            temp_profile_dir = tempfile.mkdtemp(prefix="chrome_test_profile_")
            options.add_argument(f"--user-data-dir={temp_profile_dir}")

            cls._apply_common_chrome_options(options, is_headless)
            try:
                driver = webdriver.Chrome(options=options)
            except Exception:
                shutil.rmtree(temp_profile_dir, ignore_errors=True)
                raise

        elif browser_type == "remote":
            options = ChromeOptions()
            cls._apply_common_chrome_options(options, is_headless)
            driver = webdriver.Remote(
                command_executor=settings.REMOTE_URL,
                options=options
            )

        else:
            raise ValueError(
                f"Unsupported browser: '{browser_type}'. Supported browsers are: 'brave', 'chrome', 'remote'."
            )

        driver.set_page_load_timeout(settings.PAGE_LOAD_TIMEOUT)
        return driver, temp_profile_dir

    @classmethod
    def _apply_common_chrome_options(cls, options: ChromeOptions, headless: bool) -> None:
        """Applies essential options for deterministic and performant testing."""
        # Use eager page load strategy: DOMContentLoaded is sufficient; eliminates renderer timeouts
        options.page_load_strategy = "eager"

        if headless:
            options.add_argument("--headless=new")

        options.add_argument("--no-sandbox")
        options.add_argument("--disable-dev-shm-usage")
        options.add_argument("--disable-gpu")
        options.add_argument("--window-size=1920,1080")
        options.add_argument("--disable-notifications")
        options.add_argument("--disable-extensions")
        options.add_argument("--disable-popup-blocking")
        options.add_argument("--ignore-certificate-errors")
        options.add_argument("--disable-search-engine-choice-screen")

        # Performance and renderer resilience flags
        options.add_argument("--disable-renderer-backgrounding")
        options.add_argument("--disable-backgrounding-occluded-windows")
        options.add_argument("--disable-ipc-flooding-protection")

        # Disable Brave first-run and welcome pages
        options.add_argument("--no-first-run")
        options.add_argument("--no-default-browser-check")

        # Set user agent flag for automation diagnostic identification
        options.add_argument("--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) HRIS-Selenium-E2E")
