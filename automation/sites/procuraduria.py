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

        page.goto(URL)
        page.wait_for_load_state("networkidle")

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
        _esperar_spinner(frame, page)

        try:
            for candidate in [frame, page]:
                candidate.locator("text=Consultando").wait_for(state="hidden", timeout=30000)
        except Exception:
            pass
        page.wait_for_timeout(1000)

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = str(TEMP_CERTS_DIR / f"pgn_{document_number}_{timestamp}.pdf")

        try:
            page.wait_for_load_state("networkidle", timeout=30000)

            boton_descargar = None

            for selector in ["#btnDescargar", "#btnPDF", "a:has-text('Descargar')", "input[type='submit'][value*='Descargar']", "a:has-text('PDF')"]:
                for target in [page, frame]:
                    try:
                        candidate = target.locator(selector).first
                        if candidate.is_visible(timeout=3000):
                            boton_descargar = candidate
                            break
                    except Exception:
                        continue
                if boton_descargar:
                    break

            if boton_descargar is None:
                debug_path = str(TEMP_CERTS_DIR / f"pgn_debug_{document_number}_{timestamp}.png")
                page.screenshot(path=debug_path, full_page=True)
                context.close()
                return {"status": "failed", "error_message": "No se encontró el botón de descarga. Se guardó screenshot de debug."}

            page.locator("#UpdateProgress").wait_for(state="hidden", timeout=15000)
            with page.expect_download(timeout=20000) as download_info:
                boton_descargar.click(delay=200)
            download_info.value.save_as(pdf_path)
            context.close()
            return {"status": "success", "pdf_path": pdf_path}
        except Exception as e:
            error_visible = frame.locator("#ValidationSummary1").inner_text().strip() if frame.locator("#ValidationSummary1").count() else ""
            context.close()
            if error_visible:
                return {"status": "failed", "error_message": error_visible}
            return {"status": "failed", "error_message": f"No se pudo completar la descarga: {e}"}
    except Exception:
        if owns_browser:
            browser.close()
        raise
