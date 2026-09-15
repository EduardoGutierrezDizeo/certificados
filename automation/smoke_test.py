"""Smoke test: verifica que Playwright/Chromium arrancan correctamente en el
entorno (local o dentro del contenedor Docker) sin tocar ningún portal real.

Lanza un navegador headless, navega a about:blank e imprime la versión de
Chromium. Sale con código 0 si todo funcionó.
"""
import sys

from playwright.sync_api import sync_playwright


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.goto("about:blank")
        version = browser.version
        print(f"[SMOKE] Chromium {version} arrancó y navegó a about:blank OK")
        browser.close()
    print("[SMOKE] Smoke test de Chromium/PW completado correctamente.")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as e:  # noqa: BLE001
        print(f"[SMOKE] FAILED: {e}")
        sys.exit(1)
