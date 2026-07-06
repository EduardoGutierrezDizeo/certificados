from pathlib import Path

TEMP_CERTS_DIR = Path(__file__).parent / "temp_certs"
TEMP_CERTS_DIR.mkdir(exist_ok=True)