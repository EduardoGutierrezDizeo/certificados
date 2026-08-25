<x-legal-layout :title="'Términos y Condiciones'">
    <div class="mb-8 pb-6 border-b border-ink-100">
        <h1 class="font-serif text-3xl text-ink-700 tracking-tight mb-3">
            Términos y Condiciones de Uso — CertiCheck
        </h1>
        <p class="text-sm text-carbon/50">
            Versión {{ config('legal.terms_version') }} · Última actualización: {{ config('legal.terms_updated_at') }}
        </p>
    </div>

    <div class="prose-custom space-y-6 text-sm leading-relaxed text-carbon/80">

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">1. Identificación del prestador del servicio</h2>
            <p>CertiCheck es una plataforma tecnológica operada por Eduardo José Gutierrez De Piñerez Dizeo, identificado con NIT/cédula 1004819300, con domicilio en Convencion, Colombia, en adelante "CertiCheck", "nosotros" o "la Plataforma".</p>
            <p>Para efectos de contacto, dudas, reclamos o ejercicio de derechos, puede escribir a edojose1518@gmail.com.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">2. Objeto del servicio</h2>
            <p>CertiCheck es una plataforma tipo SaaS (software como servicio) dirigida exclusivamente a abogados y profesionales del derecho debidamente identificados, que permite gestionar y automatizar la solicitud de los siguientes certificados de antecedentes ante entidades públicas colombianas:</p>
            <ul class="list-disc list-inside space-y-1 ml-4 mt-2">
                <li>Medidas Correctivas (Policía Nacional)</li>
                <li>Antecedentes Judiciales (Policía Nacional)</li>
                <li>Antecedentes Fiscales (Contraloría General de la República)</li>
                <li>Antecedentes Disciplinarios (Procuraduría General de la Nación)</li>
            </ul>
            <p class="mt-2">CertiCheck actúa como un intermediario tecnológico que consulta información pública ante los portales oficiales de dichas entidades. CertiCheck no es una entidad gubernamental, no expide certificados oficiales por sí misma, y no garantiza la disponibilidad, exactitud, vigencia ni continuidad de los portales de terceros de los cuales depende.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">3. Requisitos para usar la Plataforma</h2>
            <p>Para crear una cuenta y usar CertiCheck, el usuario declara y garantiza que:</p>
            <ol class="list-decimal list-inside space-y-2 ml-4 mt-2">
                <li><strong>3.1.</strong> Es abogado titulado o profesional autorizado para realizar consultas de antecedentes en ejercicio de su actividad profesional, y que usará la Plataforma únicamente para fines lícitos relacionados con dicho ejercicio.</li>
                <li><strong>3.2.</strong> Cuenta con la autorización, mandato o facultad legal correspondiente para consultar los antecedentes de las personas (sujetos) que registre en la Plataforma, y asume la responsabilidad exclusiva de contar con dicha autorización o base legal frente al titular de los datos consultados.</li>
                <li><strong>3.3.</strong> No usará la Plataforma para fines de acoso, discriminación, vigilancia no autorizada, ni ningún propósito distinto al ejercicio legítimo de su profesión.</li>
                <li><strong>3.4.</strong> La información que suministra al registrarse (nombre, correo electrónico, documento de identidad y demás datos requeridos) es veraz, completa y actualizada.</li>
            </ol>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">4. Cuenta de usuario y seguridad</h2>
            <ol class="list-decimal list-inside space-y-2 ml-4">
                <li><strong>4.1.</strong> Cada cuenta es personal e intransferible. Queda prohibido compartir credenciales de acceso con terceros.</li>
                <li><strong>4.2.</strong> CertiCheck implementa un mecanismo de sesión única activa por cuenta: si se inicia sesión desde un nuevo dispositivo o navegador, la sesión previamente activa será cerrada automáticamente. Esto tiene como fin proteger la cuenta del usuario y prevenir el uso compartido no autorizado de credenciales.</li>
                <li><strong>4.3.</strong> El usuario es responsable de mantener la confidencialidad de su contraseña y de notificar de inmediato a CertiCheck cualquier uso no autorizado de su cuenta, escribiendo a edojose1518@gmail.com o a través de la sección "Reportar problema" dentro de la Plataforma.</li>
                <li><strong>4.4.</strong> CertiCheck se reserva el derecho de suspender o cancelar cuentas que incumplan estos Términos, sin perjuicio de las acciones legales a que haya lugar.</li>
            </ol>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">5. Planes, precios y forma de pago</h2>
            <ol class="list-decimal list-inside space-y-2 ml-4">
                <li><strong>5.1.</strong> CertiCheck ofrece distintos planes de suscripción, cuyo nombre, precio, duración y beneficios son definidos y pueden ser modificados por CertiCheck en cualquier momento. Los cambios de precio o condiciones de un plan no afectan retroactivamente a los usuarios que ya tienen una suscripción activa bajo las condiciones vigentes al momento de su contratación; dichas condiciones se mantienen hasta el vencimiento del período ya pagado.</li>
                <li><strong>5.2.</strong> Los pagos se procesan a través de la pasarela de pagos ePayco, aceptando los medios de pago que dicha pasarela habilite (PSE, tarjeta de crédito/débito, Nequi, entre otros). CertiCheck no almacena datos completos de tarjetas de crédito o débito; dicha información es procesada directamente por la pasarela de pago bajo sus propias políticas de seguridad (PCI-DSS u otras aplicables).</li>
                <li><strong>5.3.</strong> Las suscripciones no se renuevan automáticamente mediante cobro recurrente; el usuario debe realizar manualmente el pago correspondiente para renovar su acceso antes del vencimiento de su plan vigente.</li>
                <li><strong>5.4.</strong> El usuario puede cancelar su suscripción en cualquier momento desde la sección "Mi suscripción". La cancelación surte efecto de manera inmediata, incluso si al momento de cancelar quedaba tiempo pagado pendiente de uso. CertiCheck no realiza reembolsos por el tiempo no utilizado tras una cancelación voluntaria, salvo que la ley aplicable disponga lo contrario.</li>
            </ol>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">6. Exactitud y limitación de responsabilidad sobre los certificados</h2>
            <ol class="list-decimal list-inside space-y-2 ml-4">
                <li><strong>6.1.</strong> CertiCheck obtiene los certificados directamente de los portales oficiales de las entidades competentes, sin alterar su contenido. Sin embargo, CertiCheck no garantiza:
                    <ul class="list-disc list-inside space-y-1 ml-6 mt-1">
                        <li>La disponibilidad continua o ininterrumpida de los portales de dichas entidades.</li>
                        <li>La exactitud, vigencia o completitud de la información contenida en los certificados, la cual es responsabilidad exclusiva de la entidad emisora.</li>
                        <li>Que un certificado se pueda obtener siempre en un tiempo determinado, dado que depende de sistemas de terceros fuera del control de CertiCheck.</li>
                    </ul>
                </li>
                <li><strong>6.2.</strong> El usuario es el único responsable del uso, interpretación y destino que le dé a los certificados obtenidos a través de la Plataforma.</li>
                <li><strong>6.3.</strong> CertiCheck no será responsable por daños o perjuicios derivados de la indisponibilidad temporal o permanente de alguno de los portales de las entidades públicas de las que depende el servicio.</li>
            </ol>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">7. Propiedad intelectual</h2>
            <p>Todo el software, diseño, marca, logotipos y demás elementos de CertiCheck son propiedad de Eduardo José Gutierrez De Piñerez Dizeo o de sus licenciantes, y están protegidos por la normativa colombiana de propiedad intelectual. Queda prohibida su reproducción, distribución o uso no autorizado.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">8. Soporte y reporte de problemas</h2>
            <p>El usuario puede reportar fallas, errores o inconvenientes a través de la sección "Reportar problema" dentro de la Plataforma. CertiCheck se compromete a revisar los reportes en un plazo razonable, sin que ello constituya una garantía de disponibilidad del servicio 24/7.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">9. Modificaciones a estos Términos</h2>
            <p>CertiCheck podrá modificar estos Términos en cualquier momento. Los cambios sustanciales serán informados a los usuarios activos por correo electrónico o mediante aviso dentro de la Plataforma, con una antelación razonable a su entrada en vigor. El uso continuado de la Plataforma después de dicho aviso implica la aceptación de los nuevos Términos.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">10. Terminación</h2>
            <p>CertiCheck podrá suspender o terminar el acceso de un usuario, sin previo aviso, en caso de incumplimiento de estos Términos, uso fraudulento de la Plataforma, o uso que ponga en riesgo la operación, seguridad o reputación de CertiCheck o de terceros.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">11. Ley aplicable y jurisdicción</h2>
            <p>Estos Términos se rigen por las leyes de la República de Colombia. Cualquier controversia derivada de su interpretación o cumplimiento será sometida a los jueces y tribunales competentes de Ocaña, Colombia, sin perjuicio de los mecanismos alternativos de solución de conflictos que las partes puedan acordar.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">12. Aceptación</h2>
            <p>El registro y uso de la Plataforma implica la aceptación plena e incondicional de estos Términos y de la Política de Tratamiento de Datos Personales que se presenta a continuación.</p>
        </section>
    </div>
</x-legal-layout>
