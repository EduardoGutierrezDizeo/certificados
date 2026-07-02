import os
import time
from dotenv import load_dotenv
from playwright.sync_api import sync_playwright
from twocaptcha import TwoCaptcha
from datetime import datetime

load_dotenv()
API_KEY = os.getenv("TWOCAPTCHA_API_KEY")
solver = TwoCaptcha(API_KEY)

URL = "https://cfiscal.contraloria.gov.co/certificados/certificadopersonanatural.aspx"
SITE_KEY = "6LcfnjwUAAAAAIyl8ehhox7ZYqLQSVl_w1dmYIle"
CEDULA = "1004819300"


def consultar_contraloria(cedula: str) -> dict:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(accept_downloads=True)

        page.goto(URL)
        page.wait_for_load_state("networkidle")

        page.select_option("#ddlTipoDocumento", "CC")
        page.fill("#txtNumeroDocumento", cedula)

        try:
            resultado_captcha = solver.recaptcha(sitekey=SITE_KEY, url=URL)
        except Exception as e:
            browser.close()
            return {"status": "failed", "error_message": f"Error resolviendo CAPTCHA: {e}"}

        token = resultado_captcha["code"]
        page.evaluate(
            "(token) => { document.getElementById('g-recaptcha-response').value = token; }",
            token,
        )
        page.wait_for_timeout(300)

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = f"ctr_{cedula}_{timestamp}.pdf"

        try:
            with page.expect_download(timeout=20000) as download_info:
                page.click("#btnBuscar")
            download = download_info.value
            download.save_as(pdf_path)
            browser.close()
            return {"status": "success", "pdf_path": pdf_path}
        except Exception:
            # No hubo descarga: revisamos si el sitio mostró un mensaje de error en pantalla
            page.wait_for_load_state("networkidle", timeout=10000)
            page.wait_for_timeout(1000)
            mensaje_error = page.locator("#alerts-container-validation").inner_text().strip()
            browser.close()
            if mensaje_error:
                return {"status": "failed", "error_message": mensaje_error}
            return {"status": "failed", "error_message": "No se generó descarga ni mensaje de error reconocible"}


if __name__ == "__main__":
    print(f"[DEBUG] Saldo actual en 2Captcha: ${solver.balance()}")
    resultado = consultar_contraloria(CEDULA)
    print(resultado)