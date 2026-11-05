# Plan para completar la gestión de obras

Fecha de revisión: 5 de noviembre de 2026.
Estado: ejecución parcial con ampliación operativa aplicada el 5 de noviembre de 2026. Véase el seguimiento al final; las casillas históricas del diagnóstico no equivalen a funciones verificadas.

## 1. Conclusión y objetivo

El proyecto tiene una base funcional para administrar obras, capítulos, órdenes, fases, empleados, contratistas, mediciones, presupuestos y costes reales de materiales y horas. Permite iniciar una operativa interna supervisada. Todavía no cubre de principio a fin la gestión comercial, contractual, productiva y económica de una empresa de obras.

Las carencias principales son: un editor de presupuesto con un único guardado fiable; versiones y aceptación de ofertas; traspaso del presupuesto aceptado a ejecución y facturación; seguimiento de avance y extras; contratación y liquidación de subcontratas; correcciones de partes confirmados; compras y cobros/pagos; permisos por función y obra; trazabilidad y recuperación operativa.

«100 % completo» se define aquí como completar y verificar el ciclo acordado para una empresa de obras en España: solicitud → oferta → aceptación → planificación → ejecución → certificación/facturación → cobro → cierre y garantía. No significa incorporar todas las funciones de cualquier ERP. Nóminas, contabilidad general, BIM o multiempresa pueden resolverse mediante integraciones o quedar fuera del alcance acordado.

## 2. Alcance de la revisión y límites

Revisión del inventario de modelos y migraciones, rutas, recursos y formularios Filament, servicios de presupuestos/importación, costes, facturación, impresión, configuración de acceso, documentación y pruebas. Versiones comprobadas: PHP 8.3.36, Laravel 13.39.0, Filament 5.11.1, PHPUnit 12.5.43. No se han cambiado dependencias ni funcionalidades en esta revisión.

Se aplican las skills de Laravel, Filament y pruebas para valorar el código y los flujos. No se ejecuta una migración de convenciones ni se reestructura la aplicación. No existe .ai/rules en este directorio.

Clasificación utilizada:

- **Existente:** hay código y una ruta de uso; no equivale a validación completa en producción.
- **Parcial:** hay una parte, pero falta cerrar su flujo.
- **Ausente en el repositorio:** no se ha localizado un modelo, pantalla o servicio que resuelva esa necesidad.
- **Pendiente de verificación:** requiere navegador, infraestructura real o validación de negocio; no se presenta como fallo demostrado.

La consulta de esquema de Boost devolvió una conexión SQLite sin tablas; no permite certificar el esquema de la base operativa. El análisis estructural se apoya en las migraciones y modelos. No se ha inspeccionado el contenido privado de .env, certificados, claves ni datos de clientes. La existencia de copias puntuales documentadas no acredita una política automática de respaldo ni su restauración.

No se ha hecho una prueba interactiva completa con navegador, una auditoría de penetración, una prueba de carga ni una certificación jurídica/fiscal. Los hallazgos de código indicados abajo son distintos de las hipótesis que deben probarse.

## 3. Qué existe y qué falta

| Área | Estado encontrado | Brecha principal |
| --- | --- | --- |
| Empresa, clientes y proveedores | Existente: ficha, contactos, direcciones, roles y condiciones comerciales | Expediente documental, controles de acceso y trazabilidad |
| Obras | Existente: cliente, responsable, dirección, fechas y estados | Vista integral económica, avance, documentos, incidencias y cierre controlado |
| Capítulos y mediciones | Existente: jerarquía, partidas, importaciones Excel/BC3 y PDF compatible, descompuestos de referencia | Revisiones, comparación, edición productiva y reconciliación de cambios |
| Órdenes y fases | Existente: fechas, prioridades, instrucciones y asignaciones | Dependencias, capacidad, avance real y reglas completas de transición/cierre |
| Presupuestos | Parcial: instantáneas, selección, precio/cantidad, manuales, borrado e impresión | Guardado coherente, versiones, aceptación, condiciones, impuestos y conversión a ejecución |
| Empleados | Parcial: ficha, usuario opcional, fechas laborales, asignación, tarifa y parte por fase | Calendario, ausencias, aprobación/corrección de horas y acceso de operario |
| Contratistas | Parcial: tercero con rol subcontractor, asignaciones y coste por horas | Contratos a precio cerrado/medición, certificación, facturas recibidas y liquidación |
| Materiales y almacén | Parcial: recepción por lote, reservas, consumos, devolución completa de consumo y coste | Pedidos, recepción parcial, devolución a proveedor, inventario y transferencias |
| Costes | Parcial: materiales y horas confirmados por fase/orden | Subcontratas por importe, maquinaria, indirectos, comprometido, previsión y margen consolidado |
| Facturación | Parcial: borradores, emisión de pruebas, rectificativas, XML/QR y envío preparado | Producción bloqueada, subsanación/anulación y aceptación integral fiscal |
| Tesorería | Ausente como flujo: existe fecha de vencimiento y maestros de condiciones | Cobros/pagos reales, plazos, anticipos, conciliación y deuda |
| Documentos | Parcial: impresión del navegador y archivos de importación temporales | Plantillas comerciales completas, originales archivados, firmas y control de versiones |
| Seguridad | Parcial: autenticación, comprobaciones canView/canEdit y restricciones de negocio | Políticas reales por usuario/obra, altas/bajas, segregación y auditoría |
| Operación técnica | Parcial: tests, configuración de colas, health route y envío programado | CI, despliegue reproducible, recuperación, alertas y verificación de producción |

