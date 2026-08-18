from playwright.sync_api import Browser, BrowserContext
from playwright_stealth import Stealth


_stealth = Stealth()


def crear_context_stealthed(browser: Browser, **kwargs) -> BrowserContext:
    context = browser.new_context(**kwargs)
    _stealth.apply_stealth_sync(context)
    return context
