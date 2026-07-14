import os
from datetime import datetime

from config import TEMP_CERTS_DIR

URL = "https://srvcnpc.policia.gov.co/PSC/frm_cnp_consulta.aspx"

TIPO_DOC_MAP = {"CC": "55", "CE": "57", "PA": "58", "NIT": "1"}


def consultar(document_type: str, document_number: str, full_name: str | None, issuance_date: str | None, browser=None) -> dict:
    if document_type not in TIPO_DOC_MAP:
        return {"status": "failed", "error_message": f"RNMC no soporta tipo de documento '{document_type}'"}

    if document_type == "CC" and not issuance_date:
        return {"status": "failed", "error_message": "RNMC requiere fecha de expedición para Cédula de Ciudadanía"}

    context = browser.new_context()
    page = context.new_page()

    try:
        page.on("dialog", lambda d: d.accept())

        page.goto(URL)
        page.wait_for_load_state("networkidle")
        page.wait_for_timeout(1000)

        try:
            page.select_option("#ctl00_ContentPlaceHolder3_ddlTipoDoc", TIPO_DOC_MAP[document_type])
        except Exception as e:
            options = page.eval_on_selector(
                "#ctl00_ContentPlaceHolder3_ddlTipoDoc",
                "el => Array.from(el.options).map(o => ({value: o.value, text: o.text}))",
            )
            screenshot_path = os.path.join(str(TEMP_CERTS_DIR), f"rnmc_debug_{document_number}.png")
            page.screenshot(path=screenshot_path, full_page=True)
            return {"status": "failed", "error_message": f"select_option failed: {e} | options={options} | screenshot={screenshot_path}"}

        page.wait_for_load_state("networkidle")
        page.wait_for_timeout(1000)

        page.fill("#ctl00_ContentPlaceHolder3_txtExpediente", document_number)

        if document_type == "CC":
            page.fill("#txtFechaexp", issuance_date)
            page.keyboard.press("Escape")
            page.locator("#ctl00_ContentPlaceHolder3_txtExpediente").focus()
            page.wait_for_timeout(300)
            page.click("#ctl00_ContentPlaceHolder3_btnConsultar2")
        else:
            page.click("#ctl00_ContentPlaceHolder3_btnConsultar")

        page.wait_for_load_state("networkidle", timeout=20000)

        page.wait_for_function(
            """() => {
                const overlays = document.querySelectorAll('[style*="display"]');
                for (const el of overlays) {
                    const text = el.innerText || '';
                    if (text.includes('Cargando') || text.includes('procesando')) {
                        if (el.offsetParent !== null && getComputedStyle(el).display !== 'none') {
                            return false;
                        }
                    }
                }
                const progress = document.getElementById('ctl00_ContentPlaceHolder3_UpdateProgress1');
                if (progress && getComputedStyle(progress).display !== 'none') return false;
                const body = document.body.innerText.toUpperCase();
                return body.includes('MEDIDAS CORRECTIVAS') || body.includes('NO TIENE');
            }""",
            timeout=25000,
        )
        page.wait_for_timeout(500)

        modal_texto = page.locator("#ctl00_ContentPlaceHolder3_lblcontenidomodal").inner_text().strip()
        if modal_texto:
            return {"status": "failed", "error_message": modal_texto}

        contenido = page.content()
        if "MEDIDAS CORRECTIVAS" not in contenido.upper():
            return {"status": "failed", "error_message": "No se detectó respuesta reconocible del sitio"}

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        pdf_path = str(TEMP_CERTS_DIR / f"rnmc_{document_number}_{timestamp}.pdf")
        page.pdf(path=pdf_path, format="A4", print_background=True)

        return {"status": "success", "pdf_path": pdf_path}
    except Exception:
        screenshot_path = os.path.join(str(TEMP_CERTS_DIR), f"rnmc_error_{document_number}.png")
        try:
            page.screenshot(path=screenshot_path, full_page=True)
        except Exception:
            pass
        raise
    finally:
        context.close()