No se consideran pendientes las funciones que ya existen: no hay que rehacer fases, asignaciones, tarifas, recepciones, consumos, importadores ni el cálculo fiscal desde cero.

## 4. Hallazgos prioritarios sobre lo ya implementado

Estos problemas deben corregirse antes de ampliar mucho más el producto.

### F01 — Los dos guardados del presupuesto pueden descartar trabajo

Prioridad P0. Evidencia: app/Filament/Resources/Budgets/Pages/EditBudget.php, métodos getHeaderActions() y loadEditor().

«Guardar presupuesto» persiste nombre/selección y recarga las líneas manuales desde la base. «Guardar líneas manuales» persiste ese bloque y recarga nombre/selección. Ejemplos deducibles del código: escribir una línea manual y pulsar Guardar presupuesto la descarta; cambiar el nombre y guardar las líneas manuales restaura el nombre anterior.

Acción: un único borrador de edición y un guardado atómico de todos los cambios. Si se mantienen guardados separados, nunca deben descartar los cambios del otro bloque y deben señalar lo pendiente. Incluir aviso al abandonar con cambios.

Aceptación: editar nombre, partidas importadas y líneas manuales, borrar una línea y guardar una vez; al reabrir se conserva todo. Un error de validación conserva el borrador completo.

### F02 — Una línea manual aparece en dos editores

Prioridad P0. Evidencia: loadEditor() carga las líneas manuales y también incluye todos los items en selection; getViewData() envía todos los items al árbol de capítulos. La misma línea guardada puede editarse en «Líneas manuales» y en «Sin capítulo», con estados independientes.

Acción: una única representación editable por línea, identificador estable y cálculo de totales desde el borrador vigente. Evitar claves basadas solo en posición al borrar/reordenar.

Aceptación: cada línea aparece una sola vez; cambiar precio o eliminarla actualiza inmediatamente cantidades, subtotales y total; no reaparece al guardar otro bloque. La nueva línea aún no guardada debe contar en la vista previa.

### F03 — Los bloqueos no detectan una edición obsoleta de presupuesto

Prioridad P1, P0 si habrá varios editores simultáneos. Evidencia: BudgetEditor bloquea el registro durante la escritura, pero no compara la versión que abrió el usuario. saveManualLines sustituye el bloque manual completo.

Acción: control de versión del documento y conflicto explícito, sin sobrescritura silenciosa. Decidir recuperación o comparación de cambios.

Aceptación: dos sesiones abren la misma oferta; la segunda no puede borrar los cambios guardados por la primera sin resolver el conflicto.

### F04 — Falta separar usuarios y permisos reales

Prioridad P0 antes de dar acceso a empleados o contratistas. Evidencia: User::canAccessPanel admite cualquier usuario persistido para admin; no se localizan políticas de negocio registradas, roles de aplicación ni filtros de visibilidad por obra. Los roles de Party clasifican terceros, no otorgan permisos a usuarios.

Filament permite operaciones sin una policy o método de policy, salvo restricciones adicionales. Las llamadas actuales a canEdit/canView son puntos de extensión útiles, pero no equivalen a un sistema de roles ya implementado.

Acción: matriz de administrador, administración, jefe de obra, encargado, operario y acceso externo si procede; políticas, consultas filtradas y autorización de acciones, relaciones, selectores, exportaciones e impresión. Alta, desactivación y revocación de sesiones de cuentas.

