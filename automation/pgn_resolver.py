import re
import unicodedata

CAPITALES_DEPARTAMENTOS = {
    "AMAZONAS": "LETICIA", "ANTIOQUIA": "MEDELLIN", "ARAUCA": "ARAUCA",
    "ATLANTICO": "BARRANQUILLA", "BOLIVAR": "CARTAGENA", "BOYACA": "TUNJA",
    "CALDAS": "MANIZALES", "CAQUETA": "FLORENCIA", "CASANARE": "YOPAL",
    "CAUCA": "POPAYAN", "CESAR": "VALLEDUPAR", "CHOCO": "QUIBDO",
    "CORDOBA": "MONTERIA", "CUNDINAMARCA": "BOGOTA", "COLOMBIA": "BOGOTA",
    "GUAINIA": "INIRIDA", "GUAVIARE": "SAN JOSE DEL GUAVIARE", "HUILA": "NEIVA",
    "GUAJIRA": "RIOHACHA", "LA GUAJIRA": "RIOHACHA", "MAGDALENA": "SANTA MARTA",
    "META": "VILLAVICENCIO", "NARINO": "PASTO", "NORTE DE SANTANDER": "CUCUTA",
    "PUTUMAYO": "MOCOA", "QUINDIO": "ARMENIA", "RISARALDA": "PEREIRA",
    "SAN ANDRES": "SAN ANDRES", "SANTANDER": "BUCARAMANGA", "SUCRE": "SINCELEJO",
    "TOLIMA": "IBAGUE", "VALLE DEL CAUCA": "CALI", "VAUPES": "MITU",
    "VICHADA": "PUERTO CARRENO",
}

NUMEROS_TEXTO = {
    "uno": 1, "dos": 2, "tres": 3, "cuatro": 4, "cinco": 5,
    "seis": 6, "siete": 7, "ocho": 8, "nueve": 9, "diez": 10,
}


def _sin_tildes(texto: str) -> str:
    return "".join(
        c for c in unicodedata.normalize("NFD", texto)
        if unicodedata.category(c) != "Mn"
    ).upper().strip()


def resolver_pregunta(pregunta: str, full_name: str | None) -> str | None:
    """
    Intenta resolver la pregunta de verificación de Procuraduría.
    Retorna la respuesta como string, o None si no se puede resolver
    (pregunta no reconocida, o depende de full_name y no lo tenemos).
    """
    texto = _sin_tildes(pregunta)

    # 1. Matemática: "CUANTO ES 5 + 3" / "CUANTO ES 2 X 3"
    m = re.search(r"CUANTO ES\s*(\d+)\s*([+\-X])\s*(\d+)", texto)
    if m:
        a, op, b = int(m.group(1)), m.group(2), int(m.group(3))
        if op == "+":
            return str(a + b)
        elif op == "-":
            return str(a - b)
        elif op == "X":
            return str(a * b)

    # 2. Capital de departamento: "CUAL ES LA CAPITAL DEL ATLANTICO"
    m = re.search(r"CAPITAL DE(?:L)?\s+(.+?)\s*(?:\(SIN TILDE\))?\?*$", texto)
    if m:
        depto = m.group(1).strip()
        if depto in CAPITALES_DEPARTAMENTOS:
            return CAPITALES_DEPARTAMENTOS[depto]
        return None  # departamento no reconocido, mejor no adivinar

    # De aquí en adelante, todas dependen de full_name
    if not full_name:
        return None
    primer_nombre = full_name.strip().split()[0]

    # 3. Primeros N dígitos del documento: se resuelve fuera de esta función
    #    (requiere el número de documento, no el nombre) — ver resolver_pregunta_completa

    # 4. Nombre completo (primer nombre)
    if "CUAL ES EL PRIMER NOMBRE" in texto:
        return primer_nombre

    # 5. Cantidad de letras del primer nombre
    if "CANTIDAD DE LETRAS" in texto:
        return str(len(primer_nombre))

    # 6. Primeras N letras del primer nombre
    m = re.search(r"PRIMERAS?\s+(\w+)\s+LETRAS", texto)
    if m:
        n = NUMEROS_TEXTO.get(m.group(1).lower())
        if n:
            return primer_nombre[:n]

    return None


def resolver_pregunta_completa(pregunta: str, full_name: str | None, numero_documento: str) -> str | None:
    """Versión completa que también resuelve preguntas sobre el número de documento."""
    texto = _sin_tildes(pregunta)

    m = re.search(r"PRIMEROS?\s+(\w+)\s+DIGITOS", texto)
    if m:
        n = NUMEROS_TEXTO.get(m.group(1).lower())
        if n:
            return numero_documento[:n]

    m = re.search(r"ULTIMOS?\s+(\w+)\s+DIGITOS", texto)
    if m:
        n = NUMEROS_TEXTO.get(m.group(1).lower())
        if n:
            return numero_documento[-n:]

    return resolver_pregunta(pregunta, full_name)