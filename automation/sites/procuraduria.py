import re
import time
from datetime import datetime

from playwright.sync_api import sync_playwright

from config import TEMP_CERTS_DIR
from sites import crear_context_stealthed
from sites.pgn_resolver import resolver_pregunta_completa

URL = "https://www.procuraduria.gov.co/Pages/Generacion-de-antecedentes.aspx"
TIPO_DOC_MAP = {"CC": "1", "CE": "5", "NIT": "2"}
MAX_INTENTOS_PREGUNTA = 8

REALISTIC_UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36"


def _esperar_spinner(frame, page):
    for candidate in [frame, page]:
        try:
            candidate.locator("#UpdateProgress").wait_for(state="visible", timeout=3000)
        except Exception:
            pass
        try:
            candidate.locator("#UpdateProgress").wait_for(state="hidden", timeout=30000)
        except Exception:
            pass
    page.wait_for_timeout(500)


def _obtener_frame_formulario(page, timeout_segundos=20):
    inicio = time.time()
    while time.time() - inicio < timeout_segundos:
        for frame in page.frames:
            if "webcert" in frame.url:
                try:
                    frame.wait_for_selector("#ddlTipoID", timeout=3000)
                    return frame
                except Exception:
                    continue
        page.wait_for_timeout(300)
    return None


def _esperar_resultado_post_export(frame, page, timeout_total=90):
    """Poll activo tras enviar el formulario de exportación (#btnExportar).

    En vez de depender de un único timeout fijo sobre el overlay de carga
    ("Consultando por favor espere..."), revisa activamente cada ~700ms si
    ya ocurrió alguna de las dos resoluciones posibles del portal, hasta
    agotar `timeout_total` segundos. Esto tolera que el portal de la
    Procuraduría responda más lento de lo normal sin abandonar antes de
    tiempo, y sin arriesgarse a esperar indefinidamente si el portal
    realmente está caído.

    Devuelve (frame_verpdf, error_texto):
      - (frame, None)  -> el frame de resultados ya está listo con su botón
      - (None, texto)  -> el portal devolvió un error de validación explícito
      - (None, None)   -> se agotó el tiempo sin resolución clara (portal lento/caído)
    """
    inicio = time.time()
    while time.time() - inicio < timeout_total:
        # 1. ¿Ya está el frame de resultados con su botón visible?
        for f in page.frames:
            if "verpdf" in f.url:
                try:
                    f.wait_for_selector(
                        "#btnDescargar, a:has-text('aqui'), a:has-text('aquí')",
                        timeout=1000,
                    )
                    return f, None
                except Exception:
                    pass  # el frame existe pero su contenido aún no ha cargado

        # 2. ¿Hay un mensaje de error de validación en el frame del formulario?
        try:
            error_texto = frame.locator("#ValidationSummary1").inner_text(timeout=500).strip()
            if error_texto:
                return None, error_texto
        except Exception:
            pass

        # 3. Ninguna resolución todavía (el overlay "Consultando..." muy
        #    probablemente sigue visible) -> seguimos esperando.
        page.wait_for_timeout(700)

    return None, None


TEXTO_BOTON = re.compile(
    r"descarg|pdf|export|download|guardar.*archivo|obtener.*certificado",
    re.IGNORECASE,
)


def _es_boton_descarga(elemento) -> bool:
    """Verifica si un elemento parece ser un botón de descarga de PDF."""
    tag = elemento.evaluate("el => el.tagName.toLowerCase()")
    if tag not in ("a", "button", "input"):
        return False

    texto = (elemento.inner_text() or "").strip()
    valor = elemento.get_attribute("value") or ""
    href = elemento.get_attribute("href") or ""
    tiene_download = elemento.get_attribute("download") is not None
    es_pdf_link = bool(re.search(r"\.pdf($|\?)", href, re.IGNORECASE))

    if tiene_download or es_pdf_link:
        return True
    if TEXTO_BOTON.search(texto) or TEXTO_BOTON.search(valor):
        return True

    return False