Aceptación: un operario ve solo sus asignaciones y partes y no accede a costes o tarifas; un jefe solo a sus obras; un contratista solo a su información autorizada. Probar también URLs y peticiones directas, no solo botones ocultos.

### F05 — La impresión del albarán no representa su coste neto

Prioridad P1. Evidencia: DocumentPrintController construye quantity/price sin descuento y documents/print.blade.php calcula cantidad × precio. ProjectCosting::receive sí aplica el descuento y conserva net_total.

Acción: imprimir precio, descuento e importe neto real, más unidad y cabecera histórica. En recibidos, utilizar las instantáneas guardadas cuando existan, no exclusivamente nombres actuales de maestros.

Aceptación: cantidad 2 × precio 100 con descuento 10 % imprime neto 180, no 200. El documento conserva coherencia tras renombrar una presentación/producto.

### F06 — Impresión comercial y orden de trabajo incompletas

Prioridad P1. Evidencia: plantilla genérica documents/print.blade.php. No incorpora identidad completa de emisor/cliente para la oferta, validez, condiciones o aceptación. En órdenes tampoco presenta las instrucciones, asignaciones y fechas que existen en el modelo.

Acción: plantillas separadas por tipo y audiencia; oferta comercial, orden para ejecución, fase, parte y albarán. Elegir si la copia del operario debe ocultar precios.

Aceptación: un cliente puede identificar quién oferta, qué se incluye, cuánto paga y hasta cuándo; el equipo recibe las instrucciones y fechas necesarias. Probar paginación, textos largos y documentos vacíos.

### F07 — Validar teclado y compatibilidad de entrada española

Prioridad P1. Hay manejadores de Tab en Blade y en PurchaseDeliveryNoteForm, pero las pruebas Livewire no ejecutan ese JavaScript. El foco de nuevas líneas y los selectores anidados requieren prueba real. Los campos manuales son de texto y BudgetEditor espera números normalizados; no se encuentra normalización de coma decimal en ese flujo.

Acción: recorrido de teclado completo, salida de la última fila, Shift+Tab, eliminación, errores, anidación de modales y recuperación del foco. Acordar y normalizar coma/punto decimal sin ambigüedad; tratar la fila vacía creada al final sin bloquear guardados válidos. En el repetidor, proponer posiciones consecutivas en lugar de repetir por defecto 1.

Aceptación: introducir diez líneas, crear un producto desde selector, corregir y borrar filas y guardar sin ratón; 12,50 y 12.50 se tratan según la regla visible acordada. Sin filas duplicadas ni pérdida de datos tras un error.

### F08 — Fechas y estados de personal no cierran toda la elegibilidad

Prioridad P1. Evidencia: ProjectCosting::postTime comprueba trabajador activo, asignación no cancelada y pertenencia a la orden/fase; no exige que las fechas del parte estén dentro de fechas laborales o fechas planificadas de la asignación. BusinessRecordValidator valida orden cronológico de fechas, pero no una máquina completa de estados.

Acción: definir las fechas que son restricciones y las que solo son previsión. Bloquear o exigir autorización para horas fuera de vigencia, imputación en obra cerrada y reaperturas. No convertir automáticamente toda fecha planificada en prohibición de trabajar.

Aceptación: casos antes del alta, después de la baja, asignación finalizada, obra cerrada y reapertura excepcional tienen una respuesta de negocio explícita y trazable.

### F09 — Facturación real sigue bloqueada por diseño

Prioridad P0 si se pretende emitir facturas reales desde esta aplicación. Evidencia: SalesBilling::issue rechaza series de producción. Cambiar VERIFACTU_PRODUCTION solo selecciona el destino del transporte; no elimina ese bloqueo.

Acción: completar el módulo fiscal o usar una integración con un sistema de facturación operativo mientras tanto. No retirar simplemente la validación.

Aceptación: superar el bloque fiscal del apartado 10 antes de habilitar producción. La gestión interna de obras puede pilotarse sin convertir facturas de prueba en reales.

## 5. Paquete A — Presupuestos y contratación con clientes

Prioridad P1. Depende de F01–F03.

