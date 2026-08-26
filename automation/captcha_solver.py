import logging
import os
import time

import capsolver
import requests
from dotenv import load_dotenv
from twocaptcha import TwoCaptcha

load_dotenv()

logger = logging.getLogger(__name__)

CAPSOLVER_API_KEY = os.getenv("CAPSOLVER_API_KEY")
TWOCAPTCHA_API_KEY = os.getenv("TWOCAPTCHA_API_KEY")


def _resolve_capsolver(sitekey: str, url: str) -> str | None:
    if not CAPSOLVER_API_KEY:
        logger.debug("CapSolver: no API key configurada, omitiendo")
        return None

    try:
        capsolver.api_key = CAPSOLVER_API_KEY
        start = time.time()
        solution = capsolver.solve({
            "type": "ReCaptchaV2TaskProxyLess",
            "websiteURL": url,
            "websiteKey": sitekey,
        })
        token = solution.get("gRecaptchaResponse")
        elapsed = round(time.time() - start, 1)

        if token:
            logger.info(f"CapSolver resolvió reCAPTCHA en {elapsed}s")
            return token

        logger.warning("CapSolver: respuesta sin token")
        return None
    except Exception as e:
        logger.warning(f"CapSolver falló: {e}")
        return None


def _resolve_2captcha(sitekey: str, url: str) -> str | None:
    if not TWOCAPTCHA_API_KEY:
        logger.debug("2Captcha: no API key configurada, omitiendo")
        return None

    try:
        solver = TwoCaptcha(TWOCAPTCHA_API_KEY)
        start = time.time()
        resultado = solver.recaptcha(sitekey=sitekey, url=url)
        token = resultado.get("code")
        elapsed = round(time.time() - start, 1)

        if token:
            logger.info(f"2Captcha resolvió reCAPTCHA en {elapsed}s")
            return token

        logger.warning("2Captcha: respuesta sin token")
        return None
    except Exception as e:
        logger.warning(f"2Captcha falló: {e}")
        return None


def resolver_recaptcha_v2(sitekey: str, url: str) -> dict:
    """Resuelve reCAPTCHA v2 con fallback: CapSolver → 2Captcha.

    Returns:
        dict con clave "code" (el token) y "provider" ("capsolver" o "2captcha").

    Raises:
        RuntimeError: si ambos servicios fallan.
    """
    token = _resolve_capsolver(sitekey, url)
    if token:
        return {"code": token, "provider": "capsolver"}

    token = _resolve_2captcha(sitekey, url)
    if token:
        return {"code": token, "provider": "2captcha"}

    raise RuntimeError("Ambos servicios de CAPTCHA fallaron (CapSolver y 2Captcha)")