def _buscar_boton_descarga(page):
    """Busca el botón de descarga de PDF en la página y TODOS sus frames."""
    print(f"[DEBUG] _buscar_boton_descarga: {len(page.frames)} frames: {[f.url for f in page.frames]}")
    todos_los_targets = [page] + [f for f in page.frames if f != page.main_frame]

    # 1. Buscar en el frame verpdf.aspx (post-generación)
    for frame in page.frames:
        if "verpdf" in frame.url:
            # Prioridad: el input#btnDescargar es el botón real de descarga.
            # El <a> con texto "aqui" en esta misma página lleva a un
            # servicio distinto (Actualización de nombres) y NO debe usarse.
            try:
                btn = frame.locator("#btnDescargar")
                btn.wait_for(state="visible", timeout=3000)
                print(f"[DEBUG] btnDescargar encontrado en verpdf frame")
                return btn
            except Exception as e:
                print(f"[DEBUG] #btnDescargar no encontrado en verpdf frame: {type(e).__name__}: {e}")

            # Fallback: otros enlaces de descarga, EXCLUYENDO el link "aqui"
            # (que sabemos que es de otro servicio).
            try:
                links = frame.locator("a").all()
                for link in links:
                    if link.is_visible():
                        href = link.get_attribute("href") or ""
                        texto = link.inner_text().strip().lower()
                        if "actnombre" in href.lower():
                            continue
                        if "pdf" in href or "download" in href:
                            print(f"[DEBUG] Link fallback encontrado en verpdf: href={href!r}")
                            return link
            except Exception as e:
                print(f"[DEBUG] Búsqueda de links en verpdf falló: {type(e).__name__}: {e}")

    # 2. Selectores específicos conocidos
    selectores = [
        "#btnDescargar", "#btnPDF",
        "a:has-text('Descargar')", "input[value*='Descargar']",
        "a:has-text('PDF')", "input[value*='PDF']",
        "[download]", "a[href*='.pdf']",
    ]
    for selector in selectores:
        for target in todos_los_targets:
            try:
                candidate = target.locator(selector).first
                candidate.wait_for(state="visible", timeout=3000)
                print(f"[DEBUG] Selector '{selector}' encontró botón")
                return candidate
            except Exception:
                continue

    # 3. Fallback: cualquier botón/enlace visible
    for target in todos_los_targets:
        try:
            for elemento in target.locator("a, button, input[type='submit'], input[type='button']").all():
                if elemento.is_visible() and _es_boton_descarga(elemento):
                    return elemento
        except Exception:
            continue

    print(f"[DEBUG] _buscar_boton_descarga: no se encontró ningún botón de descarga")
    return None


def _intentar_capturar_pdf(page, pdf_path, boton_descargar):
    """Hace click en boton_descargar y escucha download/response-pdf.
    Devuelve True si logró guardar el PDF, False si no pasó nada
    reconocible (asumimos que hubo una navegación intermedia)."""
    pdf_response = {}

    def _on_response(response):
        ct = response.headers.get("content-type", "")
        if "pdf" in ct.lower():
            print(f"[DEBUG] Response PDF detectada: {response.url} content-type={ct}")
            pdf_response["response"] = response

    page.on("response", _on_response)
    try:
        try:
            with page.expect_download(timeout=6000) as download_info:
                boton_descargar.click(delay=200)
            download_info.value.save_as(pdf_path)
            print("[DEBUG] Descarga capturada vía evento 'download'")
            return True
        except Exception:
            page.wait_for_timeout(1500)
            if "response" in pdf_response:
                resp = pdf_response["response"]
                with open(pdf_path, "wb") as f:
                    f.write(resp.body())
                print("[DEBUG] Descarga capturada vía response con content-type pdf")
                return True
            return False
    finally:
        page.remove_listener("response", _on_response)


