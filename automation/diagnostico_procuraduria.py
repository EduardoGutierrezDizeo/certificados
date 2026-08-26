import sys
import time
import traceback
from datetime import datetime
from pathlib import Path

from playwright.sync_api import sync_playwright

from config import TEMP_CERTS_DIR
from sites import crear_context_stealthed
from sites.procuraduria import (
    URL,
    TIPO_DOC_MAP,
    _obtener_frame_formulario,
    _esperar_spinner,
    _buscar_boton_descarga,
)
from sites.pgn_resolver import resolver_pregunta_completa

REALISTIC_UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36"


def _es_evento_relevante(response):
    ct = response.headers.get("content-type", "").lower()
    url = response.url.lower()
    if "pdf" in ct or "octet-stream" in ct:
        return True
    return any(kw in url for kw in ("pdf", "act", "verpdf", "descarga"))


def main():
    doc_type = sys.argv[1] if len(sys.argv) > 1 else "CC"
    doc_number = sys.argv[2] if len(sys.argv) > 2 else "1004819300"
    full_name = sys.argv[3] if len(sys.argv) > 3 else None

    carpeta = TEMP_CERTS_DIR / f"debug_run_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
    carpeta.mkdir(parents=True, exist_ok=True)
    print(f"[INIT] Carpeta de diagnóstico: {carpeta}")

    pw = sync_playwright().start()
    browser = pw.chromium.launch(headless=True)

    context = crear_context_stealthed(
        browser,
        accept_downloads=True,
        viewport={"width": 1920, "height": 1080},
        user_agent=REALISTIC_UA,
        record_har_path=str(carpeta / "red.har"),
    )
    page = context.new_page()

    # ─── Listeners ──────────────────────────────────────────────

    def on_response(response):
        if _es_evento_relevante(response):
            ct = response.headers.get("content-type", "")
            print(f"[RESPONSE] status={response.status} content-type={ct} url={response.url}")

    def on_frame_navigated(frame):
        print(f"[FRAME NAV] {frame.url}")

    def on_download(download):
        print(f"[DOWNLOAD EVENT] {download.url} -> {download.suggested_filename}")

    def on_popup(popup):
        print(f"[POPUP] {popup.url}")

    page.on("response", on_response)
    page.on("framenavigated", on_frame_navigated)
    page.on("download", on_download)
    page.on("popup", on_popup)

    # ─── Flujo idéntico a consultar() ───────────────────────────

    try:
        print(f"\n=== PASO 1: Navegar a {URL} ===")
        page.goto(URL)
        page.wait_for_load_state("networkidle")

        print("\n=== PASO 2: Buscar frame del formulario ===")
        frame = _obtener_frame_formulario(page)
        if frame is None:
            print("[ERROR] No se encontró el frame del formulario")
            return
        print(f"[OK] Frame encontrado: {frame.url}")

        print(f"\n=== PASO 3: Llenar formulario (tipo={doc_type}, doc={doc_number}) ===")
        frame.select_option("#ddlTipoID", TIPO_DOC_MAP[doc_type])
        frame.fill("#txtNumID", doc_number)
        frame.check("#rblTipoCert_0")

        print("\n=== PASO 4: Resolver pregunta de seguridad ===")
        respuesta = None
        for intento in range(1, 9):
            pregunta = frame.locator("#lblPregunta").inner_text().strip()
            print(f"  Pregunta {intento}: {pregunta}")
            respuesta = resolver_pregunta_completa(pregunta, full_name, doc_number)
            if respuesta is not None:
                print(f"  Respuesta: {respuesta}")
                break
            print("  No resuelta, clickeando ImageButton1 para nueva pregunta")
            frame.click("#ImageButton1")
            _esperar_spinner(frame, page)

        if respuesta is None:
            print("[ERROR] No se pudo resolver ninguna pregunta tras 8 intentos")
            return

        print("\n=== PASO 5: Enviar respuesta y exportar ===")
        frame.fill("#txtRespuestaPregunta", respuesta)
        frame.click("#btnExportar")
        _esperar_spinner(frame, page)

        try:
            for candidate in [frame, page]:
                candidate.locator("text=Consultando").wait_for(state="hidden", timeout=30000)
        except Exception:
            pass
        page.wait_for_timeout(1000)

        print("\n=== PASO 6: Buscar botón de descarga (UNA vez) ===")
        boton_descargar = _buscar_boton_descarga(page)
        if boton_descargar is None:
            debug_path = str(carpeta / "no_button.png")
            page.screenshot(path=debug_path, full_page=True)
            print(f"[ERROR] No se encontró botón de descarga. Screenshot: {debug_path}")
            return

        href = boton_descargar.get_attribute("href") or ""
        texto = boton_descargar.inner_text().strip()
        print(f"[OK] Botón encontrado: href={href!r} texto={texto!r}")

        print("\n=== PASO 7: Click en botón de descarga (único, sin reintentos) ===")
        boton_descargar.click(delay=200)

        print("\n=== PASO 8: Observando durante 15 segundos ===")
        for i in range(8):
            page.wait_for_timeout(2000)
            t = (i + 1) * 2
            step_path = carpeta / f"step_{i}.png"
            page.screenshot(path=str(step_path), full_page=True)
            print(f"[T+{t}s] Screenshot: {step_path.name}")
            print(f"[T+{t}s] Frames: {[f.url for f in page.frames]}")
            print(f"[T+{t}s] Pages en context: {[p.url for p in page.context.pages]}")

        print("\n=== PASO 9: Guardando HTML de todos los frames ===")
        for idx, frame in enumerate(page.frames):
            html_path = carpeta / f"frame_{idx}.html"
            try:
                html_content = frame.content()
                html_path.write_text(html_content, encoding="utf-8")
                print(f"[HTML GUARDADO] frame {idx} ({frame.url}) -> {html_path.name}")
            except Exception as e:
                print(f"[HTML ERROR] frame {idx} ({frame.url}): {e}")

    except Exception:
        traceback.print_exc()

    finally:
        context.close()
        browser.close()
        pw.stop()
        print(f"\n=== Diagnóstico completo. Revisa la carpeta: {carpeta} ===")
        print("Archivos clave: red.har (abrir en https://toolbox.googleapps.com/apps/har_analyzer/ o Chrome DevTools > Network > Import HAR), frame_*.html, step_*.png")


if __name__ == "__main__":
    main()
