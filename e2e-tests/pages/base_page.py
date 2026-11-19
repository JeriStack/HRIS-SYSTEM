"""
HRIS-SYSTEM — Base Page Object
Encapsulates common WebDriver operations, robust explicit waits, and page assertions.
"""

import time
from pathlib import Path
from typing import List, Optional, Tuple
from selenium import webdriver
from selenium.common.exceptions import (
    NoSuchElementException,
    StaleElementReferenceException,
    TimeoutException,
    WebDriverException,
)
from selenium.webdriver.common.by import By
from selenium.webdriver.remote.webelement import WebElement
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

from config.settings import settings


class BasePage:
    """Base class for all Page Objects in the HRIS testing framework."""

    def __init__(self, driver: webdriver.Remote, base_url: Optional[str] = None):
        self.driver = driver
        self.base_url = (base_url or settings.BASE_URL).rstrip("/")
        self.timeout = settings.EXPLICIT_WAIT

    def open(self, path: str = "") -> "BasePage":
        """Navigates to the specified path relative to base_url."""
        url = f"{self.base_url}/{path.lstrip('/')}"
        self.driver.get(url)
        return self

    def find(self, locator: Tuple[By, str], timeout: int = None) -> WebElement:
        """Finds an element using explicit wait for presence."""
        wait_time = timeout if timeout is not None else self.timeout
        return WebDriverWait(self.driver, wait_time).until(
            EC.presence_of_element_located(locator),
            message=f"Element with locator {locator} not found within {wait_time}s"
        )

    def find_visible(self, locator: Tuple[By, str], timeout: int = None) -> WebElement:
        """Finds an element using explicit wait for visibility."""
        wait_time = timeout if timeout is not None else self.timeout
        return WebDriverWait(self.driver, wait_time).until(
            EC.visibility_of_element_located(locator),
            message=f"Element with locator {locator} not visible within {wait_time}s"
        )

    def find_all(self, locator: Tuple[By, str], timeout: int = None) -> List[WebElement]:
        """Finds all matching elements with retry on transient DOM transitions."""
        wait_time = timeout if timeout is not None else self.timeout
        end_time = time.time() + wait_time
        while time.time() < end_time:
            try:
                WebDriverWait(self.driver, 1).until(
                    EC.presence_of_element_located(locator)
                )
                elements = self.driver.find_elements(*locator)
                if elements:
                    return elements
            except (StaleElementReferenceException, NoSuchElementException):
                time.sleep(0.1)
                continue
            except TimeoutException:
                pass
            except Exception:
                time.sleep(0.1)
                continue
        return []

    def click(self, locator: Tuple[By, str], timeout: int = None) -> "BasePage":
        """Waits until an element is clickable and clicks it."""
        wait_time = timeout if timeout is not None else self.timeout
        element = WebDriverWait(self.driver, wait_time).until(
            EC.element_to_be_clickable(locator),
            message=f"Element with locator {locator} not clickable within {wait_time}s"
        )
        try:
            element.click()
        except TimeoutException as e:
            if "Timed out receiving message from renderer" in str(e):
                # Navigation was dispatched by the click; renderer took longer than command timeout
                pass
            else:
                raise
        except Exception:
            # Fallback to JavaScript dispatch if standard click is intercepted
            self.driver.execute_script("arguments[0].click();", element)
        return self

    def type(self, locator: Tuple[By, str], text: str, clear: bool = True, timeout: int = None) -> "BasePage":
        """Types text into an input field after ensuring visibility."""
        element = self.find_visible(locator, timeout)
        if clear:
            element.clear()
        element.send_keys(text)
        return self

    def get_text(self, locator: Tuple[By, str], timeout: int = None) -> str:
        """Returns the inner text of an element."""
        element = self.find_visible(locator, timeout)
        return element.text.strip()

    def is_visible(self, locator: Tuple[By, str], timeout: int = 3) -> bool:
        """Checks if an element is currently visible within a short timeout."""
        try:
            self.find_visible(locator, timeout)
            return True
        except Exception:
            return False

    def wait_for_url_contains(self, fragment: str, timeout: int = None) -> bool:
        """Waits for current URL to contain a substring."""
        wait_time = timeout if timeout is not None else self.timeout
        return WebDriverWait(self.driver, wait_time).until(
            EC.url_contains(fragment),
            message=f"URL did not contain '{fragment}' within {wait_time}s (Current: {self.driver.current_url})"
        )

    @property
    def current_url(self) -> str:
        """Returns current browser URL."""
        return self.driver.current_url

    @property
    def title(self) -> str:
        """Returns current page title."""
        return self.driver.title

    def save_screenshot(self, filename: str) -> Path:
        """Saves a screenshot to the configured screenshots directory."""
        path = settings.SCREENSHOTS_DIR / filename
        self.driver.save_screenshot(str(path))
        return path