def consultar(document_type: str, document_number: str, full_name: str | None, issuance_date: str | None, browser=None) -> dict:
    if document_type not in TIPO_DOC_MAP:
        return {"status": "failed", "error_message": f"Procuraduría no soporta tipo de documento '{document_type}'"}

    owns_browser = browser is None
    if owns_browser:
        pw = sync_playwright().start()
        browser = pw.chromium.launch(headless=True)

    try:
        context = crear_context_stealthed(
            browser,
            accept_downloads=True,
            viewport={"width": 1920, "height": 1080},
            user_agent=REALISTIC_UA,
        )
        page = context.new_page()

        page.goto(URL, timeout=60000, wait_until="domcontentloaded")
        page.wait_for_load_state("networkidle", timeout=60000)

        frame = _obtener_frame_formulario(page)
        if frame is None:
            context.close()
            return {"status": "failed", "error_message": "No se pudo encontrar el frame del formulario"}

        frame.select_option("#ddlTipoID", TIPO_DOC_MAP[document_type])
        frame.fill("#txtNumID", document_number)
        frame.check("#rblTipoCert_0")

        respuesta = None
        for _ in range(MAX_INTENTOS_PREGUNTA):
            pregunta_actual = frame.locator("#lblPregunta").inner_text().strip()
            respuesta = resolver_pregunta_completa(pregunta_actual, full_name, document_number)
            if respuesta is not None:
                break
            frame.click("#ImageButton1")
            _esperar_spinner(frame, page)

        if respuesta is None:
            context.close()
            return {"status": "failed", "error_message": f"No se pudo resolver ninguna pregunta tras {MAX_INTENTOS_PREGUNTA} intentos"}

        frame.fill("#txtRespuestaPregunta", respuesta)
        frame.click("#btnExportar")

        frame_verpdf, error_validacion = _esperar_resultado_post_export(frame, page, timeout_total=90)

        if error_validacion:
            context.close()
            return {"status": "failed", "error_message": error_validacion}

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = str(TEMP_CERTS_DIR / f"pgn_{document_number}_{timestamp}.pdf")

        try:
            if frame_verpdf is None:
                debug_path = str(TEMP_CERTS_DIR / f"pgn_debug_{document_number}_{timestamp}.png")
                page.screenshot(path=debug_path, full_page=True)
                context.close()
                return {
                    "status": "failed",
                    "error_message": (
                        "El portal de la Procuraduría no respondió dentro del tiempo esperado "
                        "(posible saturación del servidor). Se guardó screenshot de debug."
                    ),
                }

            print(f"[DEBUG] Frame verpdf listo: {frame_verpdf.url}")

            boton_descargar = _buscar_boton_descarga(page)
            if boton_descargar is None:
                debug_path = str(TEMP_CERTS_DIR / f"pgn_debug_{document_number}_{timestamp}.png")
                page.screenshot(path=debug_path, full_page=True)
                context.close()
                return {"status": "failed", "error_message": "No se encontró el botón de descarga. Se guardó screenshot de debug."}

            href = boton_descargar.get_attribute("href") or ""
            print(f"[DEBUG] Botón de descarga: href={href!r}")

            exito = _intentar_capturar_pdf(page, pdf_path, boton_descargar)
            context.close()
            if exito:
                return {"status": "success", "pdf_path": pdf_path}
            return {"status": "failed", "error_message": "No se logró capturar el PDF"}

        except Exception as e:
            error_visible = ""
            if frame:
                try:
                    error_visible = frame.locator("#ValidationSummary1").inner_text().strip()
                except Exception:
                    pass
            context.close()
            if error_visible:
                return {"status": "failed", "error_message": error_visible}
            return {"status": "failed", "error_message": f"No se pudo completar la descarga: {e}"}
    except Exception:
        if owns_browser:
            browser.close()
        raise