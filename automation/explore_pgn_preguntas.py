from playwright.sync_api import sync_playwright

URL = "https://www.procuraduria.gov.co/Pages/Generacion-de-antecedentes.aspx"
N_INTENTOS = 100

with sync_playwright() as p:
    browser = p.chromium.launch(headless=False, slow_mo=200)
    page = browser.new_page()
    page.goto(URL)
    page.wait_for_load_state("networkidle")

    frame = page.frames[1]
    preguntas_vistas = set()

    for i in range(N_INTENTOS):
        pregunta = frame.locator("#lblPregunta").inner_text().strip()
        if pregunta not in preguntas_vistas:
            preguntas_vistas.add(pregunta)
            print(f"[{len(preguntas_vistas)}] {pregunta}")

        frame.click("#ImageButton1")

        try:
            frame.locator("#UpdateProgress").wait_for(state="visible", timeout=2000)
        except Exception:
            pass
        frame.locator("#UpdateProgress").wait_for(state="hidden", timeout=8000)
        page.wait_for_timeout(500)

    print("\n--- Resumen de preguntas distintas encontradas ---")
    for p_ in sorted(preguntas_vistas):
        print(f"- {p_}")

    print("\nListo. Presiona Enter para cerrar...")
    input()
    browser.close()