- [ ] Presupuestar desde una obra u oportunidad sin obligar a crear previamente una orden de ejecución. Conservar la creación desde mediciones y desde órdenes.
- [ ] Referencia comercial legible y numeración configurable; fecha, cliente/contacto, dirección de obra, moneda, validez y responsable.
- [ ] Estados: borrador, enviado, aceptado, rechazado, caducado y sustituido; fechas, autor y motivo.
- [ ] Versiones: duplicar/revisar, comparar cantidades y precios, conservar el documento enviado/aceptado y bloquear su modificación directa.
- [ ] Condiciones de pago, hitos, exclusiones, plazo estimado y condiciones comerciales.
- [ ] Descuentos, impuestos aplicables, totales y redondeo consistente con la futura factura. Separar retención fiscal de retención de garantía.
- [ ] Capítulos/subcapítulos manuales, orden de líneas, duplicación, catálogo de partidas reutilizables y costes previstos por recurso.
- [ ] Registrar aceptación con evidencia: documento firmado, correo archivado o mecanismo de firma elegido; no exigir firma electrónica avanzada para todos los casos sin justificarla.
- [ ] Convertir la versión aceptada en alcance de ejecución con vínculo de origen por línea. Crear/fusionar órdenes y fases de forma explícita e idempotente.
- [ ] Extras y modificados: oferta adicional, aprobación y coste separado, sin reescribir el presupuesto original.

Aceptación: una oferta manual de dos líneas se revisa, acepta y transforma en ejecución; el precio aceptado llega a la certificación/factura. Repetir la conversión no duplica partidas. La versión anterior y el extra autorizado siguen consultables.

Evidencia de la brecha: Budget y su migración solo contienen obra/origen/referencia/nombre/divisa/items; no hay estado, versión, aceptación o enlace desde la factura al presupuesto. SalesBilling::fromWorkOrder factura las partidas de la orden, no el precio modificado en la oferta independiente.

## 6. Paquete B — Ejecución y control de obra

Prioridad P1. Puede avanzarse en paralelo temporal al paquete A, respetando sus dependencias funcionales.

- [ ] Ficha integral con presupuesto aceptado, órdenes, fases, personal, documentos, costes, avance, facturación y cobro.
- [ ] Planificación de tareas, hitos y dependencias; calendario de recursos, disponibilidad y avisos de conflicto.
- [ ] Avance por cantidad ejecutada y por fase; medición prevista, ejecutada, certificada y facturada como conceptos distintos.
- [ ] Diario de obra y partes de producción: actividad, fotos, incidencias, instrucciones y conformidad cuando corresponda.
- [ ] Certificaciones periódicas con histórico, acumulado, periodo actual y pendiente; evitar doble certificación/facturación. Mantener la facturación parcial existente.
- [ ] Cierre/reapertura con controles: partes y consumos pendientes, trabajos por certificar, incidencias, extras y saldos.
- [ ] Recepción/entrega, reservas del cliente, repasos, garantías y postventa.
- [ ] Estados coherentes entre obra, orden y fase, con transiciones autorizadas; no exigir que cerrar producción implique cobrar todo.

Aceptación: seguir una obra con varias órdenes/fases, una desviación, un extra y una certificación parcial hasta entrega; consultar qué falta por ejecutar, certificar, facturar y cobrar sin cálculos externos.

## 7. Paquete C — Empleados y contratistas

### C1. Personal propio — P1

- [ ] Calendarios, turnos, vacaciones/ausencias y capacidad disponible; alertas de solapamiento de planificación además del control actual de horas confirmadas.
- [ ] Entrada de parte sencilla desde móvil por trabajador, envío y aprobación por encargado.
- [ ] Rectificación de partes confirmados mediante reversión/ajuste trazable. La inmutabilidad actual protege el histórico, pero no resuelve un error humano.
- [ ] Coste laboral completo configurable: coste empresa, categorías, horas extraordinarias, desplazamientos y dietas, evitando imputaciones duplicadas.
- [ ] Informes por persona/obra/periodo y exportación a asesoría/nómina.
- [ ] Si se quiere cubrir registro laboral de jornada, diseñarlo expresamente: los partes de coste por fase no cubren por sí solos toda la jornada.

Aceptación: registrar una jornada repartida entre dos obras, aprobarla y corregir una imputación errónea conservando original, motivo y efecto en costes.

### C2. Subcontratas — P1

- [ ] Expediente por contratista y contrato: alcance, precios pactados, medición o precio cerrado, plazos y responsable.
- [ ] Pedidos/contratos de subcontratación con capítulos o partidas vinculados a la obra.
- [ ] Certificación/conformidad de trabajos, retenciones de garantía y anticipos cuando proceda.
- [ ] Facturas recibidas, abonos, vencimientos, pagos y conciliación con lo certificado.
- [ ] Mano de obra de contratista por horas como modalidad adicional, conservando lo existente.
- [ ] Evitar doble coste entre horas imputadas y factura del mismo trabajo; poder tratar una empresa con varios operarios sin solapamiento artificial de una única identidad horaria.
- [ ] Documentos y caducidades de coordinación preventiva y acreditaciones según aplicabilidad, con acceso restringido.

