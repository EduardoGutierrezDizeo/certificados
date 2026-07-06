import os
from dotenv import load_dotenv
from playwright.sync_api import sync_playwright
from twocaptcha import TwoCaptcha
from datetime import datetime
from config import TEMP_CERTS_DIR

load_dotenv()
solver = TwoCaptcha(os.getenv("TWOCAPTCHA_API_KEY"))

URL = "https://antecedentes.policia.gov.co:7005/WebJudicial/antecedentes.xhtml"
SITE_KEY = "6LcsIwQaAAAAAFCsaI-dkR6hgKsZwwJRsmE0tIJH"

TIPO_DOC_MAP = {"CC": "cc", "CE": "cx", "PA": "pa"}


def consultar(document_type: str, document_number: str, full_name: str | None, issuance_date: str | None) -> dict:
    if document_type not in TIPO_DOC_MAP:
        return {"status": "failed", "error_message": f"Policía Judicial no soporta tipo de documento '{document_type}'"}

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        page.goto(URL)
        page.wait_for_load_state("networkidle")

        page.click("#aceptaOption\\:0")
        page.wait_for_selector("#continuarBtn:not([disabled])", timeout=10000)
        page.wait_for_timeout(500)
        page.click("#continuarBtn")
        page.wait_for_load_state("networkidle")
        page.wait_for_timeout(1000)

        page.select_option("#cedulaTipo", TIPO_DOC_MAP[document_type])
        page.fill("#cedulaInput", document_number)

        try:
            resultado_captcha = solver.recaptcha(sitekey=SITE_KEY, url=URL)
        except Exception as e:
            browser.close()
            return {"status": "failed", "error_message": f"Error resolviendo CAPTCHA: {e}"}

        page.evaluate(
            "(token) => { document.getElementById('g-recaptcha-response').value = token; }",
            resultado_captcha["code"],
        )
        page.wait_for_timeout(300)

        page.locator("button:has-text('Consultar')").first.wait_for(state="visible", timeout=10000)
        page.locator("button:has-text('Consultar')").first.click()
        page.wait_for_load_state("networkidle", timeout=20000)
        page.wait_for_timeout(1500)

        contenido = page.content()
        mensajes_error = page.locator("#j_idt10").inner_text().strip() if page.locator("#j_idt10").count() else ""
        if mensajes_error:
            browser.close()
            return {"status": "failed", "error_message": mensajes_error}

        if "ASUNTOS PENDIENTES" not in contenido.upper():
            browser.close()
            return {"status": "failed", "error_message": "No se detectó respuesta reconocible del sitio"}

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = str(TEMP_CERTS_DIR / f"pj_{document_number}_{timestamp}.pdf")
        page.pdf(path=pdf_path, format="A4", print_background=True)

        browser.close()
        return {"status": "success", "pdf_path": pdf_path}