<x-legal-layout :title="'Política de Tratamiento de Datos Personales'">
    <div class="mb-8 pb-6 border-b border-ink-100">
        <h1 class="font-serif text-3xl text-ink-700 tracking-tight mb-3">
            Política de Tratamiento de Datos Personales — CertiCheck
        </h1>
        <p class="text-xs text-carbon/50 mb-2">
            En cumplimiento de la Ley 1581 de 2012, el Decreto 1377 de 2013 y demás normas concordantes de protección de datos personales en Colombia
        </p>
        <p class="text-sm text-carbon/50">
            Versión {{ config('legal.terms_version') }} · Última actualización: {{ config('legal.terms_updated_at') }}
        </p>
    </div>

    <div class="prose-custom space-y-6 text-sm leading-relaxed text-carbon/80">

        <p>CertiCheck es una plataforma tecnológica operada por Eduardo José Gutierrez De Piñerez Dizeo, identificado con NIT/cédula 1004819300, con domicilio en Convencion, Colombia, en adelante "CertiCheck", "nosotros" o "la Plataforma".</p>
        <p>Para efectos de contacto, dudas, reclamos o ejercicio de derechos, puede escribir a edojose1518@gmail.com.</p>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">1. Responsable del tratamiento</h2>
            <p>Eduardo José Gutierrez De Piñerez Dizeo, Cedula 1004819300, con domicilio en Convencion, Colombia, correo de contacto edojose1518@gmail.com, es responsable del tratamiento de los datos personales recolectados a través de CertiCheck.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">2. Datos que se recolectan y tratan</h2>
            <p>CertiCheck trata dos categorías claramente distintas de datos personales:</p>

            <h3 class="font-medium text-carbon mt-4 mb-1">2.1. Datos de los usuarios registrados (los abogados)</h3>
            <p>Al registrarse y usar la Plataforma, se recolectan: nombre completo, correo electrónico, contraseña (almacenada de forma cifrada), historial de pagos y suscripciones, dirección IP y datos técnicos de sesión (para la funcionalidad de sesión única y seguridad de la cuenta), y cualquier información suministrada al reportar un problema.</p>

            <h3 class="font-medium text-carbon mt-4 mb-1">2.2. Datos de las personas consultadas (sujetos de la consulta)</h3>
            <p>Cuando un abogado usa la Plataforma para solicitar un certificado sobre una persona, CertiCheck trata: tipo y número de documento de identidad, nombre completo, y, como resultado de la consulta, datos sensibles relativos a antecedentes judiciales, fiscales y disciplinarios de dicha persona.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">3. Finalidades del tratamiento</h2>
            <p>Los datos se tratan para:</p>
            <ul class="list-disc list-inside space-y-1 ml-4 mt-2">
                <li>Permitir el registro, autenticación y gestión de la cuenta del usuario.</li>
                <li>Procesar pagos y gestionar suscripciones.</li>
                <li>Realizar las consultas de antecedentes solicitadas por el usuario ante las entidades competentes, y entregar los certificados resultantes.</li>
                <li>Enviar comunicaciones relacionadas con el servicio (confirmaciones, vencimiento de suscripción, resolución de reportes de soporte).</li>
                <li>Prevenir el fraude y el uso indebido de la Plataforma (incluyendo el control de sesión única).</li>
                <li>Cumplir con las obligaciones legales aplicables.</li>
            </ul>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">4. Derechos de los titulares (Habeas Data)</h2>
            <p>De conformidad con la Constitución Política y la Ley 1581 de 2012, todo titular de datos personales tiene derecho a:</p>
            <ul class="list-disc list-inside space-y-1 ml-4 mt-2">
                <li>Conocer, actualizar y rectificar sus datos personales.</li>
                <li>Solicitar prueba de la autorización otorgada para el tratamiento (cuando aplique).</li>
                <li>Ser informado sobre el uso que se ha dado a sus datos.</li>
                <li>Presentar quejas ante la Superintendencia de Industria y Comercio por infracciones a la ley.</li>
                <li>Revocar la autorización y/o solicitar la supresión de sus datos, cuando no exista un deber legal o contractual que impida eliminarlos.</li>
                <li>Acceder de forma gratuita a sus datos objeto de tratamiento.</li>
            </ul>
            <p class="mt-2">Para ejercer estos derechos, cualquier titular (usuario registrado o persona consultada) puede escribir a edojose1518@gmail.com, indicando su nombre completo, documento de identidad, y una descripción clara de la solicitud.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">5. Encargados del tratamiento y transferencia de datos a terceros</h2>
            <p>Para operar, CertiCheck comparte o transmite datos con los siguientes terceros, únicamente en la medida necesaria para prestar el servicio:</p>
            <ul class="list-disc list-inside space-y-1 ml-4 mt-2">
                <li><strong>ePayco:</strong> para el procesamiento de pagos.</li>
                <li><strong>2Captcha:</strong> servicio de resolución de retos de verificación, utilizado como parte del proceso técnico de consulta ante los portales de las entidades públicas.</li>
                <li><strong>Entidades públicas fuente</strong> (Policía Nacional, Contraloría General, Procuraduría General): a quienes se consulta directamente la información solicitada.</li>
                <li><strong>Proveedores de infraestructura tecnológica</strong> (servidor, correo electrónico, almacenamiento), quienes actúan como encargados del tratamiento bajo instrucciones de CertiCheck.</li>
            </ul>
            <p class="mt-2">CertiCheck exige a sus encargados del tratamiento el cumplimiento de medidas de seguridad razonables sobre los datos que procesan en su nombre.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">6. Almacenamiento y seguridad de la información</h2>
            <ol class="list-decimal list-inside space-y-2 ml-4">
                <li><strong>6.1.</strong> Los certificados (PDFs) se almacenan en un disco de almacenamiento privado, no accesible públicamente, y solo pueden ser descargados por el abogado que realizó la solicitud correspondiente.</li>
                <li><strong>6.2.</strong> Las contraseñas de los usuarios se almacenan cifradas, nunca en texto plano.</li>
                <li><strong>6.3.</strong> CertiCheck implementa medidas técnicas y administrativas razonables para proteger los datos personales contra pérdida, uso indebido, acceso no autorizado, alteración o divulgación.</li>
            </ol>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">7. Menores de edad</h2>
            <p>CertiCheck no está dirigido a menores de edad y no recolecta intencionalmente datos de menores como usuarios registrados. En caso de que, como resultado de una consulta de antecedentes, se llegase a procesar información relacionada con un menor de edad, se aplicarán las medidas de protección reforzada que exige la ley colombiana para esta población.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">8. Cambios a esta política</h2>
            <p>Esta Política puede ser actualizada en cualquier momento. Los cambios sustanciales serán comunicados a los usuarios registrados por correo electrónico o mediante aviso dentro de la Plataforma.</p>
        </section>

        <section>
            <h2 class="font-serif text-lg text-ink-700 mb-2">9. Autoridad de control</h2>
            <p>Los titulares de datos personales pueden presentar quejas o consultas ante la Superintendencia de Industria y Comercio (SIC), autoridad de protección de datos en Colombia, en caso de considerar vulnerados sus derechos.</p>
        </section>
    </div>
</x-legal-layout>