Aceptación: contratar 10.000 €, certificar una parte, registrar una factura con retención y un pago parcial; ver comprometido, ejecutado, facturado y pendiente por obra y contratista. Poder registrar trabajos simultáneos de distintas cuadrillas de la misma empresa.

Evidencia: WorkOrderContractorAssignment gestiona asignación y fechas, mientras LaborCostRate/WorkTimeEntry modelan coste horario por tercero. No se localiza contrato económico, certificación de compra o factura recibida.

## 8. Paquete D — Compras, almacén y costes

Prioridad P1 para gastos reales no cubiertos; P2 para automatización avanzada.

- [ ] Solicitud de material, comparativa/ofertas de proveedor y pedido aprobado.
- [ ] Recepciones parciales y relación pedido → albarán → factura recibida, con diferencias de cantidad y precio.
- [ ] Servicios, maquinaria/alquileres, transportes, dietas, tasas y otros gastos por obra/fase, además del material de stock.
- [ ] Facturas de compra y abonos con imputación/reparto entre obras; impuestos recuperables y no recuperables según configuración validada.
- [ ] Devoluciones parciales de consumo y devoluciones a proveedor: son operaciones distintas. Mantener trazabilidad al lote original.
- [ ] Existencias iniciales, regularizaciones/inventario, mermas y transferencias entre almacenes/obra.
- [ ] Stock mínimo, disponibilidad agregada, reservas y liberación/caducidad de reservas abandonadas.
- [ ] Cuadro económico: presupuesto base, revisado, comprometido, real, previsión final y margen; desglose por obra, capítulo y fase.
- [ ] Evitar duplicar el coste de materiales al registrar una factura de una recepción ya valorada.

Aceptación: compra con recepción parcial y descuento, consumo en dos obras, devolución, diferencia de factura y regularización de inventario; concilian cantidades, valoración y costes. El recargo del 15 % existente debe seguir identificado como recargo sobre coste, no como margen sobre venta.

## 9. Paquete E — Cobros, pagos y gestión económica

Prioridad P1 si se quiere operar sin hojas externas; integración válida si la tesorería reside en otra aplicación.

- [ ] Calendario real de vencimientos a partir de condiciones comerciales, con varios plazos por documento.
- [ ] Cobros/pagos parciales, saldo pendiente, método, fecha y referencia.
- [ ] Anticipos del cliente y a proveedores, aplicación posterior y devoluciones, evitando duplicar facturación/ingresos.
- [ ] Retenciones de garantía, liberación y vencimiento.
- [ ] Reclamaciones y alertas de impago; previsión de tesorería.
- [ ] Exportación contable y conciliación con asesoría o integración definida; separar ingresos, facturación y cobros.
- [ ] Informes de rentabilidad y flujo de caja por obra y consolidado.

Aceptación: una factura con dos vencimientos y un cobro parcial deja un saldo verificable; la rectificativa, el anticipo y la retención afectan correctamente a pendientes.

## 10. Paquete F — Documentación, normativa y facturación

Prioridad P1 para documentos; P0 antes de activar facturación real o accesos externos.

- [ ] Repositorio privado de planos, contratos, versiones, presupuestos firmados, fotos y albaranes originales.
- [ ] Clasificación, historial, descarga autorizada, límites de archivos y política de conservación. Una importación temporal no equivale a archivo documental.
- [ ] Documentos comerciales con emisor/cliente y contenido completo; PDF reproducible archivado al enviar/aceptar, además de imprimir desde navegador.
- [ ] Envío y registro de entrega por correo si se acuerda ese canal; portal de cliente/contratista como ampliación posterior.
- [ ] Subsanaciones, anulaciones y resolución de rechazos fiscales, con cadena e histórico inmutables; conservar separación de pruebas/producción.
- [ ] Declaración responsable y datos reales del productor/instalación; gestión y renovación de certificados.
- [ ] Validación integral con servicios oficiales de pruebas, contingencias, reintentos, duplicados y tratamiento de aceptaciones con errores.
- [ ] Determinar con asesoría la fiscalidad aplicable a cada operación y el alcance de factura electrónica/formatos que corresponda al negocio.
- [ ] Registro de jornada y conservación/acceso, si se incluye ese módulo, separados de los partes productivos.
- [ ] Protección de datos: finalidades y base jurídica, información a interesados, accesos, conservación, derechos y contratos con encargados.
- [ ] PRL/CAE/REA y documentación de subcontratación según actividad, tipo de interviniente y obra. Poder registrar vigencias y evidencias; no almacenar información médica innecesaria ni presentar un checklist como garantía legal.

