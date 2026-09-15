from sites.pgn_resolver import resolver_pregunta, resolver_pregunta_completa


def test_primer_nombre_saltando_toponimo():
    """Si el primer token de full_name es un topónimo (ciudad/departamento),
    no se responde con él: se usa el siguiente token como primer nombre."""
    resultado = resolver_pregunta(
        "CUAL ES EL PRIMER NOMBRE", "BARRANQUILLA PEREZ"
    )
    assert resultado == "PEREZ"


def test_primer_nombre_con_toponimo_y_dos_tokens():
    """Varios topónimos al inicio se saltan; se toma el primer token no topónimo."""
    resultado = resolver_pregunta(
        "CUAL ES EL PRIMER NOMBRE", "BOGOTA CALI MARIA SALAZAR"
    )
    assert resultado == "MARIA"


def test_primer_nombre_solo_toponimo_retorna_none():
    """Si full_name es solo un topónimo (sin un token que sirva de nombre),
    se retorna None para no responder con un valor sospechoso."""
    assert resolver_pregunta("CUAL ES EL PRIMER NOMBRE", "BARRANQUILLA") is None


def test_primer_nombre_normal_inalterado():
    """Con un nombre normal, el primer nombre se conserva tal cual."""
    assert resolver_pregunta("CUAL ES EL PRIMER NOMBRE", "JUAN PEREZ") == "JUAN"


def test_toponimo_ignora_tildes_y_mayusculas():
    """La comparación de topónimo normaliza tildes y mayúsculas."""
    assert resolver_pregunta("CUAL ES EL PRIMER NOMBRE", "bogotá pérez") == "pérez"


def test_cantidad_letras_con_toponimo_retorna_none():
    """'CANTIDAD DE LETRAS' también depende del primer nombre; sin primer
    nombre válido devuelve None en vez de medir sobre el topónimo."""
    assert resolver_pregunta("CANTIDAD DE LETRAS", "BARRANQUILLA") is None


def test_primeras_letras_con_toponimo_retorna_none():
    """'PRIMERAS N LETRAS' depende del primer nombre limpio; sin él, None."""
    assert resolver_pregunta("PRIMERAS DOS LETRAS", "BARRANQUILLA") is None


def test_capital_departamento_sigue_funcionando():
    """La rama de capital de departamento no debe verse afectada."""
    assert resolver_pregunta("CUAL ES LA CAPITAL DEL ATLANTICO", "JUAN PEREZ") == "BARRANQUILLA"


def test_pregunta_completa_con_toponimo():
    """La versión completa (la que usa procuraduria.py) respeta la limpieza
    del primer nombre y los dígitos del documento."""
    assert (
        resolver_pregunta_completa(
            "CUAL ES EL PRIMER NOMBRE", "BARRANQUILLA PEREZ", "123456"
        )
        == "PEREZ"
    )
    assert resolver_pregunta_completa("CUAL ES EL PRIMER NOMBRE", "BARRANQUILLA", "123456") is None
