import os
from dotenv import load_dotenv
from playwright.sync_api import sync_playwright
from twocaptcha import TwoCaptcha
from datetime import datetime
from config import TEMP_CERTS_DIR

load_dotenv()
solver = TwoCaptcha(os.getenv("TWOCAPTCHA_API_KEY"))

URL = "https://cfiscal.contraloria.gov.co/certificados/certificadopersonanatural.aspx"
SITE_KEY = "6LcfnjwUAAAAAIyl8ehhox7ZYqLQSVl_w1dmYIle"

TIPO_DOC_MAP = {"CC": "CC", "CE": "CE", "PA": "PA"}


def consultar(document_type: str, document_number: str, full_name: str | None, issuance_date: str | None) -> dict:
    if document_type not in TIPO_DOC_MAP:
        return {"status": "failed", "error_message": f"Contraloría no soporta tipo de documento '{document_type}'"}

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(accept_downloads=True)

        page.goto(URL)
        page.wait_for_load_state("networkidle")

        page.select_option("#ddlTipoDocumento", TIPO_DOC_MAP[document_type])
        page.fill("#txtNumeroDocumento", document_number)

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

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = str(TEMP_CERTS_DIR / f"ctr_{document_number}_{timestamp}.pdf")

        try:
            with page.expect_download(timeout=20000) as download_info:
                page.click("#btnBuscar")
            download_info.value.save_as(pdf_path)
            browser.close()
            return {"status": "success", "pdf_path": pdf_path}
        except Exception:
            page.wait_for_load_state("networkidle", timeout=10000)
            page.wait_for_timeout(1000)
            mensaje_error = page.locator("#alerts-container-validation").inner_text().strip()
            browser.close()
            if mensaje_error:
                return {"status": "failed", "error_message": mensaje_error}
            return {"status": "failed", "error_message": "No se generó descarga ni mensaje de error reconocible"}