Aceptación: la documentación de una obra puede recuperarse completa y con permisos; la persona que valida la salida fiscal puede demostrar los escenarios previstos y su evidencia. No habilitar producción simplemente cambiando un flag.

Referencias oficiales consultadas en esta revisión:

- La documentación oficial de [Filament sobre autorización](https://filamentphp.com/docs/5.x/advanced/security) explica que la ausencia de policies no deniega operaciones por defecto; es relevante para F04.
- La [AEPD: Facilita RGPD](https://www.aepd.es/guias-y-herramientas/herramientas/facilita-rgpd) ofrece apoyo a la responsabilidad activa y advierte que generar documentos no acredita cumplimiento automático.
- La [guía de registro de jornada del Ministerio de Trabajo](https://www.mites.gob.es/ficheros/ministerio/GuiaRegistroJornada.pdf) sirve de referencia para definir un módulo laboral distinto del coste por fase. Verificar normativa vigente y supuestos específicos al implementarlo.
- El [Registro de Empresas Acreditadas](https://expinterweb.mites.gob.es/rea/) explica su finalidad y las acreditaciones de contratistas/subcontratistas; comprobar las excepciones y aplicabilidad en cada caso.
- La [AEAT: declaración responsable del sistema de facturación](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/certificacion-sistemas-informaticos-declaracion-responsable.html) fundamenta su inclusión en la salida a producción.

No se fija aquí un calendario legal de obligatoriedad ni se declara cumplimiento fiscal/laboral: debe verificarse al activar cada flujo y para el titular concreto.

## 11. Paquete G — Seguridad, trazabilidad y operación

### G1. Seguridad y trazabilidad — P0/P1

- [ ] Completar F04, políticas y visibilidad de datos por obra; ocultar costes según rol.
- [ ] Auditoría de altas/cambios/borrados, aprobaciones, reversiones, cambios de precio y acceso a documentación sensible; quién, cuándo y motivo.
- [ ] Protección de documentos aceptados/confirmados; usar anulaciones y revisiones donde proceda, no borrado ordinario.
- [ ] Administración de cuentas, recuperación de acceso y segundo factor según exposición/riesgo.
- [ ] Verificar HTTPS, cookies/sesiones, modo debug, secretos y permisos en el servidor real sin divulgarlos.
- [ ] Exportaciones con los mismos permisos que las pantallas y con datos personales mínimos.

### G2. Operación y calidad — P0 antes de depender exclusivamente del sistema

- [ ] Repositorio Git y control de cambios: el directorio revisado no contiene .git. No se afirma que no exista un repositorio remoto o en otra ubicación.
- [ ] CI de pruebas y compilación; no se encuentra un workflow de CI en .github/workflows.
- [ ] Despliegue reproducible, entorno de preproducción, revisión de migraciones y procedimiento de recuperación.
- [ ] Copias automáticas cifradas de base, archivos y configuración necesaria; copia externa, retención y ensayo de restauración.
- [ ] Objetivos de pérdida máxima de datos y tiempo de recuperación acordados con el negocio.
- [ ] Supervisión de errores, tareas fallidas, espacio y certificados; alertas con un responsable.
- [ ] Verificar realmente cron/scheduler y workers supervisados. La programación en routes/console.php no acredita que el servidor la ejecute.
- [ ] Pruebas contra MySQL desechable para bloqueos/concurrencia, JSON, decimales e integridad. SQLite en memoria no valida todas esas diferencias.
- [ ] Pruebas de navegador de teclado/móvil y documentos, y de rendimiento con obras grandes. No añadir dependencias sin aprobación.
- [ ] Manual de alta inicial, importación de datos, recuperación y soporte; corregir el README técnico incompleto y su errata de ruta de certificado.
- [ ] Actualización y revisión periódica de dependencias y vulnerabilidades con versiones instaladas.

Aceptación: restaurar una copia en un entorno aislado y conciliar documentos/costes; desplegar una versión reproducible; ejecutar el recorrido de negocio sin pérdida de datos y demostrar alertas ante un fallo controlado.

## 12. Orden de ejecución y entregables

| Etapa | Entrega | Dependencias | Condición para darla por terminada |
| --- | --- | --- | --- |
| 0 | Cerrar alcance, roles y flujo económico; reproducir F01–F08 | Ninguna | Casos de uso y decisiones del apartado 13 acordados |
| 1 | Editor único, borrado coherente, totales, decimales y teclado | Etapa 0 | F01–F03/F07 resueltos con regresiones y prueba interactiva |
| 2 | Permisos, auditoría mínima y recuperación operativa | Etapa 0 | Acceso por obra probado y restauración ensayada |
| 3 | Oferta versionada, aceptación, extras y conversión a ejecución | Etapas 1–2 | Ciclo comercial completo sin duplicación |
| 4 | Planificación, partes con aprobación/corrección y avance | Etapas 2–3 | Producción y certificación conciliables |
| 5 | Contratos de subcontrata, compras/gastos y costes completos | Etapas 2–4 | Comprometido, real y previsión final por obra |
| 6 | Cobros/pagos, anticipos, garantías e integración contable | Etapas 3–5 | Saldos y tesorería verificables |
| 7 | Facturación real o integración validada | Etapas 2–3 y bloque fiscal | Emisión fiscal autorizada tras aceptación integral |
| 8 | Cierre, postventa, informes y aceptación final | Etapas anteriores | Escenarios del apartado 14 superados |

Los defectos F05/F06 pueden resolverse en las primeras etapas. La vía fiscal puede trabajarse antes si es un bloqueo comercial. No se asignan fechas ni presupuesto de desarrollo sin dimensionar usuarios, volumen, integraciones y variantes de negocio.

## 13. Decisiones de producto que faltan

El plan propone una base; estas decisiones deben resolverse al preparar las tareas, sin bloquear las correcciones evidentes:

1. ¿Una empresa y EUR como alcance definitivo? Actualmente la configuración es de una empresa; los costes y la facturación están restringidos a EUR aunque las obras/presupuestos tengan campo de divisa.
2. ¿Los empleados introducirán sus partes o lo hará un encargado? ¿Habrá acceso de cliente/contratista?
3. ¿Se trabaja por precio cerrado, medición ejecutada, horas/materiales o combinación? ¿Cómo se autorizan extras?
4. ¿Se emiten certificaciones periódicas y se aplican anticipos/retenciones de garantía?
5. ¿Facturación, contabilidad y nómina se harán aquí o en sistemas externos? Nombrar sistema y contrato de intercambio.
6. ¿Qué documentación preventiva requiere cada tipo de obra y quién la valida?
7. ¿Qué almacenes, ubicaciones y existencias iniciales reales deben incorporarse?
8. ¿Qué documento comercial se envía hoy al cliente, qué condiciones incluye y cómo se acepta?
9. ¿Cuántos usuarios, obras, líneas y partes se esperan? ¿Necesitan funcionar sin cobertura?
10. ¿Quién mantiene el sistema, atiende incidencias y valida restauraciones y obligaciones fiscales?

Ampliaciones P2 condicionadas a esas respuestas: modo sin conexión/PWA, firma con proveedor externo, portal de cliente, OCR de documentos escaneados, integración bancaria, exportación BC3 ampliada, maquinaria y mantenimiento, BIM/CAD, multiempresa y multidivisa completas. No son requisitos universales para la primera versión operativa.

## 14. Prueba de aceptación del software completo

- [ ] Crear cliente, obra y oferta sin mediciones; operar con teclado, corregir, borrar y guardar.
- [ ] Importar una medición compatible, revisar precios y generar una versión comercial sin alterar el original.
- [ ] Enviar/archivar, revisar y aceptar una oferta; transformarla en ejecución una sola vez.
- [ ] Planificar fases y personas; detectar ausencia o conflicto y reasignar.
- [ ] Recibir material, reservarlo y consumirlo con coste exacto; devolver una parte sin perder trazabilidad.
- [ ] Registrar y aprobar horas; corregir un error confirmado con ajuste, no con modificación oculta.
- [ ] Contratar una subcontrata por importe/medición, certificarla, registrar su factura y pago parcial.
- [ ] Gestionar un extra aceptado y visualizar su efecto sobre plazo, venta y coste.
- [ ] Certificar y facturar parcialmente sin duplicar cantidades ni cambiar el precio aceptado sin autorización.
- [ ] Registrar anticipo, cobro/pago parcial y retención, y conciliar saldos.
- [ ] Mostrar coste previsto/comprometido/real, previsión final y margen por obra.
- [ ] Entregar obra, gestionar repasos y garantía, cerrar y reabrir con autorización si procede.
- [ ] Imprimir/archivar documentos completos, consistentes y apropiados para cada audiencia.
- [ ] Impedir accesos cruzados y recuperar íntegramente el sistema desde una copia.
- [ ] Validar el flujo fiscal real o la integración externa antes de facturar en producción.

La aplicación podrá considerarse completa para el alcance acordado cuando estos escenarios aplicables tengan responsable de aceptación, evidencia y resultado correcto. No basta con disponer de todas las pantallas.

## 15. Verificación de esta revisión

Suite completa ejecutada con php artisan test --compact y la configuración forzada de SQLite :memory: de phpunit.xml: **372 pruebas correctas, 3.526 aserciones, 75,2 segundos**. No se añadieron pruebas ni se modificó código funcional durante esta revisión.

Este resultado confirma los escenarios ya cubiertos, no prueba las funcionalidades ausentes ni ejecuta los manejadores de teclado del navegador. La matriz y los hallazgos incluyen análisis estático; F01/F02 se deducen del flujo de carga/guardado y no se presentan como una nueva reproducción automatizada.

Esta revisión solo genera PLAN.md y actualiza PROYECTO.md; no corrige silenciosamente las brechas detectadas.

## 16. Seguimiento de implementación — 5 de noviembre de 2026

Se confirma una empresa/EUR, partes por empleados con aprobación, contabilidad externa/CSV y líneas obligatorias en pedidos y facturas recibidas.

| Bloque | Entregado | Pendiente para cerrar el bloque |
| --- | --- | --- |
| F01–F03 | Guardado único atómico, estado manual sin duplicar, total previo, huella de conflicto y aviso de salida | Validación interactiva de navegador |
| F04 | Perfiles, cuentas, ámbito por obra, selectores y descargas/exportaciones; operario sin autoaprobación | Matriz ampliada, segundo factor y revisión integral de seguridad |
| F05–F06 | Albarán neto con descuento; cabecera comercial/condiciones y orden con instrucciones; impresión de compras | PDF archivado automáticamente, diseño final y evidencia histórica completa en todos los documentos |
| F07–F08 | Coma decimal, filas vacías, devolución parcial, fechas laborales y ausencias al confirmar horas | Recorrido real de teclado/móvil, calendario y reglas completas de reapertura |
| A | Oferta desde obra, envío registrado, aceptación con evidencia, revisión, extra, ejecución idempotente con precio y descuento protegidos | Fiscalidad mixta, referencias configurables, biblioteca y comparación visual |
| B | Avance por partida, diario/hitos/incidencias/repasos/garantías y comprobaciones de cierre | Planificación con dependencias/capacidad y certificación comercial independiente |
| C | Ausencias, aprobación de partes, reversión confirmada, contratos y certificación económica de subcontratas | Coste laboral completo, cuadrillas, calendario avanzado y documentación preventiva específica |
| D | Pedidos/facturas con líneas y totales, recepciones parciales vinculadas, gastos, devoluciones parciales, ajustes y traslados valorados | Abonos, reparto entre obras, conciliación automática, inventario inicial y mínimos de stock |
| E | Movimientos parciales, anticipos/aplicación/devolución, plazos, exportaciones y caja neta | Conciliación por plazo, retenciones liberables y previsión final/tesorería completa |
| F | Documentos privados/versiones/caducidad y descargas autorizadas | Envío/archivo PDF automático, obligaciones específicas y flujo fiscal real |
| G | Auditoría básica, copia cifrada, verificador/ensayo aislado, diagnóstico, Git y CI SQLite/build | Copia externa, prueba real de restauración/MySQL, CI remoto, HTTPS/entorno y supervisión de servicios |

Los modelos y pantallas se han aplicado con migraciones aditivas y copias previas. La cuenta MySQL no permite crear la base aislada del ensayo de restauración; se verificó integridad/descifrado sin sustituir ese ensayo. La configuración real sigue de desarrollo y no se ha inventado un dominio HTTPS.

El dashboard explica la operativa entregada y señala sus límites. PROYECTO.md contiene decisiones, tablas y recorridos. **Este plan sigue abierto:** no se presenta este bloque como el 100 % de lo solicitado.

Validación final de este bloque: **393 pruebas correctas y 3.688 aserciones**, suite completa sobre SQLite :memory:, Pint y compilación Vite correctos. La prueba de restauración sigue pendiente por permisos; tampoco se ha realizado todavía el recorrido interactivo de teclado/móvil ni la validación integral MySQL concurrente. El cumplimiento de las casillas de aceptación del apartado 14 debe verificarse individualmente; no se marcan todas como terminadas por disponer ya de pantallas.
