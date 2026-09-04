from playwright.sync_api import Browser, BrowserContext
from playwright_stealth import Stealth


_stealth = Stealth()

# Marca "HeadlessChrome" que Chromium headless inserta en el header Sec-CH-UA
# (Client Hints). El backend de Procuraduría detecta esa marca y NUNCA responde
# al POST AJAX de exportación (X-Requested-With: XMLHttpRequest), haciendo que
# la consulta falle con "No se encontró el botón de descarga" tras agotar todos
# los fallbacks. playwright_stealth NO enmascara este header, por lo que hay que
# reescribirlo explícitamente para que se vea como un Chrome normal (no-headless).
#
# Aunque solo lo confirmamos contra Procuraduría, el mismo leak puede afectar
# sutilmente a los otros 3 sitios, así que el fix se aplica de forma GLOBAL en
# el contexto compartido por los 4 scrapers.
_HEADLESS_MARCA = "HeadlessChrome"

# El Sec-CH-UA en el wire siempre declara la marca HeadlessChrome. Lo
# reemplazamos por el set equivalente de un Chrome no-headless real, derivando
# el número de versión mayor del Chromium realmente instalado (no hardcodeado),
# para mantener coherencia con el build en uso.
def _sec_ch_ua_limpio(major: str) -> str:
    return f'"Not)A;Brand";v="8", "Chromium";v="{major}", "Google Chrome";v="{major}"'


def _sec_ch_ua_full_limpio(major: str, full: str) -> str:
    return (
        f'"Not)A;Brand";v="8.0.0.0", "Chromium";v="{full}", '
        f'"Google Chrome";v="{full}"'
    )


# Headers de Client Hints que Chromium genera y que pueden filtrar la marca.
_CLIENT_HINT_HEADERS = (
    "sec-ch-ua",
    "sec-ch-ua-full-version-list",
    "sec-ch-ua-platform",
    "sec-ch-ua-mobile",
)


def crear_context_stealthed(browser: Browser, **kwargs) -> BrowserContext:
    context = browser.new_context(**kwargs)
    _stealth.apply_stealth_sync(context)

    _aplicar_fix_headless(context, browser)

    return context


def _aplicar_fix_headless(context: BrowserContext, browser: Browser) -> None:
    """Intercepta las requests del contexto y reescribe los headers de Client
    Hints para eliminar cualquier marca 'HeadlessChrome' (SIN tocar el resto).

    El header Sec-CH-UA lo genera Chromium a nivel de red y no se puede
    sobrescribir con context.set_extra_http_headers() (no aplica a client
    hints). La forma fiable y barata de reescribirlo es con
    route.continue_(headers=...): validado en el WIRE (servidor echo local) que
    el sec-ch-ua que eventualmente llega al backend ya no contiene
    'HeadlessChrome'. Es el camino rápido, sin el round-trip extra de
    route.fetch()/fulfill(), que ademas ralentizaba la carga inicial del portal
    (decenas de subrecursos) hasta superar el timeout de 30s de page.goto.
    """

    # Major + full version del Chromium instalado, p. ej. "138" y "138.0.7204.23".
    try:
        full_version = browser.version  # "138.0.7204.23"
        major = full_version.split(".")[0]
    except Exception:
        full_version = "138.0.0.0"
        major = "138"

    sec_ch_ua_limpio = _sec_ch_ua_limpio(major)
    sec_ch_ua_full_limpio = _sec_ch_ua_full_limpio(major, full_version)

    def _handler(route):
        headers = dict(route.request.headers)
        necesita_fix = any(
            _HEADLESS_MARCA in headers.get(h, "")
            for h in _CLIENT_HINT_HEADERS
        )
        if not necesita_fix:
            route.continue_()
            return

        if "sec-ch-ua" in headers:
            headers["sec-ch-ua"] = sec_ch_ua_limpio
        if "sec-ch-ua-full-version-list" in headers:
            headers["sec-ch-ua-full-version-list"] = sec_ch_ua_full_limpio
        # Elimina cualquier residual de la marca en el resto de client hints.
        _limpiar_residual(headers)

        # Camino rápido: reenvía la request con los headers reescritos. No
        # cambia método ni body, por lo que POSTs/backends AJAX siguen igual.
        try:
            route.continue_(headers=headers)
        except Exception:
            # Si algo falla, nunca bloqueamos el scraper: dejamos pasar la
            # request original sin reescribir.
            try:
                route.continue_()
            except Exception:
                pass

    def _limpiar_residual(headers: dict) -> None:
        for h in _CLIENT_HINT_HEADERS:
            v = headers.get(h)
            if v and _HEADLESS_MARCA in v:
                headers[h] = v.replace(_HEADLESS_MARCA, "Google Chrome")

    context.route("**/*", _handler)
