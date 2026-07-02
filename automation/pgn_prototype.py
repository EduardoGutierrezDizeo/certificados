from playwright.sync_api import sync_playwright
from datetime import datetime
from pgn_resolver import resolver_pregunta_completa

URL = "https://www.procuraduria.gov.co/Pages/Generacion-de-antecedentes.aspx"

TIPO_DOC_MAP = {
    "CC": "1",
    "CE": "5",
    "NIT": "2",
    # PA (Pasaporte) no está soportado por este sitio
}

CEDULA = "1004819300"
TIPO_DOCUMENTO = "CC"
FULL_NAME = None  # opcional: "EDUARDO GUTIERREZ" por ejemplo, o None si no se tiene

MAX_INTENTOS_PREGUNTA = 8


def esperar_spinner(frame, page):
    for candidate in [frame, page]:
        try:
            candidate.locator("#UpdateProgress").wait_for(state="visible", timeout=1500)
        except Exception:
            pass
        try:
            candidate.locator("#UpdateProgress").wait_for(state="hidden", timeout=10000)
        except Exception:
            pass
    page.wait_for_timeout(400)

def obtener_frame_formulario(page, timeout_segundos=20):
    """Busca el frame que contiene el formulario real de Procuraduría, con reintentos."""
    import time
    inicio = time.time()
    while time.time() - inicio < timeout_segundos:
        for frame in page.frames:
            if "webcert" in frame.url:
                try:
                    frame.wait_for_selector("#ddlTipoID", timeout=2000)
                    return frame
                except Exception:
                    continue
        page.wait_for_timeout(500)
    return None


def consultar_pgn(cedula: str, tipo_documento: str, full_name: str | None) -> dict:
    if tipo_documento not in TIPO_DOC_MAP:
        return {"status": "failed", "error_message": f"Tipo de documento '{tipo_documento}' no soportado por Procuraduría"}

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False, slow_mo=200)
        page = browser.new_page(accept_downloads=True)
        page.goto(URL)
        page.wait_for_load_state("networkidle")

        frame = obtener_frame_formulario(page)
        if frame is None:
            print(f"[DEBUG] Frames disponibles: {[f.url for f in page.frames]}")
            browser.close()
            return {"status": "failed", "error_message": "No se pudo encontrar el frame del formulario"}

        frame.select_option("#ddlTipoID", TIPO_DOC_MAP[tipo_documento])
        frame.fill("#txtNumID", cedula)
        frame.check("#rblTipoCert_0")

        # Busca una pregunta que podamos resolver, refrescando si hace falta
        respuesta = None
        pregunta_actual = None
        for intento in range(MAX_INTENTOS_PREGUNTA):
            pregunta_actual = frame.locator("#lblPregunta").inner_text().strip()
            respuesta = resolver_pregunta_completa(pregunta_actual, full_name, cedula)
            if respuesta is not None:
                print(f"[DEBUG] Pregunta resuelta: '{pregunta_actual}' -> '{respuesta}'")
                break
            print(f"[DEBUG] No se pudo resolver: '{pregunta_actual}', refrescando...")
            frame.click("#ImageButton1")
            esperar_spinner(frame, page)

        if respuesta is None:
            browser.close()
            return {"status": "failed", "error_message": f"No se pudo resolver ninguna pregunta tras {MAX_INTENTOS_PREGUNTA} intentos"}

        frame.fill("#txtRespuestaPregunta", respuesta)

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = f"pgn_{cedula}_{timestamp}.pdf"

        frame.click("#btnExportar")

        # Espera a que cargue la página intermedia de confirmación
        esperar_spinner(frame, page)
        page.wait_for_timeout(1000)

        # Busca el botón "Descargar" de la página intermedia
        try:
            # Esperar a que el botón btnDescargar esté visible (en frame o página)
            page.wait_for_load_state("networkidle", timeout=20000)
            page.wait_for_timeout(1000)
            boton_descargar = page.locator("#btnDescargar").first
            if not boton_descargar.is_visible(timeout=3000):
                boton_descargar = frame.locator("#btnDescargar").first
                boton_descargar.wait_for(state="visible", timeout=10000)
            # Asegurar que el spinner no esté bloqueando
            page.locator("#UpdateProgress").wait_for(state="hidden", timeout=15000)
            with page.expect_download(timeout=20000) as download_info:
                boton_descargar.click(delay=500)
            download = download_info.value
            download.save_as(pdf_path)
            browser.close()
            return {"status": "success", "pdf_path": pdf_path}
        except Exception as e:
            page.screenshot(path="pgn_step_result.png", full_page=True)
            with open("pgn_step_result.html", "w", encoding="utf-8") as f:
                f.write(frame.content())

            error_visible = frame.locator("#ValidationSummary1").inner_text().strip() if frame.locator("#ValidationSummary1").count() else ""
            browser.close()

            if error_visible:
                return {"status": "failed", "error_message": error_visible}
            return {"status": "unknown", "note": f"No se pudo completar la descarga: {e}. Revisa pgn_step_result.png/html"}


if __name__ == "__main__":
    resultado = consultar_pgn(CEDULA, TIPO_DOCUMENTO, FULL_NAME)
    print(resultado)