import os
import time
from dotenv import load_dotenv
from playwright.sync_api import sync_playwright
from twocaptcha import TwoCaptcha
from datetime import datetime

load_dotenv()
API_KEY = os.getenv("3745109ee89c0c9ef59e4b4bb68a0189")
solver = TwoCaptcha(API_KEY)

URL = "https://antecedentes.policia.gov.co:7005/WebJudicial/antecedentes.xhtml"
SITE_KEY = "6LcsIwQaAAAAAFCsaI-dkR6hgKsZwwJRsmE0tIJH"
CEDULA = "1004819300"


def consultar_pj(cedula: str) -> dict:
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

        page.select_option("#cedulaTipo", "cc")
        page.fill("#cedulaInput", cedula)

        t0 = time.time()
        resultado_captcha = solver.recaptcha(sitekey=SITE_KEY, url=URL)
        t1 = time.time()
        print(f"[DEBUG] 2Captcha tardó {t1 - t0:.1f} segundos en resolver")

        token = resultado_captcha["code"]

        page.evaluate(
            "(token) => { document.getElementById('g-recaptcha-response').value = token; }",
            token,
        )
        page.wait_for_timeout(300)

        t2 = time.time()
        page.click("#j_idt17")
        page.wait_for_load_state("networkidle", timeout=20000)
        page.wait_for_timeout(1500)
        t3 = time.time()

        print(f"[DEBUG] Tiempo entre token recibido y clic: {t2 - t1:.1f}s")
        print(f"[DEBUG] Tiempo total desde generar token hasta respuesta del servidor: {t3 - t1:.1f}s")

        contenido = page.content()
        mensajes_error = page.locator("#j_idt10").inner_text().strip() if page.locator("#j_idt10").count() else ""

        if mensajes_error:
            browser.close()
            return {"status": "failed", "error_message": mensajes_error[:200], "tiempo_total_captcha": t3 - t1}

        if "ASUNTOS PENDIENTES" not in contenido.upper():
            browser.close()
            return {"status": "failed", "error_message": "No se detectó respuesta reconocible del sitio"}

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = f"pj_{cedula}_{timestamp}.pdf"
        page.pdf(path=pdf_path, format="A4", print_background=True)

        browser.close()
        return {"status": "success", "pdf_path": pdf_path}


if __name__ == "__main__":
    print(f"[DEBUG] Saldo actual en 2Captcha: ${solver.balance()}")
    resultado = consultar_pj(CEDULA)
    print(resultado)