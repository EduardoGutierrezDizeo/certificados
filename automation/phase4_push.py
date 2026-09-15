"""Fase 4: empuja jobs reales de RNMC a la cola certificate_jobs.

Lee los datos reales (CC) de automation/test_data.local.json y pushea N jobs
con site="rnmc" para medir el comportamiento real del portal bajo concurrencia.

PRIVACIDAD: este script NUNCA imprime numeros de documento reales en su salida.
Identifica a cada persona como 'persona A'/'persona B' segun su posicion en el
archivo (por indice de archivo, no necesariamente por indice de job).

Uso:
    python phase4_push.py --count 2 --start-id 90001
    python phase4_push.py --count 4 --start-id 91001
"""

import argparse
import json
import sys
from datetime import datetime
from pathlib import Path

import redis


def mask(doc_number: str) -> str:
    """Devuelve un identificador no sensible: ultimos 3 digitos precedidos de *."""
    if not doc_number:
        return "?"
    visible = doc_number[-3:] if len(doc_number) >= 3 else doc_number
    return "*" * max(0, len(doc_number) - len(visible)) + visible


def main() -> int:
    parser = argparse.ArgumentParser(description="Empuja jobs reales de RNMC a la cola")
    parser.add_argument("--count", type=int, required=True, help="Numero de jobs a encolar")
    parser.add_argument("--start-id", type=int, required=True, help="certificate_request_id inicial")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=6379)
    parser.add_argument("--queue", default="certificate_jobs")
    args = parser.parse_args()

    local_path = Path(__file__).parent / "test_data.local.json"
    if not local_path.exists():
        print(f"[ERROR] No existe {local_path}. Cargalo con datos reales antes.")
        return 1

    raw = json.loads(local_path.read_text(encoding="utf-8"))
    personas = raw.get("personas", [])
    personas = [p for p in personas if p.get("document_number") and p.get("document_number") != "REEMPLAZAR"]
    if not personas:
        print("[ERROR] El archivo local no tiene personas con datos cargados.")
        return 1

    # Seleccionar personas: repite la primera si no alcanzan.
    selected = [personas[i % len(personas)] for i in range(args.count)]

    r = redis.Redis(host=args.host, port=args.port, decode_responses=True, socket_timeout=10)

    current = r.llen(args.queue)
    if current > 0:
        print(f"[ERROR] La cola '{args.queue}' NO esta vacia: {current} job(s). Abortando para no mezclar.")
        return 2

    pushed = 0
    for i, persona in enumerate(selected):
        cert_id = args.start_id + i
        # Origen de esta persona en el archivo (0-index -> etiqueta A, B, ...).
        src_idx = [j for j, p in enumerate(personas) if p is persona][0]
        label = chr(ord("A") + src_idx)

        try:
            issuance = datetime.strptime(persona["issuance_date"], "%Y-%m-%d").strftime("%d/%m/%Y")
        except (ValueError, TypeError):
            print(
                f"[ERROR] issuance_date invalida para persona {label} "
                f"({persona.get('issuance_date')!r}). Debe ser YYYY-MM-DD."
            )
            return 3

        payload = {
            "certificate_request_id": cert_id,
            "site": "rnmc",
            "document_type": persona["document_type"],
            "document_number": persona["document_number"],
            "full_name": None,
            "issuance_date": issuance,
        }
        r.rpush(args.queue, json.dumps(payload, ensure_ascii=False))
        print(
            f"[OK] Encolado job certificate_request_id={cert_id} "
            f"source=persona {label} doc={mask(persona['document_number'])} "
            f"type={persona['document_type']} issuance={issuance}"
        )
        pushed += 1

    print(f"LISTO: {pushed} job(s) de site=rnmc encolados en '{args.queue}' (total en cola: {r.llen(args.queue)}).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
