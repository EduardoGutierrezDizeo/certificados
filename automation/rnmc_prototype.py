from playwright.sync_api import sync_playwright
from datetime import datetime

URL = "https://srvcnpc.policia.gov.co/PSC/frm_cnp_consulta.aspx"

# --- Datos de prueba: reemplaza con datos reales ---
CEDULA = "1004819300"
FECHA_EXP = "26/08/2021"  # formato DD/MM/AAAA
# ----------------------------------------------------

def consultar_rnmc(cedula: str, fecha_exp: str) -> dict:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        page.on("dialog", lambda d: d.accept())

        page.goto(URL)
        page.wait_for_load_state("networkidle")

        page.select_option("#ctl00_ContentPlaceHolder3_ddlTipoDoc", "55")
        page.wait_for_load_state("networkidle")
        page.wait_for_timeout(1000)

        page.fill("#ctl00_ContentPlaceHolder3_txtExpediente", cedula)
        page.fill("#txtFechaexp", fecha_exp)

        page.keyboard.press("Escape")
        page.locator("#ctl00_ContentPlaceHolder3_txtExpediente").focus()
        page.wait_for_timeout(300)

        page.click("#ctl00_ContentPlaceHolder3_btnConsultar2")

        # Espera a que la red se estabilice después del postback AJAX
        page.wait_for_load_state("networkidle", timeout=20000)
        page.wait_for_timeout(1000)

        contenido = page.content()

        # Revisa primero si hay un modal de error con texto visible
        modal_texto = page.locator("#ctl00_ContentPlaceHolder3_lblcontenidomodal").inner_text().strip()
        if modal_texto:
            browser.close()
            return {"status": "failed", "error_message": modal_texto}

        # Revisa si el resultado (éxito) está presente en la página
        if "MEDIDAS CORRECTIVAS" not in contenido.upper():
            browser.close()
            return {"status": "failed", "error_message": "No se detectó respuesta reconocible del sitio"}

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = f"rnmc_{cedula}_{timestamp}.pdf"
        page.pdf(path=pdf_path, format="A4", print_background=True)

        browser.close()
        return {"status": "success", "pdf_path": pdf_path}


if __name__ == "__main__":
    resultado = consultar_rnmc(CEDULA, FECHA_EXP)
    print(resultado)