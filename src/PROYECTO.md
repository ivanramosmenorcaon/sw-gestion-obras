# Gestión de obras y contratistas — decisiones del proyecto

Documento vivo. Se actualizará con cada bloque implementado y con cada decisión aceptada.
Fecha inicial: 3 de noviembre de 2026.

## 1. Alcance confirmado

- Aplicación Laravel con Filament para una sola empresa por instalación.
- Actividad en territorio nacional; empresa fuera del SII.
- Gestión de presupuestos, proyectos, órdenes, fases, materiales, horas, subcontratación, compras, albaranes y facturación.
- Cobro combinando certificaciones, anticipos, horas/materiales y liquidación final.
- Adaptable a distintos oficios sin codificar un oficio concreto.
- Desarrollo por etapas: primero migraciones y seeders; después modelos; finalmente resources y relation managers de Filament.
- El usuario solicita mantener este documento con las decisiones y el estado del proyecto.

## 2. Estado de implementación

| Bloque | Estado |
| --- | --- |
| Preparación de Laravel Boost | Instalado |
| Maestros: empresa, terceros, pagos, unidades, productos y tarifas | 17 tablas aplicadas y catálogos cargados; incidencia de pruebas descrita al final |
| Proyectos, capítulos, órdenes, fases y asignaciones | Ocho tablas aplicadas en MySQL (lote 2); decisiones y validación en el apartado 11 |
| Presupuestos versionados, compras, albaranes y conciliación | Propuesto; pendiente |
| Stock, consumos, costes y horas | Propuesto; pendiente |
| Certificaciones, proformas, facturas, anticipos y tesorería | Propuesto; pendiente |
| Integración VERI*FACTU y facturación electrónica | Pendiente |
| Modelos y Filament | 27 modelos, 25 resources y 18 relation managers; interfaz en español. Véanse apartados 12 y 13. |
| Parametrización fiscal | Dos tablas de catálogos, identificación y países oficiales, perfiles y retenciones de compra/venta en terceros/productos/empresa. Véase apartado 13. |

Versiones comprobadas: PHP 8.3.36, Laravel 13.39.0, Filament 5.11.1 y Boost 2.10.4.
Las versiones mínimas de composer.json no se deben confundir con las versiones instaladas.

## 3. Decisiones generales

### Una empresa

Se utiliza company_settings sin company_id en las tablas de negocio. Su columna scope solo admite company y es única, de modo que la base de datos permite como máximo un registro. No se introduce información fiscal ficticia.
Ofrecer la aplicación mediante instalaciones independientes es compatible con este diseño. Un SaaS compartido entre empresas requerirá una evolución explícita de esquema, autorización y aislamiento.

### Nombres y precisión

Tablas y columnas en inglés siguiendo las convenciones de Laravel. Los textos de interfaz serán traducibles al español.
Todas las tablas de este bloque tienen id y timestamps.
Cantidades: DECIMAL(20,6); precios unitarios: DECIMAL(20,6); factores de conversión: DECIMAL(24,12); porcentajes: DECIMAL(7,4).
No usar float para cálculos económicos. La precisión de visualización de una unidad no modifica la precisión almacenada.
La política de redondeo fiscal y monetario se definirá con el módulo de facturación.
Los códigos de país y moneda se guardan como ISO de dos y tres caracteres, respectivamente; validación pendiente en la aplicación.
Valores iniciales: España, EUR, idioma es y zona Europe/Madrid.

### Integridad y conservación

Referencias mediante claves foráneas con borrado restringido. Los maestros se desactivan mediante is_active cuando corresponda.
No hay borrados en cascada que eliminen datos comerciales accidentalmente.
El rollback elimina las tablas del bloque en orden inverso de dependencias: es destructivo sobre sus datos y solo debe utilizarse en entornos desechables. En producción se crearán migraciones correctoras.
El bloque se agrupa en una migración porque constituye una primera instalación coherente de maestros. Una vez aplicada en un entorno compartido, no se editará.

## 4. Modelo del primer bloque

### Identidad y contactos

- company_settings: identidad, domicilio fiscal, moneda, zona horaria y contacto de la empresa emisora.
- parties: identidad única de cada tercero, persona física o entidad. Se admite identificación fiscal vacía durante la captación comercial; será obligatoria cuando la normativa del documento lo exija.
- La combinación país fiscal + tipo + número fiscal es única cuando el número está informado. La normalización de mayúsculas, espacios y prefijos se implementará antes de guardar.
- party_roles admite customer, supplier y subcontractor. Una misma identidad puede tener varios roles, sin repetir el mismo.
- party_addresses permite que una dirección sea fiscal y de entrega simultáneamente. No se presupone que cada obra sea una dirección maestra: el proyecto conservará su propia ubicación.
- party_contacts incluye cargo, correo, teléfono, indicador de contacto principal y estado.
- party_customer_settings y party_supplier_settings son extensiones de uno a uno con condiciones comerciales separadas.
- El rol de proveedor/subcontratista y su coherencia con estas extensiones se validarán en la aplicación.

### Condiciones de pago

payment_terms identifica la condición por un código estable.
payment_term_installments contiene los tramos ordenados, porcentaje, evento de referencia y días posteriores.
Eventos admitidos: invoice_date, work_start y work_completion.
Una condición 30 % al inicio / 70 % al finalizar no presupone que ambas fechas sean la fecha de factura.
Estas condiciones son plantillas comerciales, no un calendario fiscal de emisión. La aplicación resolverá eventos y generará vencimientos concretos cuando corresponda, sin alterar documentos anteriores si cambia la plantilla.
Pendiente: comprobar suma exacta del 100 %, porcentajes positivos y reparto del último céntimo, y definir emisión de anticipos.

### Unidades y productos

units distingue count, time, mass, length, area y volume.
unit_conversions expresa cantidad_destino = cantidad_origen × factor. No incluye identidades ni conversiones entre magnitudes diferentes.
Los factores inversos periódicos, como minuto a hora, se almacenan aproximados a 12 decimales; deberá controlarse el redondeo al convertir.
products distingue material, service, labor y rental. El control de stock es independiente del tipo.
product_categories permite jerarquía; se evitarán ciclos en la fase de aplicación.
product_units representa las unidades/presentaciones disponibles para cada producto:
- unit_id identifica la unidad física del contenido.
- base_unit_factor indica cuántas unidades base del producto contiene una unidad de esta presentación.
- Ejemplo: producto con base ud, presentación caja20, unit_id ud, factor 20.
- Ejemplo: producto con base g, presentación kilogramo, unit_id kg, factor 1000.
- La presentación base tendrá factor 1; su creación automática corresponderá a la fase de modelos.
- Los campos available_for_purchase y available_for_sale delimitan su uso.
- Se validarán factores positivos y compatibilidad física; una conversión entre m² y m³ exige una medición y un espesor, no una equivalencia global.

supplier_products vincula tercero proveedor, producto y presentación obligatoria, referencia externa y cantidad mínima.
No contiene un supuesto coste histórico: los presupuestos recibidos y documentos de compra conservarán sus precios.
La referencia externa es única por proveedor, permitiendo varias presentaciones si tienen referencias distintas.

### Tarifas

price_lists incluye código, nombre, moneda, vigencia opcional y estado.
price_list_items contiene producto, presentación, cantidad mínima, precio por presentación y vigencia.
Una tarifa específica se puede asignar a un cliente; inicialmente varios clientes pueden compartirla.
Las claves foráneas compuestas impiden asociar a un producto una presentación perteneciente a otro, tanto en tarifas como en referencias de proveedor.
La fecha inicial del precio es obligatoria. La combinación tarifa + presentación + cantidad mínima + fecha inicial es única.
Pendiente en aplicación: validar rangos y solapamientos; seleccionar el tramo de cantidad más alto aplicable; exigir compatibilidad entre vigencia de línea y cabecera.
Los precios de tarifa se consideran antes de impuestos. El tratamiento fiscal pertenece al documento y al módulo tributario futuro.
Cada documento guardará su propia descripción, unidad, factor, precio, descuentos y tratamiento fiscal históricos.

## 5. Seeders

BusinessMasterSeeder se ejecuta desde DatabaseSeeder y utiliza Query Builder, sin anticipar la creación de modelos.
La carga es transaccional y está prevista para ejecución administrativa secuencial, no concurrente.
Incluye:
- 11 unidades: ud, h, min, g, kg, mm, cm, m, m², m³ y l.
- 12 conversiones dirigidas: tiempo, masa, longitud y volumen.
- 5 condiciones: contado, 30 días, 60 días, 50 % a 30/60 días y 30 % inicio/70 % finalización.
No crea empresa, terceros, productos ficticios, tarifas ni usuarios.
Se ha retirado la creación del usuario de ejemplo del seeder inicial de Laravel; no se borra ningún usuario existente.
Al repetir la carga no se sobrescriben unidades, conversiones ni condiciones existentes. Una condición existente se conserva completa, incluso si un administrador ha cambiado sus tramos.
Los seeders no reparan catálogos modificados: los cambios posteriores exigirán acciones explícitas.

## 6. Diseño previsto para los siguientes bloques

Estas decisiones orientan el diseño. El núcleo de proyectos y ejecución ya existe (apartado 11); los documentos, costes reales y facturación siguen pendientes.

1. Proyecto → capítulos económicos y órdenes de trabajo → fases de ejecución. Capítulo económico y fase son conceptos distintos.
2. Asignación de personal propio y contratistas; costes horarios con vigencia y coste aplicado conservado en cada parte.
3. Presupuestos al cliente con versiones y ampliaciones aceptadas, presupuestos recibidos, pedidos, entregas y devoluciones.
4. Conciliación entre líneas con cantidades e importes: admite recepciones y facturaciones parciales, agrupación de albaranes y discrepancias.
5. Stock mediante movimientos y reservas. Compra, recepción, consumo y factura son hechos distintos.
6. Costes previstos, comprometidos y reales separados, con reglas explícitas para impedir doble cómputo.
7. Certificaciones de avance independientes de proformas y facturas.
8. Facturas de compra y venta separadas; relaciones de rectificación, series, desgloses fiscales, vencimientos, anticipos y aplicaciones de cobros/pagos.
9. Una factura puede incluir varias órdenes o proyectos mediante líneas y repartos.
10. Retenciones fiscales y retenciones de garantía de obra se tratarán por separado.
11. Descomposición de partidas en materiales y trabajo, mediciones y dimensiones: pendiente de concretar.

## 7. Facturación española y VERI*FACTU

Territorio nacional no equivale a un único tratamiento tributario. Antes del módulo fiscal se concretará si hay operaciones en Canarias, Ceuta/Melilla o territorios forales, y las obligaciones aplicables.
La empresa ha confirmado que está fuera del SII.
El modelo fiscal deberá contemplar impuestos por línea, exenciones, no sujeción, inversión del sujeto pasivo, rectificativas y conservación de los datos fiscales históricos.
Las facturas emitidas no se editarán ni borrarán como documentos en borrador.
Se distinguirán rectificación de factura, anulación de registro de facturación y subsanación.
VERI*FACTU requiere registros de alta/anulación, encadenamiento, huellas, identificación del sistema, QR, envíos, respuestas y reintentos. La cadena no se separará por proyecto ni por serie.
La factura electrónica entre empresas tiene requisitos propios y se estudiará separadamente.
Este bloque de maestros NO implementa cumplimiento fiscal ni conexión con AEAT. No se fijan calendarios normativos sin una nueva verificación oficial al implementar el módulo.

Fuentes oficiales consultadas en la fase de diseño:
- [Reglamento de facturación, RD 1619/2012](https://www.boe.es/eli/es/rd/2012/11/30/1619).
- [Orden HAC/1177/2024](https://www.boe.es/buscar/act.php?id=BOE-A-2024-22138).
- [AEAT: SIF y VERI*FACTU](https://www3.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html).
- [AEAT: trazabilidad](https://www3.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/caracteristicas-requisitos-sif-trazabilidad.html).

## 8. Validaciones pendientes de la capa de aplicación

Las claves foráneas, unicidad y dominios enum protegen parte del esquema. Este listado recoge los pendientes iniciales; el apartado 12 detalla las validaciones ya implementadas al adelantar los modelos y Filament:
- Normalización y validación fiscal según país y documento.
- Una dirección fiscal y un contacto principal efectivos por tercero.
- Coherencia entre roles y extensiones comerciales.
- Suma de porcentajes, importes/cantidades válidos y eventos de vencimiento.
- Conversiones positivas, de la misma magnitud y sin autoequivalencias.
- Ausencia de ciclos en categorías.
- Coherencia de unidades, presentaciones y unidades base; restricciones al cambiarlas tras tener movimientos.
- Tarifas sin solapamientos y selección determinista de precios.
Estas reglas no se presentan como ya resueltas por las migraciones.

## 9. Validación y operación

Pruebas automatizadas en tests/Feature/BusinessMasterDataTest.php, con SQLite en memoria:
- Repetición de seeders conservando personalizaciones y sin crear usuarios ni datos fiscales.
- Porcentajes y eventos de las condiciones iniciales.
- Compatibilidad de magnitudes de las conversiones iniciales.
- Roles múltiples en una identidad.
- Rechazo de precios con presentación de otro producto.
- Protección de unidades utilizadas y configuración de empresa única.

Antes de aplicar migraciones sobre una base real, revisar su estado y realizar la copia de seguridad correspondiente.
Comandos de instalación del bloque: php artisan migrate y php artisan db:seed --class=BusinessMasterSeeder.
Pruebas: php artisan test --compact tests/Feature/BusinessMasterDataTest.php.
Resultado: 7 pruebas correctas (39 aserciones) con SQLite en memoria y comprobación previa de la conexión; formato PHP verificado con Pint sobre los archivos modificados. No hay repositorio Git, por lo que Pint --dirty no funciona y se han indicado las rutas explícitamente.

### Incidencia de aislamiento de pruebas (2026-11-03)

La primera ejecución de las pruebas utilizó por error la conexión MySQL gestion_obras del entorno. Las variables DB_CONNECTION y DB_DATABASE del proceso prevalecieron sobre los valores env de phpunit.xml. LazilyRefreshDatabase ejecutó migrate:fresh contra esa base y reinicializó sus tablas, incluyendo usuarios y notificaciones. La nueva migración quedó aplicada en MySQL en ese proceso. No se había realizado una copia previa y no se conoce el contenido anterior; no se puede afirmar que no se hayan perdido datos. Se informó al usuario. No se recrean cuentas ni se inventan datos para ocultar el resultado.

Corrección: phpunit.xml fuerza DB_CONNECTION=sqlite, DB_DATABASE=:memory: y DB_URL vacío tanto en server como en env. Además, beforeRefreshingDatabase comprueba conexión y ruta antes de permitir que las pruebas de este bloque refresquen la base. Se verificó que esa protección bloquea MySQL y posteriormente que las pruebas pasan sobre SQLite en memoria. La recuperación de cualquier dato previo requiere una copia externa o mecanismos de recuperación de MySQL, si existen; no está resuelta por este cambio.

## 10. Historial

- 2026-11-03: confirmados una empresa, territorio nacional, fuera del SII y sistemas de cobro combinados.
- 2026-11-03: creado el bloque de 17 tablas de maestros, seeder sin datos de negocio ficticios y pruebas de integridad. Modelos y Filament quedan para fases posteriores.
- 2026-11-03: verificado formato, 7 pruebas con 39 aserciones en SQLite en memoria y carga de BusinessMasterSeeder en MySQL. Todas las migraciones figuran aplicadas. Documentada la reinicialización accidental de MySQL y corregido el aislamiento de pruebas; recuperación de datos previos sin resolver.
- 2026-11-03: segundo bloque aplicado tras copia privada previa: ocho tablas de proyectos y ejecución, 25 tablas de negocio acumuladas. Verificadas 18 pruebas y 77 aserciones en memoria, formato PHP y esquema resultante en MySQL. Siguiente bloque propuesto: presupuestos versionados de venta y presupuestos recibidos.
- 2026-11-03: a petición del usuario se adelantan modelos y Filament para los dos bloques existentes: 25 modelos/factories, 23 resources, 18 gestores de relaciones, cinco grupos de navegación y traducciones españolas. Sin migraciones nuevas ni carga de datos en MySQL.
- 2026-11-03: ampliada la parametrización fiscal tras contrastar AEAT y BOE: países e identificación, situación fiscal, tipos con vigencia, perfiles y retenciones independientes de compra/venta. Dos migraciones nuevas aplicadas como lote 3, copia previa y carga inicial sin asignación automática a datos existentes.
- 2026-11-03: alta de fichas desde todos los selectores de relaciones gestionadas por resources. Se centraliza en RecordSelect y se registra como convención permanente en AGENTS.md.

## 11. Segundo bloque: proyectos y ejecución

### Alcance y tablas

Se crean ocho tablas en la migración create_project_execution_tables. Este bloque no crea modelos, pantallas ni datos de obras ficticios. No necesita un seeder nuevo: no introduce catálogos configurables ni datos iniciales obligatorios.

| Tabla | Contenido |
| --- | --- |
| projects | Referencia única global, nombre, descripción, cliente obligatorio, gestor de usuario opcional, estado, moneda, ubicación propia, fechas previstas/reales y notas. |
| project_chapters | Proyecto, padre opcional, código único dentro de la obra, nombre, descripción, posición y estado activo. |
| employees | Código único, nombre, usuario opcional único, contacto, puesto, fechas de vinculación laboral y estado activo. |
| work_orders | Proyecto obligatorio, referencia única global, nombre, descripción, encargado empleado opcional, estado, prioridad, fechas previstas/reales e instrucciones. |
| work_order_phases | Orden, código único dentro de ella, nombre, descripción, posición, estado y fechas previstas/reales. |
| work_order_lines | Orden y proyecto, capítulo/fase opcionales, posición única en la orden, tipo, producto/presentación opcionales, descripción, unidades y factor conservados, cantidad prevista, coste y precio de venta unitarios estimados y notas. |
| work_order_employee_assignments | Orden, fase opcional, empleado, función, estado, fechas previstas, horas previstas y notas. |
| work_order_contractor_assignments | Orden, fase opcional, tercero contratista, alcance del trabajo, estado, fechas previstas y notas. |

### Capítulos y fases

Los capítulos organizan el presupuesto y el control económico del proyecto; las fases organizan la ejecución de cada orden.
Una orden no tiene un único capítulo obligatorio: sus líneas pueden abarcar varios capítulos de la misma obra.
Los capítulos admiten subcapítulos; su padre debe pertenecer al mismo proyecto por clave foránea compuesta.
La fase de una línea o asignación debe pertenecer a su orden por clave foránea compuesta.
Una línea declara project_id para que la base garantice que su orden y capítulo pertenecen al mismo proyecto. No es un identificador de empresa.
El capítulo y la fase pueden quedar vacíos durante la planificación inicial.

### Personas y asignaciones

Un empleado puede trabajar sin tener cuenta de usuario. Un usuario no se vincula a varios empleados.
El gestor del proyecto es un usuario; el encargado de una orden es un empleado. No se presupone que ambos sean la misma persona.
Se eligen dos tablas de asignaciones, en lugar de una relación polimórfica o dos destinos opcionales: así cada destino tiene una clave foránea real y no hay combinaciones ambiguas.
Una fase vacía significa asignación general a la orden.
Se admiten varias asignaciones de la misma persona/contratista a una orden para distintos períodos o alcances. No se impone una unicidad que impida una segunda intervención.
Las asignaciones operativas no constituyen un contrato ni generan automáticamente costes, compras o facturas. Los acuerdos e importes comprometidos se guardarán en el futuro módulo de subcontratación.
Las horas previstas de una asignación sirven para planificar personal; no se sumarán como otro coste adicional a las partidas de mano de obra.
La asignación de un contratista a una obra se realiza a través de sus órdenes.

### Partidas y estimaciones

Se permiten líneas vinculadas al catálogo y líneas libres con descripción obligatoria.
Tipos: material, service, labor y rental. En la fase de aplicación se validará su coherencia con el producto.
Cada línea conserva su unidad física, etiqueta comercial, unidad base y factor. La cantidad base prevista se obtiene como planned_quantity × base_unit_factor.
Ejemplo: 2,5 rollos de 20 metros equivalen a 50 metros; el precio y coste unitarios se expresan por rollo.
En líneas libres se indicarán también unidades explícitas; cuando no exista conversión, la unidad base coincide y el factor es 1.
La clave compuesta de producto/presentación impide usar la presentación de otro producto cuando se informan ambos. Además, la presentación debe existir por una clave foránea directa.
El hecho de informar presentación obliga funcionalmente a informar producto; esta condición y la coherencia con las unidades quedan pendientes de validación en la aplicación.
Los costes y precios estimados se expresan en la moneda del proyecto y antes de impuestos. No se añaden sumas almacenadas ni un porcentaje de avance sin fuente.
Coste previsto de línea: cantidad prevista × coste unitario estimado. Venta estimada: cantidad prevista × precio unitario estimado.
Estos valores no sustituyen un presupuesto aceptado, una certificación, un coste real ni una factura.
La relación con versiones de presupuestos se incorporará al diseñar ese módulo, sin anticipar claves a tablas inexistentes.
Los factores, unidades y precios guardados no se sincronizarán automáticamente al cambiar el catálogo.

### Estados y fechas

- Proyecto: draft, quoted, approved, in_progress, on_hold, completed, cancelled. Inicial: draft.
- Orden: draft, planned, in_progress, on_hold, completed, cancelled. Inicial: draft.
- Fase: pending, in_progress, on_hold, completed, cancelled. Inicial: pending.
- Asignación: planned, active, completed, cancelled. Inicial: planned.
- Prioridad de orden: low, normal, high, urgent. Inicial: normal.
- Se guardan fechas, no horas de agenda, para planificación general. El registro horario se diseñará en su bloque.
- Los estados son dominios iniciales, independientes del oficio; las transiciones y permisos todavía no están implementados.
- Se utilizarán referencias globales únicas para localizar proyecto/orden desde documentos externos. La generación de numeración concurrente se implementará con la capa de aplicación.

### Integridad y consultas

Se mantiene borrado restringido en todas las relaciones: no se eliminan en cascada fases, líneas ni asignaciones.
Se añaden índices para listar obras por cliente/estado, órdenes por proyecto/estado, planificación por estado/fecha, capítulos por padre/posición y asignaciones por persona/fecha.
Las claves únicas compuestas con id permiten referenciar explícitamente pertenencia a proyecto u orden.
El rollback elimina solo las ocho tablas nuevas en orden inverso; destruye sus datos y no debe usarse como operación ordinaria en producción.
No se modifica la migración ya aplicada del primer bloque.

### Validaciones pendientes de modelos y aplicación

- Fechas finales no anteriores a las iniciales y coherencia de fechas reales con estados.
- Clientes con rol customer y contratistas con rol subcontractor; personal y terceros activos al asignar.
- Ausencia de ciclos entre capítulos, incluida la autorreferencia.
- Cantidades/horas/costes/precios no negativos y factores estrictamente positivos.
- Compatibilidad de unidades y presentaciones, y producto obligatorio cuando se indica presentación.
- Resolución de conflictos de agenda y coherencia de asignaciones con los períodos de las órdenes.
- Moneda válida y conversión explícita de compras en otra divisa, en su módulo.
- Reglas de modificación, aprobación y conservación del historial tras iniciar ejecución.
- Costes horarios con vigencia, partes de horas, consumos y coste real: pendientes del bloque de costes.

### Verificación y aplicación

Se han utilizado las habilidades laravel-best-practices y testing-best-practices.
Boost MCP no está disponible en esta sesión; se han inspeccionado las tablas existentes con Artisan y las APIs en el framework instalado.
Pruebas nuevas: ProjectExecutionSchemaTest, con comprobación de conexión SQLite y base :memory: antes de cualquier refresco.
Cubren líneas de catálogo/libres, varios capítulos en una orden, rechazo de cruces de obra/orden/presentación, personal sin usuario, contratistas, códigos de capítulo y protección de borrado.
Se ejecutan también las pruebas del primer bloque para comprobar compatibilidad.
Antes de migrar MySQL se ha creado una copia con mysqldump --single-transaction, sin exponer credenciales, con permisos de archivo restringidos:
storage/app/private/backups/before_project_execution_20261103_155149.sql (40.271 bytes).
Esta copia conserva el estado anterior al segundo bloque; no recupera el estado previo a la incidencia del primero.
Estado final: migración aplicada correctamente en MySQL como lote 2; comprobadas las claves foráneas e índices de work_order_lines con Artisan. Formato PHP correcto. Ambos archivos de pruebas pasan con 18 pruebas y 77 aserciones en SQLite en memoria (11 pruebas del segundo bloque y 7 del primero). Pint --dirty se intentó según AGENTS.md; al no existir repositorio Git, se formatearon explícitamente los dos archivos PHP del bloque. No se ejecutó migrate:fresh ni rollback sobre MySQL.

## 12. Panel Filament en español, modelos y relaciones

### Cambio de orden solicitado

El usuario solicita adelantar la interfaz de los dos bloques existentes. Se crean ahora sus modelos y pantallas, antes de continuar con presupuestos, compras y facturación. No se han creado tablas nuevas ni alterado los registros de MySQL en esta etapa.

Se han leído filament-development, laravel-best-practices y testing-best-practices, y consultado la documentación oficial de Filament 5.11.1. Se han utilizado los comandos make:model, make:filament-resource y make:filament-relation-manager.

### Modelos y factories

Hay 25 modelos de negocio, uno por cada tabla existente, con:
- Asignación masiva explícita mediante el atributo Fillable.
- Relaciones belongsTo, hasMany y hasOne con claves explícitas y tipos de retorno.
- Casts booleanos, de fechas y decimales. Los importes y factores se devuelven como cadenas decimales.
- Valores iniciales coherentes con las migraciones.
- Validación al guardar y protección de borrado cuando hay relaciones dependientes.
- Una factory útil por modelo. PartyFactory dispone de estados customer, supplier y contractor que crean el rol correspondiente.

No se añaden seeders vacíos por modelo ni se cargan datos de prueba en la aplicación. BusinessMasterSeeder sigue siendo el punto de entrada de los catálogos iniciales.

Al crear un producto mediante Eloquent se crea automáticamente una presentación base: código base, unidad del producto y factor 1. No se puede borrar esta presentación. La unidad base del producto queda protegida frente a cambios mientras existan presentaciones; actualmente la presentación automática activa esta protección desde el alta. Esta decisión evita reinterpretar cantidades existentes; cualquier futura operación de cambio de unidad deberá diseñarse explícitamente.

### Navegación

Se registran 23 resources: diez visibles en el menú y trece auxiliares con rutas propias, utilizados también por las relaciones. Las dos tablas de condiciones comerciales de terceros se editan como relaciones hasOne dentro del formulario del tercero, por lo que no necesitan resources separados.

| Grupo | Entradas visibles |
| --- | --- |
| Obras | Proyectos; Órdenes de trabajo |
| Contactos | Clientes, proveedores y contratistas |
| Catálogo | Productos y servicios; Categorías; Tarifas; Unidades de medida |
| Equipo | Empleados |
| Configuración | Empresa; Condiciones de pago |

No se añaden entradas de presupuestos, facturas o almacenes hasta implementar sus tablas y comportamiento.

### Relation managers

Se crean 18 gestores registrados en sus resources:

| Registro padre | Gestores |
| --- | --- |
| Tercero | Roles; Direcciones; Personas de contacto; Referencias de proveedores; Proyectos |
| Producto | Presentaciones; Referencias de proveedores; Precios |
| Unidad | Conversiones desde esa unidad |
| Condición de pago | Tramos de pago |
| Tarifa | Precios |
| Proyecto | Capítulos; Órdenes de trabajo |
| Orden de trabajo | Fases; Partidas; Personal asignado; Contratistas asignados |
| Empleado | Asignaciones a órdenes |

Los formularios y tablas se comparten entre los resources auxiliares y los relation managers, evitando dos definiciones distintas para el mismo dato. En un relation manager la relación propietaria se introduce por contexto; en las partidas de una orden también se proporciona el proyecto de esa orden.

Las relaciones de una sola fila customerSettings y supplierSettings están integradas mediante secciones del formulario del tercero. Cada una mantiene sus condiciones de pago y referencias; la del cliente permite asignar una tarifa.

### Flujo básico de trabajo

1. Configurar los datos de la empresa.
2. Crear un tercero y añadir sus roles desde la pestaña Roles.
3. Crear las unidades, categorías y productos necesarios; cada producto recibe su presentación base.
4. Crear un proyecto seleccionando un tercero con rol de cliente.
5. Añadir capítulos y órdenes desde el proyecto.
6. Dentro de una orden, añadir fases, partidas y asignaciones.
7. Para asignar un contratista, el tercero debe tener el rol de subcontratista. Para una referencia de compra, debe tener el rol de proveedor.
8. Configurar tarifas y condiciones comerciales cuando se necesiten.

Las asignaciones y partidas siguen siendo planificación; no crean automáticamente consumos, costes reales ni facturas.

### Idioma y traducciones

El idioma principal y el de respaldo son es. Se actualizan los valores por defecto de config/app.php y las claves APP_LOCALE, APP_FALLBACK_LOCALE y APP_FAKER_LOCALE de .env y .env.example. Faker utiliza es_ES.

Archivos:
- lang/es/business.php: grupos, nombres singulares/plurales, campos, estados, opciones, ayudas y errores de negocio.
- lang/es/validation.php: mensajes de validación utilizados por los formularios.
- lang/es/auth.php: errores de autenticación.

Los labels de campos, columnas, filtros, pestañas de relación y nombres de recursos utilizan claves de traducción. Los botones, navegación de páginas, búsquedas, paginación y avisos estándar aprovechan las traducciones españolas incluidas en Filament.
Los valores internos de estados y enums siguen en inglés: solo se traduce su presentación. El contenido introducido por los usuarios no se trata como clave de traducción.
Fechas visibles: d/m/Y. Los selectores de fecha usan el componente de Filament con localización de la aplicación. Los números de las tablas utilizan formato español.
Se podrán incorporar otros idiomas añadiendo sus archivos equivalentes, sin renombrar columnas ni enums.

### Validación implementada

BusinessRecordRules define reglas explícitas de los campos, unicidades y relaciones dependientes. BusinessRecordValidator centraliza las comprobaciones antes de guardar; los errores se asignan a la ruta del campo correspondiente en páginas y modales.

Se implementan:
- Campos obligatorios, longitudes, valores enum, existencia de referencias y unicidades simples/compuestas.
- Normalización de país/moneda a mayúsculas y eliminación de espacios en la identificación fiscal.
- Formato de país/moneda, correo, zona horaria, precisión y límites decimales, cantidades no negativas y factores positivos.
- Coherencia de fechas finales respecto de fechas iniciales informadas.
- Roles de cliente/proveedor/subcontratista al realizar nuevas vinculaciones.
- Pertenencia de órdenes/capítulos al proyecto y de fases a la orden, también antes de llegar a las claves foráneas.
- Correspondencia entre producto y presentación, y compatibilidad de unidades/tipo al crear o modificar la vinculación de una partida.
- Ausencia de ciclos entre categorías/capítulos.
- Conversiones entre unidades distintas de la misma magnitud.
- Una dirección fiscal y un contacto principal por tercero.
- Suma de tramos de pago no superior al 100 %, calculada con enteros escalados para evitar aritmética float.
- Ausencia de solapamientos de precios de la misma tarifa, presentación y tramo de cantidad.
- Una sola configuración de empresa; el alta queda bloqueada una vez existe.

Los selectores presentan nombres en lugar de identificadores y filtran capítulos, fases, órdenes y presentaciones por su contexto. Cambiar un proyecto/producto invalida las selecciones dependientes. Elegir una presentación en una partida copia su unidad, unidad base, etiqueta y factor, conservándolos como datos de esa partida.

### Conservación y transacciones

El panel activa transacciones de base de datos para las operaciones de Filament. Esto incluye el alta de producto con su presentación y el guardado de relaciones del formulario.
Los maestros se mantienen mediante is_active y los documentos de ejecución mediante estados. Las pantallas principales no ofrecen acciones de borrado.
Los gestores de relaciones permiten borrar únicamente registros sin dependencias; la configuración de empresa y la presentación base quedan protegidas. Los listados precargan indicadores de existencia de dependencias para evitar consultas de comprobación por cada fila.
Las validaciones de modelo complementan las restricciones de base de datos; no sustituyen estas últimas. Los cálculos y aprobaciones futuros que requieran serialización concurrente necesitarán sus propios bloqueos y transacciones de dominio.

### Acceso al panel

Se mantiene un panel interno sin registro público. User implementa FilamentUser y permite el acceso al panel admin a cuentas existentes autenticadas. Todos esos usuarios tienen actualmente el mismo acceso administrativo.
No se crean cuentas ni contraseñas en esta etapa. Una matriz de permisos por función y policies específicas será una evolución separada antes de ofrecer accesos limitados a empleados o terceros.

### Límites y trabajo pendiente

La interfaz cubre las 25 tablas existentes. No representa todavía presupuestos, stock real, horas trabajadas, certificaciones, facturas ni VERI*FACTU.
No se ha implementado comprobación fiscal completa de NIF/NIE/identificadores extranjeros.
Durante la edición se permite que una condición de pago sume menos del 100 %; antes de utilizarla para facturar se deberá exigir el 100 % exacto.
Quedan pendientes las reglas de aprobación/transiciones de estado, conflictos de agenda, coherencia completa de vigencias entre cabecera de tarifa y sus precios, restricciones tras ejecución/facturación y permisos por función.
Las restricciones de roles se comprueban al realizar una nueva vinculación; todavía no hay un proceso de reclasificación de terceros con trabajos históricos.
Las validaciones realizadas por Eloquent no se ejecutan al escribir con Query Builder o actualizaciones masivas; los futuros importadores deberán utilizar las operaciones de dominio apropiadas.

### Verificación

Pruebas nuevas:
- BusinessModelsTest: factories de los 25 modelos, normalización fiscal, ciclos, magnitudes incompatibles, tramos, solapamientos, precisión decimal y protección de presentación base.
- FilamentResourcesTest: apertura de listados/altas/ediciones de los 23 resources, alta real desde cada formulario, apertura de los 18 relation managers y sus modales, autenticación, menús y errores en español, creación/edición de productos, condiciones comerciales, creación de fases/partidas y rechazo de relaciones incompatibles.

Todas utilizan SQLite en memoria con comprobación previa de conexión, y usuarios creados mediante factories exclusivamente en esa base. Se ejecutan también las pruebas de esquema de los bloques anteriores.
No se requiere instalar dependencias ni compilar frontend: esta etapa modifica PHP y traducciones.
Resultado final: php artisan test --compact pasa con 124 pruebas y 666 aserciones. Pint pasa sobre todos los archivos PHP de esta etapa. Se intentó --dirty como exige AGENTS.md; al no existir repositorio Git se indicaron las rutas explícitamente. La configuración efectiva app.locale es es y el estado de migraciones de MySQL conserva los mismos lotes 1 y 2, sin migraciones nuevas en esta etapa.

## 13. Parametrización fiscal de terceros, productos y empresa

### Motivo y alcance

El usuario solicita completar los datos fiscales necesarios para preparar la facturación española y VERI*FACTU: identificación del tercero, países, IVA, exenciones, retenciones y otros tratamientos.
Se implementa la parametrización y su validación. El módulo de emisión de facturas, sus cálculos definitivos y el envío a AEAT siguen pendientes. No se declara que la aplicación ya cumpla íntegramente el RRSIF por disponer de estos maestros.

### Fuentes oficiales y versión

Contrastadas el 3 de noviembre de 2026:
- [AEAT: esquemas y tipos comunes](https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SuministroInformacion.xsd).
- [AEAT: diseño de registros y listas L1, L7, L8A, L8B, L9 y L10](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/DsRegistroVeriFactu.xlsx).
- [AEAT: validaciones y errores, versión 1.2.2](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Validaciones_Errores_Veri-Factu.pdf).
- [Reglamento de facturación, RD 1619/2012](https://www.boe.es/buscar/act.php?id=BOE-A-2012-14696).
- [AEAT: tipos de retención y condiciones](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/folleto-actividades-economicas/7-otras-obligaciones-fiscales-retenciones/7_1-cuadro-relacion-tipos-retencion-porcentaje.html).
- [AEAT: retenciones en los registros de facturación](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/registros-facturacion-alta.html).

Los catálogos y reglas deberán revisarse cuando cambie la documentación oficial. La estructura XSD por sí sola no basta: el documento de validaciones impone restricciones adicionales.

### Identificación del tercero y países

Se mantienen separados:
- Tipo de persona: física o entidad (campo type existente).
- Condición en la que actúa: particular, empresario/profesional o Administración/organismo público.
- Roles comerciales: cliente, proveedor, subcontratista.
- Tipo de identificación fiscal y país emisor de esa identificación.
- País de cada domicilio, que no determina por sí solo el impuesto de una operación.

El país fiscal y los países de direcciones, proyectos y empresa pasan a ser selectores. FiscalCatalog utiliza los 246 códigos de CountryType2 del XSD consultado, incluidos sus códigos especiales. Los nombres de países y las opciones están en lang/es/fiscal.php. No se permite introducir libremente códigos ajenos al catálogo ni se crea una tabla editable que pueda desvirtuar la lista oficial. El código especial XG conserva una etiqueta explícita de código especial AEAT, sin asignarle una denominación geográfica no contrastada.

Tipos de identificación disponibles:
- nif: NIF español, incluidos DNI, NIE y NIF de entidades; utiliza el elemento NIF.
- 02: NIF-IVA.
- 03: Pasaporte.
- 04: Documento oficial del país de residencia.
- 05: Certificado de residencia.
- 06: Otro documento probatorio.
- 07: No censado, exclusivamente como destinatario y con país ES.

Los tipos 02 a 07 utilizan IDOtro. No se inventa un IDType 01 para el NIF español.
Se comprueba la compatibilidad de país/tipo. El código 07 no se habilita para la empresa emisora.
Los maestros siguen permitiendo contactos comerciales sin número fiscal. FiscalIdentity::recipient exige identificación al preparar los datos de un destinatario, produce una sola rama NIF o IDOtro y aplica límites de nombre e identificador del esquema.
El nombre fiscal se limita a 120 caracteres en la aplicación. NIF: hasta 9 caracteres al mantener el maestro y exactamente 9 con formato compatible al preparar la identidad; IDOtro: máximo 20.
La acción «Comprobar identificación guardada» revisa los datos persistidos y su formato. No consulta el censo AEAT ni VIES, no certifica el alta censal y no realiza una validación completa de todos los dígitos de control o estructuras nacionales extranjeras.
No se convierten ni truncan silenciosamente identificaciones o nombres antiguos. Los valores históricos fuera de estos límites requerirán revisión al editarlos o antes de facturar.

### Situación fiscal y valores predeterminados

Se añaden a terceros y empresa:
- taxpayer_capacity: condición en la que actúa.
- tax_territory: Península/Baleares, Canarias, Ceuta, Melilla, otro territorio UE o tercero.
- iae_code: epígrafe de actividad.
- withholding_obliged: obligación de practicar retenciones.
- equivalence_surcharge_applicable: acogimiento al recargo de equivalencia.

Los dos indicadores son de tres estados: sí, no y sin determinar. No se interpreta un dato desconocido como una respuesta negativa. La interfaz conserva correctamente el valor no al volver a abrir un registro.

Terceros, productos y empresa incorporan:
- sales_tax_profile_id y purchase_tax_profile_id.
- sales_withholding_rate_id y purchase_withholding_rate_id.
- fiscal_notes: observaciones y referencias de justificación.

Estos campos son propuestas para operaciones de venta/compra diferentes. No se copia automáticamente un IVA o un IRPF a los registros existentes y no se aplica una jerarquía ciega de sustitución entre cliente y producto.
El futuro motor de factura deberá resolver cada línea según fecha, territorio de realización, destinatario, actividad del emisor, bien/servicio y documentos justificativos, permitiendo confirmar el tratamiento antes de emitir.
Una retención de producto no obliga a retener a un particular. En compras importa la obligación de la empresa pagadora y la naturaleza/régimen de la actividad del proveedor. En ventas importa la actividad del emisor y la condición del destinatario.
El domicilio español de un contacto no decide por sí solo entre IVA, IGIC o IPSI. La operativa foral y sus sistemas propios deberán analizarse para cada empresa antes de su puesta en producción; este bloque no implementa TicketBAI ni Batuz.

### Nuevas tablas

| Tabla | Contenido |
| --- | --- |
| tax_rates | Código único, nombre, familia, porcentaje DECIMAL(7,4), vigencia desde/hasta, fundamento legal, necesidad de evidencia y activo. |
| tax_profiles | Código único, nombre, impuesto, clave de régimen, calificación, exención, tipo y recargo vinculados, fundamento legal, necesidad de evidencia y activo. |

Familias de tipos: IVA, IGIC, IPSI, retención IRPF, recargo de equivalencia y otro impuesto indirecto.
Los porcentajes de impuestos indirectos se comprueban con la precisión y tamaño del campo de tipo del esquema; las retenciones se conservan separadamente. Una retención no puede utilizarse como tipo indirecto.
Las claves de impuesto son 01 IVA, 02 IPSI, 03 IGIC y 05 otros. Las listas de régimen y causas de exención dependen del impuesto.
Los tipos tienen vigencia para permitir versiones futuras. No se sobrescribe una fila existente al repetir el seeder.

### Tratamientos fiscales

Se representan de forma distinta:
- S1: sujeta y no exenta sin inversión. Tiene un tipo, que puede ser 0 % si corresponde legalmente.
- S2: sujeta y no exenta con inversión del sujeto pasivo. El perfil exige tipo cero y no permite recargo.
- N1: no sujeta por artículo 7, 14 u otros.
- N2: no sujeta por reglas de localización.
- exempt: marcador interno de operación exenta; exige una causa de exención compatible y no contiene tipo ni recargo. Al generar el futuro registro se informará OperacionExenta, no el texto interno exempt.

Para IVA se admiten E1 a E6; IGIC tiene sus causas propias y admite además E7 y E8 conforme al documento de validaciones. Los textos legales no se reutilizan entre impuestos aunque coincida el código.
Se validan familia de tipo, ausencia de tipo/recargo en exentas y no sujetas, correspondencia IVA/recargo y combinaciones básicas de régimen y calificación.
Se incluyen, entre otras, las restricciones de exportación, régimen de caja, operaciones por cuenta de terceros y localización documentadas por AEAT. Las restricciones que necesitan tipo de factura, destinatarios o fecha de operación se completarán al crear las facturas.

Para una ejecución de obra no se elige automáticamente S2 por tratarse de construcción: el perfil exige revisar el supuesto contractual y los requisitos de los intervinientes.
El IVA al 10 % en determinadas obras tampoco se aplica globalmente por pertenecer a una categoría de producto o porque el cliente sea un particular.

### Retenciones y recargo

El IRPF se guarda fuera del perfil de impuesto indirecto. La retención modifica el importe a pagar/cobrar, no se resta del total del registro VERI*FACTU.
En el futuro módulo de factura se distinguirán expresamente base, cuotas de impuesto, cuota de recargo, total fiscal, retención y líquido a pagar.
Las retenciones de garantía de obra son otro concepto y no se confundirán con retenciones fiscales.

Se distingue entre:
- Proveedor en régimen general que repercute un recargo al minorista: los perfiles iniciales con recargo usan clave de régimen 01.
- Emisor acogido a su propio régimen especial de recargo: clave 18, que no se selecciona simplemente porque el cliente esté en recargo.

### Seeders

FiscalCatalogSeeder se incorpora a DatabaseSeeder después de BusinessMasterSeeder.
Carga 11 tipos iniciales: IVA 21/10/4/0; IRPF 15/7/1; recargo 5,2/1,4/0,5/0.
Carga 16 perfiles: los cuatro tipos sujetos, inversión en obra, seis causas de exención, dos no sujeciones y tres perfiles con recargo.
Las retenciones reducidas y los tratamientos especiales se señalan como condicionados y con necesidad de justificación.
La fecha inicial 2026-01-01 es el comienzo del catálogo configurado para esta instalación, no una afirmación de que todos esos tipos entraran legalmente en vigor ese día. No se pretende reconstruir períodos históricos ni asignar retroactivamente impuestos a documentos.
IGIC, IPSI y otros tienen estructura y catálogos de claves; sus tipos concretos deben configurarse y contrastarse antes de utilizarlos. No se precargan porcentajes regionales sin comprobar el caso.
La carga es transaccional, repetible y conserva las personalizaciones. No asigna perfiles ni retenciones a terceros, productos o empresa.

### Filament y traducciones

Nuevo grupo Fiscalidad:
- Tipos impositivos y retenciones.
- Perfiles fiscales.

Se crean dos resources, modelos y factories. Acumulado: 27 modelos de negocio, 25 resources y 18 relation managers.
Los formularios fiscales son condicionales según impuesto y tratamiento. Al cambiar de calificación se limpian las selecciones incompatibles; los campos ocultos conservan la deshidratación necesaria para guardar los valores nulos.
FiscalDefaults es una sección reutilizada por terceros, productos y empresa. Sus selectores de retención solo ofrecen la familia IRPF.
Se añaden columnas de perfiles fiscales a los listados de productos/terceros y filtros de identificación/territorio en terceros.
Los labels, opciones, ayudas y errores nuevos están en lang/es/fiscal.php.

### Migraciones, seguridad y comprobación

Se crean dos migraciones nuevas, sin editar las ya aplicadas:
- create_fiscal_configuration_tables.
- add_fiscal_defaults_to_business_records.

Los nuevos campos en tablas existentes son anulables y no se modifica su contenido anterior.
Copia privada previa: storage/app/private/backups/before_fiscal_configuration_20261103_171135.sql (135.733 bytes).
Ambas migraciones se han aplicado en MySQL como lote 3 y se ha ejecutado FiscalCatalogSeeder.
Pruebas fiscales en FiscalConfigurationTest: ramas de identificación, país/tipo, datos incompletos, catálogos repetibles, incompatibilidades, IGIC, asignación de IRPF, independencia de compra/venta, estados sí/no/desconocido y pantallas.
Se conserva la comprobación previa de SQLite en memoria en todas las pruebas que refrescan la base.
Resultado final: 29 pruebas fiscales específicas correctas; suite completa correcta con 153 pruebas y 814 aserciones. Formato PHP verificado con Pint sobre las rutas modificadas (--dirty no está disponible porque no hay repositorio Git). Comprobado con Artisan el esquema MySQL de tax_profiles y sus claves foráneas. No se ha ejecutado migrate:fresh ni rollback sobre MySQL.

## 14. Creación de fichas desde los selectores de relaciones

### Requisito permanente

El usuario solicita que todos los selectores de fichas gestionables permitan crear el registro relacionado sin abandonar el formulario, tanto ahora como en adelante. La convención se incorpora a AGENTS.md.

### Implementación

AppServiceProvider vincula la clase estándar Filament\\Forms\\Components\\Select con App\\Filament\\Forms\\Components\\RecordSelect en el contenedor de Laravel.
Los formularios siguen utilizando Select::make()->relationship(). Esto aplica automáticamente el comportamiento a los formularios existentes, los compartidos con relation managers y los futuros resources generados con Artisan.

RecordSelect:
- Localiza el resource registrado para el modelo relacionado.
- Configura createOptionForm() reutilizando Resource::form(), con operación de alta.
- Configura createOptionUsing() para crear la ficha con Eloquent, guardar las relaciones del formulario y devolver su clave primaria.
- Deja a Filament seleccionar la nueva opción, actualizar su etiqueta y ejecutar los eventos de cambio del selector.
- Admite modales anidados, como Orden → nuevo Proyecto → nuevo Cliente.
- Mantiene la traducción española del título y las acciones.
- Realiza cada alta en una transacción y sitúa los errores de validación en el formulario modal correspondiente.
- Comprueba canCreate() del resource relacionado en la acción y de nuevo antes de persistir. Al crear un tercero con rol contextual también comprueba la autorización del resource de roles.

### Contextos de creación

| Selector | Tratamiento |
| --- | --- |
| Cliente | Crea el tercero y su rol customer. |
| Proveedor | Crea el tercero y su rol supplier. |
| Contratista | Crea el tercero y su rol subcontractor. |
| Orden o capítulo | Mantiene el proyecto seleccionado, si existe. |
| Fase | Mantiene la orden seleccionada, si existe. |
| Presentación de producto | Mantiene el producto seleccionado, si existe. |
| Retención | Crea un tipo de familia withholding. |
| Recargo | Crea un tipo de familia surcharge. |
| Tipo indirecto de un perfil | Mantiene la familia del impuesto seleccionado. |

Los campos de contexto aparecen fijados en el modal. Sus valores se vuelven a imponer en el servidor al crear la ficha; no se confía en que un campo deshabilitado impida por sí solo modificar la petición.
Si se crea una orden, capítulo, fase o presentación con un propietario todavía no seleccionado en el formulario exterior, se sincronizan los campos de propietario que existan en ese formulario.
La creación de productos conserva el alta automática de la presentación base a través de los eventos del modelo.
Las comprobaciones de coherencia y pertenencia de los modelos siguen vigentes.

### Alcance y mantenimiento

Los enums, países oficiales, códigos de régimen y otras opciones fijas no representan fichas que se deban crear libremente.
Los selectores de usuarios existentes no tienen un resource de administración de cuentas y no se convierten en un mecanismo de registro de usuarios.
Para nuevos modelos de negocio basta con registrar su resource y usar el selector estándar de relación. Si una relación nueva tiene filtros o contexto adicionales, deberá ampliarse su tratamiento en RecordSelect y sus pruebas.
No se duplican los formularios de los resources ni se crea un esquema reducido distinto para los modales.
Se reutilizan los formularios y las reglas de los modelos; los futuros efectos de negocio reutilizables deberán residir en modelos/servicios o configurarse expresamente en la creación de opciones, no únicamente en hooks de una página de alta.

### Comprobación

RelatedRecordSelectTest comprueba la cobertura de selectores en los 25 resources y flujos reales de creación, selección, modales anidados, roles, contexto de orden/producto/familia fiscal, errores y autorización.
Pruebas con SQLite en memoria y comprobación previa de conexión.
No se han creado migraciones ni modificado registros de la base MySQL en esta etapa.
Resultado final: 37 pruebas de selectores correctas; suite completa correcta con 190 pruebas y 2.197 aserciones. Pint pasa sobre RecordSelect, AppServiceProvider, las traducciones y las pruebas modificadas. Se intentó --dirty según AGENTS.md y se utilizaron rutas explícitas al no existir repositorio Git. No se requieren cambios de esquema, carga de datos ni compilación frontend.






## 15. Facturación de venta y preparación de VERI*FACTU — 3 de noviembre de 2026

### Estado y límite de esta entrega

Se incorpora un módulo funcional **exclusivamente de pruebas**. La emisión de producción está bloqueada expresamente en el servicio, incluso si alguien crea una serie de producción directamente en la base de datos. No basta con cambiar una variable de entorno para habilitarla.

Este módulo todavía **no es un SIF listo para facturar legalmente en producción**: faltan los flujos de subsanación de registros rechazados/aceptados con errores, anulación registral, comprobación de duplicados contra AEAT y la validación integral con el certificado real. También deben completarse y conservarse la declaración responsable del productor y las comprobaciones operativas de conservación, exportación, recuperación y envío continuado. Los estados de error se muestran para revisión; no se modifican ni sustituyen silenciosamente registros originales. No se ha firmado ni inventado una declaración responsable.

La empresa utilizará **certificado digital propio**, según decisión del usuario. No se solicitará certificado ni contraseña por el chat. El código de transmisión se dirige únicamente al servicio oficial de pruebas. No se han remitido facturas ni se han habilitado envíos reales durante el desarrollo.

### Uso en Filament

Menú **Facturación**:

- **Series de facturación**: series ordinarias y rectificativas por ejercicio. El seeder crea solamente F y R de pruebas para el ejercicio actual. No reinicia contadores ni modifica series existentes.
- **Facturas de venta**: crear un borrador eligiendo cliente, fechas y serie; guardar; añadir las líneas en el relation manager. Se pueden incorporar productos o describir servicios libremente, con cantidad, unidad, precio, descuento, tratamiento fiscal y retención.
- Desde la edición de una orden de trabajo, **Facturar fases** permite seleccionar varias fases y un porcentaje de las cantidades pendientes. El porcentaje se aplica al pendiente, no al presupuesto total original. Las cantidades del borrador resultante se pueden ajustar antes de emitir. Las partidas sin fase no se importan por esta acción: deben asignarse a una fase o facturarse individualmente.
- **Emitir en pruebas** guarda primero la cabecera y después valida, numera y congela la factura en una transacción. Los errores no consumen número. Los borradores no tienen número fiscal.
- **Cancelar borrador** conserva su historial y libera sus reservas. No anula una factura emitida ni consume números.
- La vista de una factura emitida permite imprimir/guardar PDF mediante el navegador, descargar el XML original y preparar una rectificativa. El documento impreso muestra claramente que es una prueba sin validez fiscal.
- **Preparar rectificativa** crea un borrador R1–R4 por diferencias (I), con cantidades negativas y referencia expresa a la factura original. Conserva precios, descuentos y tipos históricos; permite reducir cantidades o retirar líneas. No implementa rectificativas por sustitución, incrementos de precio ni rectificación de rectificativas.

Los selectores de cliente, serie, orden, producto, tratamiento fiscal y retención conservan la creación de fichas desde el modal y la autorización de su resource. Los selectores auxiliares de series/fases de la acción de una orden reutilizan los formularios completos de sus resources, guardan sus relaciones y fijan el propietario/tipo en servidor. Se amplía RecordSelect para retenciones de líneas y contexto de series. Las órdenes creadas desde una factura deben pertenecer a un proyecto del cliente seleccionado; una creación incompatible se rechaza antes de guardarla.

### Datos y trazabilidad

La migración `create_sales_billing_tables` añade siete tablas, sin modificar datos de negocio existentes:

| Tabla | Responsabilidad |
| --- | --- |
| invoice_series | Identificación de serie, ejercicio, tipo, entorno y próximo número. |
| sales_invoices | Cabecera, cliente, orden opcional, rectificación, estados y totales. |
| sales_invoice_lines | Cantidades, precios, fiscalidad y vínculo a partidas originales. |
| billing_chains | Último registro de cada entorno; bloqueo común de encadenamiento. |
| billing_records | XML, huella, registro anterior, fecha con huso e identificación del sistema, inmutables. |
| billing_submissions | Estado de envío, intentos, respuesta, CSV e incidencias. |
| billing_submission_attempts | Historial de respuestas de cada intento de comunicación. |

El emisor y el destinatario se guardan como instantáneas, incluyendo NIF/IDOtro y domicilio completo. También se conservan los porcentajes, las bases, las cuotas y las referencias legales de las líneas. Cambiar las fichas de contacto, producto o impuestos no modifica una factura emitida. Las rectificativas utilizan la identidad histórica del destinatario original.

La numeración se reserva bajo bloqueo de la serie y dentro de la misma transacción que crea factura definitiva y registro. La fecha respeta el orden cronológico de la serie. El encadenamiento es común para todas las series del mismo entorno, no independiente por serie, cliente o proyecto. Una cadena iniciada no permite cambiar el NIF del obligado tributario.

Las claves foráneas impiden borrar órdenes, partidas, clientes o productos referenciados por facturas. La eliminación especial de órdenes en borrador también consulta si existen facturas. No se ofrecen borrados físicos de facturas ni de series.

La protección de inmutabilidad y los bloqueos se aplican a las operaciones normales de modelos/servicios, incluidos formularios con estado obsoleto. El acceso administrativo directo a SQL no queda protegido por eventos de Eloquent: los permisos de base de datos, copias y controles de operación siguen siendo necesarios. No se han creado triggers que pretendan certificar el cumplimiento del sistema.

### Cantidades y cálculo

- Se usan decimales exactos (`brick/math`, ya instalado) y redondeo monetario a céntimos; no aritmética de coma flotante para importes.
- Cada línea calcula su base después del descuento. Los impuestos indirectos se agrupan por su tratamiento y porcentajes; se calcula la cuota del grupo y se distribuyen diferencias de céntimos entre líneas. El límite de AEAT es de 12 **grupos de desglose**, no de 12 partidas de factura.
- `gross_total` = base + IVA + recargo. `payable_total` = total anterior − IRPF. El total y el QR comunicados a VERI*FACTU utilizan el total anterior a retenciones; IRPF no se envía como un impuesto indirecto ni se resta del registro.
- Una rectificación parcial reparte los importes originales; la última fracción absorbe los céntimos pendientes para no abonar más de la base/cuota original.
- Los borradores ordinarios y las facturas emitidas reservan cantidades de las partidas de origen. Las rectificativas en borrador reservan su capacidad de rectificación, pero **no liberan cantidades para volver a facturar** hasta que se emiten.
- Se bloquea la orden al validar reservas y se vuelve a comprobar al emitir. El origen y el cliente deben coincidir. Se evita sobrefacturar y abonar más cantidad que la factura original, teniendo en cuenta otros borradores.
- Las cantidades de obra se facturan con el precio previsto de venta de la partida. No se altera el presupuesto, el coste ni el stock por emitir una factura. Una factura no sustituye a un movimiento de almacén ni a una imputación de horas.

### Alcance fiscal soportado

Esta versión admite EUR, IVA nacional (`Impuesto=01`), regímenes 01 y 18 y factura completa F1; rectificativas R1, R2, R3 y R4 por diferencias. Se bloquean IGIC, IPSI, exportaciones, criterio de caja, OSS y demás regímenes hasta disponer de sus flujos específicos. No se generan facturas simplificadas F2, facturas de compra, pagos/cobros, vencimientos fraccionados ni proformas en esta entrega.

Cada línea puede tener un perfil fiscal distinto. Al seleccionar un producto se propone su configuración; en su defecto, la del cliente y después la de la empresa. Esta prioridad es una sugerencia editable, no una decisión jurídica automática: un supuesto especial del cliente debe revisarse expresamente antes de emitir. La información fiscal del cliente no cambia automáticamente todos los tipos reducidos de sus productos.

Se comprueban actividad y vigencia de los tipos en la fecha de operación; los supuestos marcados como condicionados requieren justificación. Se conservan menciones de exención y de inversión del sujeto pasivo en el documento. S2 requiere destinatario empresario/profesional y cuota cero; las exenciones no envían tipo ni cuota. Las retenciones requieren emisor persona física y destinatario empresario/profesional o entidad pública con obligación de retener confirmada; escoger un porcentaje no demuestra por sí solo su aplicación. La actividad/IAE, comienzo de actividad y demás condiciones deben justificarse según el supuesto. El recargo exige cliente sujeto al régimen y producto material. La comprobación local de identificadores no equivale a consultar el censo AEAT/VIES ni garantiza la aplicación jurídica del tratamiento elegido.

### Certificado propio y configuración privada

Variables documentadas también en `.env.example` y leídas exclusivamente desde `config/billing.php`:

```dotenv
BILLING_PRODUCER_NAME="Nombre real del productor del software"
BILLING_PRODUCER_NIF="NIF real del productor"
BILLING_SYSTEM_NAME="Gestion de obras"
BILLING_SYSTEM_ID=01
BILLING_SYSTEM_VERSION=0.1.0
BILLING_INSTALLATION_ID="Identificador estable de esta instalación"
BILLING_RESPONSIBLE_DECLARATION_PATH="Ruta privada a la declaración cuando exista"
VERIFACTU_SEND_ENABLED=false
VERIFACTU_CERTIFICATE_PATH="Ruta privada al certificado PEM de la empresa"
VERIFACTU_CERTIFICATE_PASSWORD=
VERIFACTU_PRIVATE_KEY_PATH="Ruta privada a la clave PEM"
VERIFACTU_PRIVATE_KEY_PASSWORD=
```

No usar nombres o NIF inventados para el productor. La identificación del productor puede coincidir con la empresa solamente cuando esta sea realmente quien desarrolla el sistema. La instalación no debe regenerarse al desplegar o al reiniciar. Las instantáneas del sistema quedan guardadas junto al XML; cambiar configuración no reescribe registros previos.

El conector utiliza certificado PEM y clave PEM separada, con soporte de clave cifrada mediante contraseña. Si el certificado entregado es P12/PFX, debe convertirse localmente en un entorno seguro; no se cargan secretos desde formularios web. Las rutas bajo `public/` se rechazan. La verificación TLS del servidor permanece activada; no se usa `verify=false`.

Activar envíos solo después de configurar identificación y certificado. `php artisan billing:send-pending --no-interaction` remite el siguiente registro pendiente a **prewww1.aeat.es**, respetando los tiempos de espera. La tarea se programa cada minuto mediante el scheduler de Laravel; el servidor debe tener activo `schedule:run` o `schedule:work`. No se ha cambiado el cron del servidor. Existe además una acción manual de envío de pruebas, mediante la cola; si la conexión de cola no es `sync`, debe estar ejecutándose el worker.

Los reintentos técnicos reutilizan exactamente el mismo XML y huella. Se serializan los envíos y se guarda el historial de respuestas. Una reclamación de envío obsoleta se recupera después de cinco minutos. Se comprueba el esquema y que la respuesta corresponda al NIF/número/fecha enviados. Un duplicado exige revisión; no se marca como aceptado automáticamente. No se reintentan automáticamente los rechazos fiscales como si fueran fallos de red.

### Fuentes contrastadas y artefactos oficiales

Consulta del 3 de noviembre de 2026:

- [Reglamento de facturación, RD 1619/2012, texto consolidado con actualización publicada el 31/03/2026](https://www.boe.es/buscar/act.php?id=BOE-A-2012-14696): contenido, numeración y rectificación.
- [AEAT: información técnica de VERI*FACTU](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/informacion-tecnica.html).
- [AEAT: SuministroInformacion.xsd](https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SuministroInformacion.xsd).
- [AEAT: diseño de registros y listas](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/DsRegistroVeriFactu.xlsx), utilizado también en la parametrización fiscal anterior.
- [AEAT: validaciones y errores, versión 1.2.2](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Validaciones_Errores_Veri-Factu.pdf).
- [AEAT: especificación de la huella, versión 0.1.2](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Veri-Factu_especificaciones_huella_hash_registros.pdf): concatenación y SHA-256, contrastado con su primer ejemplo publicado.
- [AEAT: QR, versión 0.5.0](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/DetalleEspecificacTecnCodigoQRfactura.pdf): URL y parámetros, QR de 35 mm en la impresión.
- [AEAT: retenciones en los registros de alta, FAQ actualizadas a 21/10/2026](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/registros-facturacion-alta.html).
- [AEAT: cuadro de retenciones y condiciones](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/folleto-actividades-economicas/7-otras-obligaciones-fiscales-retenciones/7_1-cuadro-relacion-tipos-retencion-porcentaje.html). La página consultada sigue rotulada como tipos aplicables de **2025**: no se presenta como certificado de vigencia de cualquier porcentaje para 2026.

En `resources/verifactu` se conservan los XSD de suministro, información común y respuesta, el WSDL oficial y el esquema XMLDSIG del W3C para validación sin red. En el XSD común se ha localizado únicamente el import de XMLDSIG a su copia local; no se han relajado las restricciones de la AEAT. La validación XSD es necesaria pero no sustituye las comprobaciones semánticas ni una prueba real de integración.

### Despliegue local y comprobación de esta entrega

La migración aditiva se ha aplicado a MySQL, seguida únicamente de `BillingSeeder`. Se han creado las series de pruebas; no se han generado facturas de ejemplo en la base de trabajo ni se han modificado sus contactos, productos u órdenes. Se conserva una copia SQL previa en `storage/app/private/backups/pre-sales-billing-20261103-202653.sql`, con permisos privados. Los envíos permanecen desactivados; el cron y el certificado no se han configurado automáticamente.

Las pruebas se ejecutan con SQLite `:memory:` y comprobación explícita de conexión antes de refrescar la base. La cobertura nueva incluye el flujo de Filament, facturación parcial, pertenencia de fases, reservas, cancelación de borradores, inmutabilidad y editores obsoletos, numeración/encadenamiento, el vector oficial de hash, XSD, exenciones, ISP documentada, IRPF, redondeos, créditos parciales, permisos, creación anidada de fichas, impresión/XML autenticados y reintentos HTTP con historial. Las llamadas HTTP de las pruebas están simuladas; no se ha usado un certificado real ni se ha enviado un registro a AEAT.

Pint se ha aplicado a los archivos PHP modificados. Se intentó `--dirty` conforme a AGENTS.md; al no existir repositorio Git, se pasó la lista explícita de archivos. No se han instalado dependencias ni ha sido necesario compilar assets frontend.

Resultado final: **317 pruebas correctas y 3.189 aserciones** en la suite completa, incluidas **32 pruebas específicas de facturación**. La migración figura aplicada y las rutas de Filament, impresión y exportación XML están registradas. El comando de envío confirma que los envíos están desactivados.

### Primera prueba con el certificado aportado — 4 de noviembre de 2026

El usuario ha aportado `B57479677_.p12` en la raíz del proyecto, protegido por contraseña. Se ha comprobado su presencia, sin abrir su clave privada ni solicitar la contraseña por chat. El nombre del archivo no acredita por sí solo el titular, la vigencia ni que sea un certificado de representación válido.

Diagnóstico de la configuración efectiva en esta revisión: falta la ficha de empresa; están sin configurar el productor, su NIF, la instalación y las rutas/contraseñas del certificado. Los envíos están desactivados. Existen dos series activas de pruebas del ejercicio 2026. La cola configurada es `database`.

El conector actual utiliza PEM y clave privada PEM separados. Para este primer ensayo se conserva esa implementación: convertir localmente el P12, sin poner contraseñas como argumentos de comandos, sin exportar una clave sin cifrar y sin subir archivos a `public/`.

Desde `/var/www/html`, con los permisos adecuados:

```bash
mkdir -p storage/app/private/verifactu
chmod 700 storage/app/private/verifactu
chmod 600 B57479677_.p12
openssl pkcs12 -in B57479677_.p12 -clcerts -nokeys -out storage/app/private/verifactu/certificado.pem
openssl pkcs12 -in B57479677_.p12 -nocerts -out storage/app/private/verifactu/clave.pem
chmod 600 storage/app/private/verifactu/certificado.pem storage/app/private/verifactu/clave.pem
chown -R www-data:www-data storage/app/private/verifactu
openssl x509 -in storage/app/private/verifactu/certificado.pem -noout -subject -issuer -dates
```

OpenSSL solicita interactivamente la contraseña de importación del P12. Al exportar la clave solicita además una contraseña para cifrar la clave PEM: esta última es `VERIFACTU_PRIVATE_KEY_PASSWORD`. El certificado PEM público no necesita contraseña. Revisar titular/representación y caducidad antes del ensayo; no deducir el titular únicamente del nombre del archivo.

Completar la ficha de empresa emisora y un cliente con identificación y domicilio fiscal completos. Completar también la identidad real del productor del software y un identificador estable de instalación en `.env`. El productor no se sustituye automáticamente por el titular del certificado. Usar las rutas absolutas a los dos PEM en `VERIFACTU_CERTIFICATE_PATH` y `VERIFACTU_PRIVATE_KEY_PATH`, y guardar la contraseña PEM solo en la configuración privada.

Mantener inicialmente `VERIFACTU_SEND_ENABLED=false`, ejecutar `php artisan config:clear --no-interaction` y preparar una factura F1 con una serie de pruebas del ejercicio correcto; por ejemplo, una línea de 10 EUR y tratamiento IVA general 21 %, sin retención. Emitirla en pruebas crea el registro local y permite revisar el XML. Después, activar envíos de pruebas conscientemente y volver a limpiar la caché de configuración.

`php artisan billing:send-pending --no-interaction` envía directamente el registro pendiente más antiguo; no necesita worker. No selecciona por sí solo la factura que se tenga abierta en pantalla. El scheduler, si está activo en el servidor, también podrá enviar pendientes al habilitar la variable. El botón de envío de una factura concreta sí utiliza la cola `database`; para procesarlo debe ejecutarse `php artisan queue:work` y reiniciarse el worker cuando cambie la configuración (`php artisan queue:restart --no-interaction`).

Comprobar en la vista de factura el estado, CSV e incidencias. `accepted` confirma aceptación en el entorno de pruebas; `accepted_with_errors` requiere revisión y `rejected` no equivale a aceptación. La emisión de producción sigue bloqueada. No se han cambiado variables privadas, convertido certificados ni enviado registros durante esta revisión.

### Carga del precio unitario en líneas de factura — 4 de noviembre de 2026

Se corrige una omisión del formulario: al seleccionar un producto se proponían descripción, unidad e impuestos, pero no se consultaban los precios ya registrados. Ahora `SalesPrice` propone el precio de la presentación base a partir de la tarifa asignada al cliente, en la moneda de la factura y vigente en su fecha de operación. Se comprueba tanto la vigencia y actividad de la tarifa como las fechas del precio, y se elige el tramo de mayor cantidad mínima que cumpla la cantidad de la línea. Cuando aún no se ha indicado cantidad, se consulta para una unidad.

Si el cliente no tiene tarifa asignada, se utiliza el precio solo cuando hay una única tarifa aplicable al producto; si hay varias, no se elige una arbitrariamente. Si la tarifa asignada no tiene precio aplicable, se deja el campo vacío para su introducción manual. No se utilizan precios de compra ni precios de paquetes como si fueran precios de la unidad base.

Al cambiar la cantidad se actualiza el precio propuesto si conserva el valor automático anterior, respetando los precios modificados manualmente. No se aplica esta actualización por cantidad a rectificativas ni a líneas procedentes de órdenes, cuyos precios originales/pactados se conservan. Cambiar de producto limpia el precio anterior cuando el nuevo producto no tiene precio aplicable. Abrir o guardar una factura no recalcula sus precios automáticamente.

Se añaden pruebas de selección y guardado desde el relation manager, tramos por cantidad, precios manuales, vigencias, monedas, presentación, tarifas ambiguas y cambio de producto. Sin migraciones ni cambios en los datos de negocio.

### Selección explícita del destino VERI*FACTU — 4 de noviembre de 2026

Se incorpora `VERIFACTU_PRODUCTION` en `.env` y `.env.example`, inicialmente vacío. Solo el valor exacto `production` selecciona el destino real. Ausencia, cadena vacía, `true`, `1`, mayúsculas u otros valores mantienen el destino de pruebas. La lectura se hace en `config/billing.php`, compatible con la caché de configuración de Laravel; el resto del código utiliza `billing.environment`.

```dotenv
# Pruebas: valor vacío o variable ausente
VERIFACTU_PRODUCTION=

# Producción: activación explícita del destino
# VERIFACTU_PRODUCTION=production
```

`VERIFACTU_SEND_ENABLED` sigue controlando si se permiten envíos. El nuevo flag solo elige su entorno:

- Pruebas: `https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP`.
- Producción: `https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP`.

El entorno conservado en cada factura debe coincidir con el configurado antes de enviarla. Cambiar el flag no convierte documentos, XML, series ni cadenas de pruebas en documentos de producción. Un envío directo incompatible se rechaza sin incrementar intentos; el comando y el scheduler solo seleccionan pendientes del entorno configurado. La serialización, recuperación de envíos y tiempos de espera también quedan separados por entorno. El botón de envío muestra el destino correspondiente y solo aparece en facturas compatibles con el entorno activo.

Esta modificación prepara la selección del destino del transporte. **Permanece el bloqueo independiente de emisión de facturas de producción** mientras no se completen los trabajos de subsanación/anulación, declaración responsable y validación integral descritos en esta sección. El flag no declara que el módulo esté listo para producción ni elimina ese bloqueo.

Después de cambiar el flag, ejecutar `php artisan config:clear --no-interaction` (o reconstruir la caché según el despliegue) y `php artisan queue:restart --no-interaction` si existen workers persistentes. En esta entrega se deja el flag vacío y no se remiten registros a ningún entorno. No hay migraciones ni conversiones de datos.

Las pruebas comprueban ocho valores/ausencia del flag, selección de los dos endpoints con HTTP simulado y bloqueo de documentos de pruebas al seleccionar producción, tanto en envío directo como en el comando de pendientes.

### Domicilio fiscal visible desde la factura — 4 de noviembre de 2026

Elegir un cliente vincula su ficha; los domicilios se gestionan en su relación Direcciones, no como campos de la cabecera de factura. Para evitar un bloqueo de emisión sin acceso visible a la información que falta, el formulario de factura ahora muestra el domicilio fiscal del cliente seleccionado o un aviso concreto: ninguna/varias direcciones fiscales activas, o campos obligatorios incompletos.

Se añade el botón «Gestionar domicilio fiscal del cliente», que abre la ficha del contacto en otra pestaña para gestionar Direcciones sin perder los datos de la factura. Sigue disponible cuando el selector de cliente está bloqueado por existir líneas. Tanto la información del domicilio como el acceso a edición respetan los permisos del resource de contactos. El domicilio debe tener dirección, localidad, código postal y país; exactamente una dirección debe estar marcada como fiscal y activa. No se duplican domicilios en la cabecera ni se crean direcciones automáticamente al seleccionar un cliente.

También se corrige un mensaje ambiguo de emisión: un domicilio incompleto de la empresa emisora se identifica ahora como un problema de la configuración de empresa, en lugar de pedir que se modifique el cliente. Las instantáneas de las facturas emitidas conservan su comportamiento anterior.

Se añaden pruebas de formulario para ausencia, duplicidad, campos incompletos, domicilio completo y permisos, junto con una comprobación del error específico del emisor. Sin migraciones ni modificaciones de los domicilios existentes.

### Orden de elementos XML en rectificativas — 4 de noviembre de 2026

Se corrige la emisión de rectificativas tras recuperar de MySQL la identidad histórica del destinatario. Las columnas JSON de MySQL pueden devolver las claves en un orden diferente al de inserción; el generador copiaba directamente ese orden al XML. Esto producía `NIF` antes de `NombreRazon`, incumpliendo la secuencia del XSD de AEAT. En SQLite las pruebas iniciales conservaban el orden y no detectaban esta diferencia de almacenamiento.

El generador reconstruye ahora explícitamente la identidad del destinatario en el orden del esquema: `NombreRazon`, seguido de `NIF` o `IDOtro`; dentro de `IDOtro`, `CodigoPais` cuando exista, `IDType` e `ID`. Utiliza exclusivamente los valores de la instantánea histórica, sin consultar de nuevo la identidad actual del cliente. La corrección no modifica facturas emitidas, XML previamente guardados, huellas ni datos maestros; se aplica al generar nuevos registros.

Se añaden dos regresiones que simulan el reordenamiento de claves JSON tras la persistencia, con NIF y con IDOtro, y emiten una rectificativa validada contra el XSD oficial. Ambas reproducían el fallo antes de la corrección y pasan después. No se ha emitido ni enviado una rectificativa real durante la reparación.

## 16. Albaranes de compra, almacén y costes por fase — 4 de noviembre de 2026

### Decisiones confirmadas

El usuario fija un incremento inicial del **15 % sobre el precio de compra**. La fórmula es `venta sin impuestos = coste neto de compra × (1 + porcentaje / 100)`: una compra de 100 € propone una venta de 115 €. Se denomina incremento o recargo sobre coste para distinguirlo del margen sobre la venta (15 € sobre 115 € no representa un 15 % de margen sobre venta).

Se configura en la ficha de empresa, con una excepción opcional por producto. Un valor vacío en el producto hereda el porcentaje de empresa; 0 % es un valor explícito válido. La migración inicializa el porcentaje de empresa a 15 %, según la respuesta del usuario, sin cambiar precios de facturas ni presupuestos ya guardados.

Las fases y asignaciones de operarios/contratistas ya existían. Se conservan y se amplían con costes materiales y partes horarios. No se crean fases ficticias ni se asigna automáticamente personal a una obra. Una orden puede prepararse en borrador sin fases; para pasar a planificación, ejecución, pausa o finalización debe tener al menos una fase no cancelada. No se puede borrar, cancelar o trasladar su última fase mientras la orden esté activa. Se bloquean las filas de las órdenes al cambiar estados o retirar fases. No se han modificado los estados de órdenes anteriores mediante una actualización masiva.

### Esquema añadido

La migración `2026_11_04_171829_create_purchasing_and_phase_cost_tables` añade:

| Tabla | Función |
| --- | --- |
| warehouses | Almacenes, con código único y activación. |
| purchase_delivery_notes | Albarán del proveedor, almacén receptor, fecha y estado. Referencia única por proveedor. |
| purchase_delivery_lines | Producto, presentación, cantidad, precio neto, descuento y fotografía del coste recibido. Cada línea recibida identifica un lote de existencias. |
| material_consumptions | Imputación de una cantidad de un lote a una fase concreta, con coste y valor de venta histórico; también devoluciones referenciadas al consumo original. |
| labor_cost_rates | Coste horario de un operario o un contratista, con vigencia inclusiva desde/hasta. |
| work_time_entries | Partes por fase, persona, inicio, fin y pausas; horas y coste confirmado conservados. |

Se añaden `sales_markup_percentage` a empresa (15 % inicial) y producto (opcional). Se utilizan claves foráneas restrictivas; el vínculo compuesto orden/fase impide atribuir un coste a una fase de otra orden. El modelo exige exactamente uno de `employee_id` o `contractor_id` en tarifas y partes. Los importes se tratan con decimales exactos; no se utilizan floats para calcular importes.

### Entrada de productos por albarán

Menú **Compras y almacén** → **Albaranes de compra**:

1. Crear cabecera con proveedor, referencia del albarán, almacén y fecha.
2. Añadir líneas: producto, presentación opcional, cantidad, precio de compra sin impuestos y descuento.
3. Pulsar **Confirmar recepción**. Hasta entonces el albarán es un borrador y no genera existencias ni precios de compra utilizables.

El producto debe ser de tipo Material y tener **Controlar stock** activado. Los servicios y la mano de obra se imputan por su flujo correspondiente, no como existencias físicas. La creación de productos desde este selector fija tipo Material, control de stock y activación en servidor; la creación de presentaciones mantiene el producto y habilita su compra. Los proveedores creados desde el selector reciben su rol de proveedor.

Al recibir se convierte la cantidad de la presentación a la unidad base mediante su factor. El coste neto de línea incluye el descuento, sin impuestos, y se reparte entre la cantidad base. Por ejemplo, dos cajas de 12 unidades a 120 € por caja y descuento del 10 % producen 24 unidades, coste neto de 216 €, coste base de 9 € y venta propuesta de 10,35 € por unidad al 15 %.

Se conservan descripción de producto/unidad, factor de conversión, cantidad base, coste unitario, importe, porcentaje y propuesta de venta. Después de confirmar no se puede modificar ni borrar el albarán o sus líneas, incluso desde un formulario abierto antes de confirmarlo. No se permite cambiar tipo ni unidad base de un producto que ya tenga recepciones confirmadas. Cambiar la presentación comercial posteriormente no altera factores de documentos recibidos.

Cada albarán recibe en un único almacén. Se crea un **Almacén principal** mediante un seeder idempotente, que no cambia su configuración si ya existe. Las líneas del albarán muestran cantidades físicas y cantidades disponibles después de las reservas.

### Materiales asignados a órdenes y fases

En **Obras → Fases de órdenes de trabajo**, abrir la fase y su relación **Consumos de materiales**; también existe el listado global de consumos:

1. Elegir el lote o línea de un albarán ya recibido. El selector identifica almacén, proveedor, referencia, lote, producto y cantidad disponible en unidad base.
2. Indicar fecha y cantidad consumida en unidad base.
3. Guardar el borrador y confirmar la imputación.

Los borradores reservan cantidades. Las existencias físicas disminuyen al confirmar; la disponibilidad ya descuenta otros borradores. Las reservas, confirmaciones y devoluciones bloquean la línea de recepción de origen para evitar stock negativo o doble asignación concurrente. La fecha del consumo no puede preceder a la recepción; las confirmaciones no pueden ser futuras.

La valoración utiliza **el coste real del lote elegido**, no un coste medio inventado ni una asignación FIFO automática. La última imputación que agota el lote absorbe los céntimos residuales para cuadrar el coste total con el albarán. El porcentaje y precio de venta previsto de la imputación se toman de la fotografía de esa recepción.

**Cancelar borrador** libera la reserva. **Devolver al almacén** crea y confirma una imputación negativa nueva, referenciada a la original, y revierte exactamente sus cantidades e importes. Esta entrega admite una devolución íntegra por consumo; las devoluciones parciales se rechazan para evitar abonar todo el importe a cambio de solo parte del material. Para devoluciones independientes se pueden registrar consumos separados. Se conserva la imputación original y no se admiten devoluciones duplicadas. La devolución repone el almacén de origen.

El selector de lotes recibidos no ofrece «crear opción»: identifica entradas de stock ya confirmadas, sin resource de alta independiente. Para disponer de un lote nuevo se debe crear y recibir un albarán; no se confirma una recepción implícitamente desde un consumo. Esta excepción mantiene la separación entre entrada física e imputación a obra.

### Operarios, contratistas, tarifas y partes

En **Equipo → Costes por hora**, seleccionar exclusivamente un operario o un contratista, el coste horario y su período de vigencia. El contratista debe tener ese rol en Contactos. Las tarifas son costes internos netos, introducidos por el usuario; no se han inventado salarios, tarifas reales ni reglas automáticas de nómina/cotización.

Las fechas de vigencia son inclusivas y no pueden solaparse para la misma persona/recurso. Para un cambio de coste se cierra el período anterior y se abre otro. Una tarifa utilizada no permite cambiar persona, coste, moneda o inicio; su fecha final sí puede cerrarse siempre que siga cubriendo todos los partes ya confirmados.

Asignar el operario/contratista a la fase o a la orden mediante las asignaciones existentes. En **Partes de trabajo**, o desde la relación de la fase, introducir inicio, fin, pausa no imputable y descripción. La pantalla utiliza **Europe/Madrid** y guarda los instantes en UTC. El máximo es de 24 horas por parte. Al confirmar:

- Debe existir asignación a esa fase o a toda la orden.
- Se comprueban persona activa y ausencia de otros partes confirmados solapados del mismo recurso.
- Debe existir una única tarifa que cubra todo el intervalo. Si el trabajo cruza un cambio de tarifa, debe dividirse en partes separados en ese límite.
- Se calculan las horas descontando pausas. Para el coste se utilizan los segundos efectivos multiplicados por la tarifa, redondeando a céntimos al final; no se multiplica una aproximación previa de las horas.
- Se guardan tarifa utilizada, horas, coste por hora e importe. Cambios de tarifas posteriores no revaloran trabajos históricos.

Los contratistas se modelan como recursos identificados por su contacto: la tarifa y el control de solapamiento corresponden a ese recurso. La gestión detallada de varios trabajadores de una misma empresa contratista no introduce automáticamente personas nuevas a partir de un único contacto empresarial.

Los partes confirmados se conservan inmutables. Esta entrega permite corregir/cancelar sus borradores; no incluye todavía un flujo de ajuste de partes ya confirmados. Deben revisarse antes de confirmarlos.

### Precios propuestos y separación entre presupuesto y ejecución

Para nuevas propuestas de precio de productos con compras recibidas, se utiliza el coste de la **última recepción según fecha**, no futura respecto a la operación, aplicando el porcentaje actual del producto o la empresa. Esa regla tiene prioridad sobre las tarifas comerciales fijas. El incremento se aplica antes de redondear el precio unitario. Si todavía no existe ninguna recepción de ese producto, se conserva el sistema de tarifas anterior para permitir el uso de los productos ya configurados.

Al seleccionar un producto o presentación en una partida de orden se proponen coste y venta conforme a esa regla. En las facturas se reutiliza el mismo cálculo. No se recalculan de oficio precios ya guardados al abrir o guardar un documento; siguen existiendo las modificaciones comerciales explícitas de borradores y las condiciones acordadas en partidas de órdenes. Cambiar porcentajes no reescribe facturas emitidas ni presupuestos históricos.

Los **consumos y partes son costes de ejecución**, separados de las cantidades previstas y facturables de las partidas de órdenes. Registrar un consumo no añade otra partida de venta ni factura dos veces un material presupuestado. La facturación por fases continúa utilizando las partidas de venta de la orden; un trabajo/material adicional que deba cobrarse se debe añadir a esas partidas antes de facturarlo. Emitir una factura tampoco descuenta stock por segunda vez.

### Consulta, permisos y alcance

Las fichas de fase y de orden muestran materiales confirmados, mano de obra confirmada y suma de costes reales. No incluyen borradores ni costes supuestos por faltar una tarifa. Las devoluciones confirmadas restan del coste material.

Se incorporan cinco resources y tres relation managers, con etiquetas españolas y columnas redimensionables, reordenables y fijables. Los selectores de fichas reutilizan formularios completos y las autorizaciones de los resources. Se impone el propietario en servidor al crear costes desde la relación de una fase. El alta global y la confirmación de costes también comprueban la autorización de edición de la fase; no permiten eludirla utilizando otro formulario.

Los nuevos flujos operan en EUR. No se incluyen en esta entrega conciliación con factura de compra, transferencias entre almacenes, inventario inicial por ajustes, devoluciones a proveedor ni reparto de portes/gastos adicionales. Una futura conciliación deberá conservar las recepciones y registrar ajustes de valoración, en lugar de modificar costes históricos ya confirmados. El 15 % se aplica a productos; no se inventa automáticamente una tarifa de venta para el trabajo de cada operario.

### Aplicación y verificación

La migración se ha aplicado a MySQL después de crear la copia privada `storage/app/private/backups/pre-project-costing-20261104-173413.sql`. Se ha ejecutado únicamente el seeder de almacén para crear Principal si faltaba. Los operarios, contratistas, productos y albaranes de las pruebas se crean exclusivamente en SQLite `:memory:`, con comprobación de conexión antes de refrescar la base de pruebas.

Las pruebas nuevas cubren conversión de presentaciones, descuento y 15 %, existencias y reservas, cancelación y devolución, inmutabilidad frente a formularios obsoletos, pertenencia a fases, tarifas por vigencia, personal asignado, solapamientos, pausas, cambio de tarifa en mitad del trabajo, precisión de segundos y costes pequeños, límites de importes, fases obligatorias, formularios y confirmaciones Filament, creación contextual de productos y permisos de fase desde formularios anidados y globales.

Se ha aplicado Pint a los archivos modificados; `--dirty` no está disponible porque el directorio no es un repositorio Git, por lo que se han utilizado rutas explícitas. No se han instalado dependencias ni se han iniciado envíos de documentos a servicios externos como parte de esta implementación.

Resultado final de esta entrega: **362 pruebas correctas y 3.433 aserciones** en la suite completa, incluidas **25 pruebas específicas de costes y sus interfaces**. La migración está aplicada como lote 9 y el almacén principal está disponible. Las rutas de albaranes, almacenes, consumos, tarifas y partes se han registrado en el panel.

## 17. Guía operativa en el dashboard — 4 de noviembre de 2026

Se incorpora el widget `GettingStartedGuide` al dashboard existente mediante el descubrimiento de widgets del panel. Ocupa todo el ancho y presenta una guía en español de doce pasos desplegables, con un resumen del recorrido y accesos rápidos. Permite desplegar o plegar todos los pasos y se adapta al modo oscuro y a pantallas pequeñas. El contenido se mantiene en `lang/es/onboarding.php`, separado de la vista y de los enlaces a resources.

El recorrido documentado es: configuración inicial de empresa/contactos/productos; proyecto; capítulos; órdenes, fases y partidas; presupuesto y aceptación manual del proyecto; personal y tarifas; recepción de albaranes; consumos; partes y costes; facturación; borradores/rectificaciones; revisión y cierre manual de fases, órdenes y proyecto. Cada paso indica los menús, pestañas y botones existentes. Los enlaces se generan mediante `Resource::getUrl()` y solo se muestran si el usuario tiene acceso al listado correspondiente.

Se explican las diferencias entre capítulos, fases y partidas, la independencia del presupuesto respecto a las partidas facturables de la orden, el domicilio fiscal del cliente, las reservas de stock/cantidades, los costes confirmados y el significado del cierre. La guía refleja el alcance real: emisión fiscal actualmente de pruebas, cierre de estados manual y ausencia de seguimiento de cobros/conciliación de compras. No promete botones de aceptación, cierre automático o funciones contables que no existen.

Para que el recorrido hacia los consumos y partes de una fase sea navegable, se añade **Abrir fase** en la relación Fases de la orden. Abre la página completa ya existente de la fase; conserva la edición rápida en modal y respeta la autorización de edición del resource. Para llegar a las relaciones de una orden creada desde el proyecto, la guía indica abrir su edición completa desde Obras → Órdenes de trabajo.

No se modifican datos de negocio, migraciones, estados ni cálculos. Se comprueban la presencia de la guía en el dashboard autenticado, los enlaces según permisos y el acceso autorizado a la ficha completa de fase. Se compilan los assets con `npm run build` para incluir los estilos del widget.

Verificación: **21 pruebas afectadas correctas y 197 aserciones**, incluyendo dashboard, permisos, relaciones de fases y regresión de órdenes; compilación Vite correcta. Pint aplicado a los PHP modificados con rutas explícitas al no existir Git.

## 2026-11-05 — Presupuestos manuales, teclado e impresión

Se revisan las skills locales y se aplican las de Laravel, Filament, columnas redimensionables, interfaz y pruebas en los ámbitos afectados. No existe directorio .ai/rules en esta instalación.

Desde Presupuestos → Nuevo presupuesto se selecciona una orden y un nombre para abrir un presupuesto vacío, aunque la orden no tenga mediciones. La selección de orden conserva RecordSelect y su creación contextual. Se comprueba la autorización para crear presupuestos y consultar la orden.

El editor incorpora líneas manuales con descripción/detalles, unidad, cantidad y precio. «Añadir línea» permite empezar; Tab desde el último precio añade otra fila y solicita foco en su descripción, mientras Shift+Tab conserva el retroceso. «Guardar líneas manuales» valida y guarda ese bloque. El botón previo «Guardar presupuesto» conserva el flujo de selección, cantidades y precios de las partidas ya guardadas. Las líneas manuales son instantáneas del presupuesto, identificadas con claves negativas y marca manual; no crean ni modifican mediciones de la orden. La sustitución del bloque manual se realiza bajo bloqueo y transacción, conserva las partidas importadas y rechaza cantidades/precios negativos y precisión superior a seis decimales.

El formulario de albaranes incorpora un repetidor de líneas enlazado a la relación existente. Reutiliza el formulario de producto, presentación, descripción, posición, cantidad, precio y descuento, incluidos los selectores con creación contextual. Tab desde el último descuento solicita una nueva línea y enfoca su primer control; Shift+Tab mantiene la navegación inversa. Se guarda el albarán y sus líneas desde el formulario. Se conserva además el gestor de líneas existente y las protecciones de modelos contra modificación de documentos recibidos.

Se añaden acciones «Imprimir» en presupuestos, órdenes, fases, partes y albaranes. Abren una vista imprimible del registro guardado y permiten imprimir o guardar PDF desde el navegador. Las facturas conservan su impresión fiscal existente. Los documentos nuevos requieren autenticación, acceso al panel y permiso canView del recurso correspondiente. Se imprimen identificadores, contenido del documento y líneas cuando corresponda; los partes muestran persona, fase, horario de Madrid y pausa. Los albaranes muestran precios antes de descuento y no presentan un total neto calculado incorrectamente. Guardar antes de imprimir: la vista lee datos persistidos.

Verificación: 43 pruebas, 347 aserciones, correctas sobre SQLite :memory: (BudgetCreationTest, KeyboardDocumentsTest, CostingResourcesTest y ProjectCostingTest). Incluye creación vacía, líneas manuales y validación, conservación de mediciones, guardado conjunto de varias líneas de albarán y respuestas de impresión. Pint aplicado a los PHP modificados; --dirty no funciona porque este directorio no es un repositorio Git, por lo que se proporcionaron las rutas explícitas. Compilación npm run build correcta. Pendiente comprobación interactiva en navegador del foco y Tab; las pruebas Livewire no ejecutan los manejadores JavaScript.

### 2026-11-05 — Eliminación de líneas del presupuesto

Se corrige la ausencia de controles de eliminación en el editor. Cada fila manual ofrece «Eliminar línea», accesible con teclado; quita la fila del borrador de pantalla y reindexa las restantes. «Guardar líneas manuales» persiste la eliminación, incluso si se quitan todas. Las partidas del árbol también ofrecen «Eliminar línea»: quedan excluidas y «Guardar presupuesto» confirma su eliminación. Se conserva la posibilidad de volver a incluirlas antes de guardar. El guardado de un presupuesto existente admite ahora cero partidas; la creación desde una selección de mediciones mantiene la validación de selección no vacía. Las mediciones originales no se eliminan.

Ambos métodos comprueban canEdit en el servidor. Regresión verificada con eliminación de una fila intermedia, conservación de la siguiente, eliminación de la última fila manual y eliminación de la última partida copiada sin borrar su medición. Resultado: 20 pruebas y 208 aserciones correctas (KeyboardDocumentsTest y BudgetCreationTest), sobre SQLite en memoria. Pint aplicado a rutas explícitas porque no hay repositorio Git para --dirty.

## 2026-11-05 — Revisión integral y plan de cierre funcional

Se crea PLAN.md por petición del usuario tras revisar modelos/migraciones, recursos y formularios, rutas, acceso, servicios de importación/presupuestos/costes/facturación, impresión, documentación y pruebas. Se contrastan los flujos con el ciclo comercial y productivo de una empresa de obras y con fuentes oficiales para los aspectos de acceso, protección de datos, jornada, acreditaciones y facturación.

Conclusión: existe base para una operativa interna supervisada, pero falta cerrar aceptación/versionado de ofertas, conversión a ejecución, avance y certificaciones, subcontratación por importe/medición, gastos y compras completas, tesorería, permisos reales y recuperación operativa. El plan distingue funciones existentes, parciales, ausentes y comprobaciones pendientes, e incluye prioridades, dependencias, decisiones de alcance y escenarios de aceptación. No se fija un porcentaje de completitud ni un calendario de desarrollo sin definir el alcance.

Hallazgos de código prioritarios documentados: los dos guardados de presupuesto recargan y pueden descartar cambios del otro bloque; las líneas manuales aparecen además en el árbol con un segundo estado; no se detectan conflictos de edición obsoleta; no se localizan políticas de autorización por rol/obra; la impresión de albarán calcula importes sin descuento; el paso del precio ofertado a ejecución/factura no está implementado; la emisión de producción permanece bloqueada explícitamente. Se documenta también la necesidad de validar teclado, coma decimal y reglas de vigencia del personal. No se han corregido estos puntos durante el diagnóstico.

Verificación: suite completa, 372 pruebas y 3.526 aserciones correctas, 75,2 segundos, sobre SQLite :memory:. No se han modificado dependencias, migraciones ni código funcional. La consulta de esquema de Boost devolvió SQLite sin tablas, por lo que no certifica el estado de la base operativa. No se han leído secretos ni efectuado envíos fiscales reales. Las pruebas actuales no sustituyen la validación de navegador, MySQL concurrente o restauración de copias indicada en PLAN.md.

## 2026-11-05 — Aplicación del plan: ampliación operativa

### Base de trabajo confirmada

El usuario confirma una empresa en EUR, partes introducidos por empleados y aprobados por encargado, presupuestos por medición o precio cerrado y contabilidad externa mediante CSV. Añade expresamente que pedidos y facturas de compra deben tener líneas. No se configura un programa contable concreto ni una conexión bancaria, no se inventa un destino externo de respaldo y no se habilita la emisión fiscal real.

Se aplican las skills de Laravel, Filament, columnas redimensionables, Tailwind y pruebas. No existe .ai/rules. Versiones: PHP 8.3, Laravel 13.39.0, Filament 5.11.1; sin nuevas dependencias.

### Presupuestos y ejecución

- Guardado único y transaccional de nombre, condiciones, exclusiones, validez, selección y líneas manuales. Se elimina el botón separado de guardado manual.
- Una sola representación editable de cada línea manual; claves de edición estables, vista previa de total con cambios sin guardar y normalización de coma decimal. Se ignora únicamente la fila final vacía sin importes introducidos.
- Huella del documento bloqueada en Livewire para detectar otra sesión: un conflicto conserva el borrador en pantalla y no sobrescribe el documento actual.
- Aviso de cambios sin guardar al intentar salir del navegador.
- Alta de una oferta desde obra sin orden previa. Se mantiene la creación desde mediciones.
- Preparar envío guarda y bloquea la versión, conservando instantáneas comerciales; no envía correo automáticamente. Registrar aceptación exige evidencia escrita.
- Crear revisión conserva el original; Presupuestar extra crea una oferta adicional vinculada.
- Crear ejecución genera una orden nueva y una fase con las cantidades y precios aceptados; repetir la acción devuelve la misma orden. No altera la orden original de mediciones. Las unidades deben existir activas en catálogo.
- La cantidad, descripción y precio de las partidas de ejecución vinculadas a oferta aceptada quedan protegidos frente a cambios directos. Los extras se tramitan mediante su oferta y orden.
- El presupuesto mantiene total sin impuestos; no se declara implementada una oferta con desglose fiscal mixto completo.

### Acceso y trazabilidad

Se introduce BusinessResource y OperationalAccess para los recursos existentes y nuevos, conservando los controles y policies ya presentes. Perfiles: administrador, administración, jefe de obra, encargado y operario. Los usuarios anteriores conservan el perfil administrador al migrar; las nuevas cuentas tienen perfil operario por defecto. El administrador dispone de Usuarios y permisos. Los selectores de cuentas siguen sin creación inline.

Los operarios acceden a sus propios partes, no a costes o tarifas, y no pueden confirmarlos; el encargado puede confirmar los partes de su ámbito. La visibilidad por obra se aplica a recursos, selectores, documentos y exportaciones. El jefe de obra no puede cambiar la configuración fiscal y solo modifica obras de su ámbito. Una cuenta inactiva no accede al panel. Los cambios de perfil/actividad revocan sesiones; no se puede desactivar o degradar la cuenta de la propia sesión.

Se registran eventos de alta, modificación, borrado y descarga con usuario, fecha, entidad, id y nombres de campos cambiados. No se almacenan contraseñas ni valores completos de los cambios. Esta es auditoría básica; no se presenta como un registro forense inalterable o una solución completa de cumplimiento normativo.

### Personal, avance, incidencias y correcciones

Nuevos recursos:
- Ausencias de empleados, con fechas y aprobación; se impiden solapamientos aprobados. Al confirmar un parte se comprueba actividad, fechas laborales y ausencia aprobada.
- Avance y mediciones ejecutadas por partida, con control del acumulado de borradores y confirmados respecto a lo previsto.
- Diario, incidencias, instrucciones, hitos, repasos y garantías por obra, con fecha prevista y cierre/resolución.
- Correcciones de partes confirmados mediante reversión con motivo. Se conserva el original; la reversión confirmada excluye su efecto de los costes y libera su intervalo para el parte corregido.

No se considera implementado un calendario visual con turnos/dependencias/capacidad, un registro laboral completo de jornada o una nómina. La medición ejecutada no genera automáticamente una certificación comercial.

### Compras y subcontratación

Nuevos contratos de subcontrata por obra, tercero, alcance, importe y garantía pactada, con confirmación. Las certificaciones parciales reservan importe y no pueden exceder el contrato. Los extras requieren otro contrato/oferta autorizado.

Los pedidos de compra y facturas recibidas incorporan líneas de producto opcional, descripción, unidad, cantidad, precio, descuento, impuesto, retención fiscal y garantía. La posición se gestiona en el repetidor y permite reordenación con botones. Los totales se calculan desde las líneas con decimales exactos y se recalculan al editar o borrar. No se puede confirmar sin líneas. Los documentos y líneas confirmados son inmutables, también desde un formulario obsoleto.

Se pueden vincular cabeceras y líneas a pedido/albarán. Las recepciones parciales de una línea de pedido comprueban producto, unidad e importe pendiente en cantidad; la unidad de recepción debe coincidir con la línea del pedido. Una factura puede referenciar un contrato o un albarán, con comprobación de obra/proveedor. Los impuestos se introducen según el documento del proveedor; no se propone un porcentaje ficticio.

Las facturas recibidas muestran base, impuestos, retención fiscal, garantía e importe a pagar. Las facturas vinculadas a material ya valorado por consumo o a contrato ya certificado no se suman de nuevo al coste. Los gastos manuales e importes de facturas independientes se incluyen en el coste de obra: no registrar dos veces el mismo justificante. El sistema no realiza todavía conciliación automática de diferencias de precio ni reparto de una misma factura entre varias obras.

### Almacén y costes

- Devoluciones parciales de consumos, con valoración proporcional y absorción del resto de céntimos en la última devolución. Se conserva la devolución completa dejando cantidad vacía.
- Ajustes de inventario positivos/negativos sobre lotes recibidos, salidas por devolución a proveedor y traslados entre almacenes.
- Las salidas pendientes reservan cantidad. Al confirmar traslado se genera una recepción interna en destino con coste de procedencia; no se considera una compra nueva para proponer el último precio de adquisición.
- La valoración al agotar el lote incorpora los ajustes para no cargar a la obra el coste de material devuelto al proveedor o trasladado.
- Gastos de obra por categorías: maquinaria, transporte, desplazamiento, tasas, indirectos y otros, opcionalmente por fase.

La situación económica en la ficha del proyecto separa venta aceptada, material, horas, subcontrata certificada, gastos, facturas independientes, importes pedidos/contratados, facturado, cobrado y pagado. Venta aceptada menos coste incurrido no se etiqueta como margen final: falta el coste futuro de ejecución. Los contratos/pedidos confirmados son compromisos nominales, no una previsión final automática.

### Tesorería y exportación contable

Se registran cobros/pagos parciales con obra, tercero, sentido, método, referencia y factura opcional. Se comprueban coincidencias y saldo, incluidos borradores reservados. Sin factura son anticipos.

Se añaden aplicación de anticipos a facturas posteriores, plazos/vencimientos múltiples y devoluciones de dinero con motivo. Una aplicación no crea otro movimiento de dinero; se limita por saldo del anticipo y de la factura. Las devoluciones no pueden consumir la parte de un anticipo ya aplicada. La situación de caja descuenta las devoluciones confirmadas.

El dashboard ofrece CSV de horas, cabeceras y líneas de facturas recibidas, movimientos, devoluciones y aplicaciones de anticipos. Las exportaciones respetan el ámbito y evitan fórmulas de hoja de cálculo en campos de texto. El contrato final de importación con la asesoría y sus cuentas contables queda por acordar. No hay integración bancaria automática ni liberación automática de garantías.

### Documentación e impresión

Repositorio privado por obra con referencia, tipo, versión y caducidad; admite PDF, imágenes y Excel. Se protege la selección de rutas existentes y la descarga se autoriza por obra. Al confirmar una versión queda bloqueada; su sustitución se archiva con un número de versión superior.

El albarán impreso aplica descuento e importe neto. Las nuevas recepciones guardan el nombre de presentación en su instantánea. Las ofertas imprimen identidad comercial y condiciones; las órdenes incluyen instrucciones, supervisor, asignaciones y fechas. Los pedidos y facturas recibidas tienen impresión de detalle y totales. Es impresión/guardar PDF desde el navegador; no se declara implementada la generación automática y archivo de un PDF firmado.

### Operación técnica y despliegue

operations:backup exporta la base MySQL mediante la conexión de la aplicación, cifra con APP_KEY y guarda checksum/recuentos. Se probó descifrado y comparación. El intento inicial con el cliente mysqldump detectó una cadena TLS autofirmada; se utilizó la conexión PDO de la aplicación, sin desactivar la comprobación TLS del cliente.

La copia se programa a las 02:00 si el scheduler está activo. Solo incluye base de datos: hace falta respaldar archivos y clave de forma separada, establecer retención y destino externo.

operations:verify-backup comprueba integridad y ofrece --restore para una base temporal aislada. El ensayo se intentó y no pudo crear esa base por permisos de la cuenta disponible; no se tocó una base de negocio como alternativa. La restauración sigue sin estar certificada.

operations:check detecta configuración de desarrollo, URL sin HTTPS, tareas fallidas, ausencia de copia reciente y proximidad de caducidad del certificado. En esta instalación detecta APP_ENV distinto de production, APP_DEBUG activo y APP_URL HTTP. No se han sustituido valores sin conocer el dominio y despliegue reales. No se detectaron procesos cron/scheduler o queue:work; podrían depender de orquestación externa.

Se inicializa Git local, se excluyen certificados, claves, configuración privada y datos, y se incorpora CI para pruebas SQLite, compilación y auditoría de dependencias. Falta conectar repositorio remoto y validar el entorno MySQL aislado. README actualizado con despliegue, copias y certificado. No se añaden dependencias.

### Dashboard y guía de usuario

La guía incluye perfil, pendientes autorizados, accesos y exportaciones. Se actualiza la explicación de presupuestos, recepción, corrección de partes, cobros y cierre, y se añaden recorridos de contratación, gastos, documentación, vencimientos, anticipos, movimientos de stock y líneas de compras. No anuncia como disponible la emisión fiscal real ni la automatización pendiente.

### Migraciones y copias aplicadas

Se aplican únicamente migraciones aditivas, sin migrate:fresh en MySQL. Se crean copias cifradas antes de las tandas: operations-20261105-173649.sql.enc, operations-20261105-174426.sql.enc, operations-20261105-175437.sql.enc y operations-20261105-175800.sql.enc; posteriormente operations-20261105-175843.sql.enc incorpora recuentos para ensayo. Se preservan las copias históricas previas.

### Alcance todavía abierto

Este bloque implementa una ampliación operativa extensa, pero no cierra todo PLAN.md. Permanecen: desglose fiscal mixto de ofertas, numeración comercial configurable, biblioteca de partidas/descompuestos editables, planificación visual/dependencias/capacidad, certificación comercial periódica independiente, reparto de compras entre obras, abonos de compra y conciliación automática, inventario inicial sin recepción previa, stock mínimo y caducidad de reservas, garantías económicas completas, coste laboral ampliado, previsión final/margen completo, generación y envío/archivo automático de PDF, registro laboral de jornada, cumplimiento documental personalizado, acceso externo, segundo factor y validación fiscal real.

También quedan condicionados a datos/permisos externos: respaldo fuera del servidor, restauración aislada, dominio y configuración de producción, supervisión real de scheduler/worker, formato final de la asesoría y validación fiscal. No se cambia el bloqueo de emisión real ni se remite información a AEAT.

### Seguimiento adicional de esta entrega

Se añaden descuentos comerciales por línea de presupuesto y se propagan a la ejecución y a las facturas generadas desde fases. El total e impresión reflejan el descuento; no se altera el precio unitario original para ocultarlo. La migración de descuento se aplica tras la copia operations-20261105-181019.sql.enc.

Las ofertas enviadas/aceptadas conservan la consulta de líneas en modo lectura y no permiten borrado ordinario; tampoco se permite borrar una orden derivada de oferta aceptada. Se filtran los pasos del dashboard según los accesos disponibles para cada perfil.

Se comprueba el estado tras migrar: MySQL contiene 70 tablas; se conservan los 2 presupuestos, 384 partidas, 3 órdenes y 1 usuario que había antes del trabajo. No se crean registros ficticios de negocio para demostrar las pantallas. Las pruebas crean sus fixtures exclusivamente en SQLite en memoria.

La suite completa detectó una preparación antigua que intentaba cerrar una orden con fases pendientes. Se actualiza esa preparación para finalizar primero las fases y conservar la prueba de protección frente al borrado. Se mantienen las pruebas anteriores y se amplía cobertura para guardado conjunto, conflicto de edición, aceptación/conversión, perfiles, compras por líneas, pagos/anticipos/plazos, correcciones, documentos privados y movimientos de stock.

### Validación final del bloque aplicado

Resultado final de php artisan test --compact: **393 pruebas correctas, 3.688 aserciones**, 79,2 segundos, con SQLite :memory: forzado. Formato aplicado con Pint, compilación Vite correcta y vistas Blade compiladas. El aviso de fontaine es opcional y no impide compilar; no se añade esa dependencia.

El plan sigue abierto: no se presenta la ampliación como terminación del 100 % de sus apartados. Los pendientes funcionales y los dependientes de infraestructura/validación externa están separados en PLAN.md, apartado 16, y en la guía operativa. Se prepara una instantánea local de código en Git excluyendo claves, certificados, .env, datos privados y compilados. No se publica ni se envía el repositorio a un servicio externo.

## 2026-11-05 — Navegación por áreas e iconos Tabler

Se reorganiza el panel con clusters de Filament 5, manteniendo los recursos en sus carpetas actuales. El menú principal contiene el dashboard, seis áreas y Contactos como acceso directo. Al abrir un área aparece su menú lateral propio:

- Obras: Preparación (proyectos y presupuestos), Ejecución (órdenes, fases y avance), Seguimiento (diario/incidencias, documentos, gastos y consumos).
- Compras y almacén: Compras (pedidos → albaranes → facturas recibidas), Subcontratas (contratos y certificaciones), Almacén (almacenes y movimientos).
- Equipo: Personal (empleados y ausencias), Trabajo y costes (partes, correcciones y tarifas horarias).
- Ventas y cobros: Ventas (facturas emitidas), Tesorería (movimientos, vencimientos, anticipos y devoluciones).
- Catálogo: productos, tarifas y unidades.
- Configuración: empresa y condiciones comerciales, fiscalidad y series, usuarios y auditoría.

Los 50 recursos tienen iconos distintos del enum Daljo25\FilamentTablerIcons\Enums\TablerIcon, incluidos los auxiliares no publicados en el menú. Se utiliza la versión instalada 3.50.0; no se añaden dependencias. Los recursos auxiliares siguen abriéndose desde sus relaciones y no se publican entradas nuevas solo por asignarles icono.

OperationalArea conserva los prefijos y nombres de ruta originales al agrupar recursos en clusters. Así continúan funcionando los favoritos, enlaces de documentos y acciones que utilizan las rutas anteriores. Las entradas de área solo aparecen cuando existe algún recurso accesible; un operario accede a Equipo y su listado de partes.

Se actualiza la guía del dashboard con la explicación de los dos niveles y las ubicaciones de partes, correcciones, fiscalidad y series. No hay migraciones ni cambios de datos o permisos.

Verificación de navegación: 89 pruebas correctas y 1.767 aserciones (NavigationAreasTest, FilamentResourcesTest, OperationsManagementTest, DashboardGuideTest y OperationalAccessTest). Se comprueba el menú principal de ocho entradas, submenús de área, iconos Tabler únicos, conservación de rutas y visibilidad del operario. Pint y compilación de vistas correctos. No se ha realizado una revisión visual interactiva en navegador.

### Submenús de área horizontales — 5 de noviembre de 2026

Se configura SubNavigationPosition::Top en OperationalArea para todos los clusters: las opciones de cada área aparecen como navegación horizontal sobre el contenido, conservando los grupos, iconos, permisos y rutas. El menú principal permanece lateral y se elimina la columna lateral adicional de subnavegación. Se actualiza la explicación del dashboard. Se utiliza la opción nativa de Filament 5.11.1, sin plantillas sobrescritas ni dependencias nuevas. No hay cambios de datos.

### Corrección de clics en desplegables horizontales — 5 de noviembre de 2026

Se identifica un conflicto de apilamiento con resized-column: la barra de herramientas de la tabla tiene z-index 21, por encima del panel desplegable estándar. El tema sitúa la navegación superior del cluster, tanto pestañas como selector móvil, en una capa relativa 22. Queda por encima de la tabla y por debajo de la barra principal de Filament (30) y de los modales. No se crea un contexto de aislamiento sobre todo el contenido, que podría atrapar overlays de tablas. No se modifican archivos de vendor ni se desactivan acciones de la tabla.

Tema recompilado con npm run build. Las pruebas de navegación comprueban renderizado, permisos y rutas; no ejecutan clics en navegador. No se añade una prueba PHP para una corrección exclusivamente CSS.

### Desplegables fuera del contenedor desplazable — 5 de noviembre de 2026

La corrección previa solo con z-index provocaba un scroll interno: fi-tabs usa overflow-x:auto y atrapaba el panel dentro de su contenedor al posicionar la barra. Se retira ese posicionamiento y su z-index. Las vistas de subnavegación horizontal y móvil se adaptan en resources/views/vendor/filament-panels/components/page/sub-navigation para activar la opción nativa teleport del dropdown. El panel se dibuja fuera del contenedor desplazable y se identifica con una clase interna para aplicar z-index 25 solo a esos menús, por encima de la barra de tablas (21) y por debajo del chrome y modales. Se conserva el comportamiento nativo de apertura, foco, enlaces y permisos; no se modifican archivos de vendor.

Se recompila el tema y se limpia la caché de vistas. Se añade regresión de renderizado que exige teleport en los paneles de navegación; no sustituye una comprobación interactiva de los clics en navegador.

### Corrección de líneas de albarán creadas desde relation manager — 5 de noviembre de 2026

Se identifica el conflicto entre dos editores de la misma relación: el formulario de cabecera cargaba lines vacío; el relation manager insertaba una línea y, al confirmar, save() del formulario desactualizado podía eliminarla antes de llamar a recepción. La prueba anterior montaba el formulario después de crear la línea y no cubría este orden real de acciones.

Ahora las altas, modificaciones y borrados del relation manager notifican al formulario propietario. Si sus líneas no tienen cambios pendientes, se recargan únicamente esas líneas sin tocar la cabecera. Si hay un borrador local, se conserva y se muestra un aviso. Una huella bloqueada y una comprobación bajo bloqueo antes de guardar impiden que un formulario desactualizado sobrescriba las líneas de otro componente/sesión. La acción «Recargar líneas guardadas» requiere confirmación y explica que sustituye solo ese bloque.

La confirmación usa una transacción que incluye el guardado previo y la recepción. Si falla la recepción, se revierten los cambios de base y se restaura el borrador de formulario para poder corregir y reintentar. Se suprime el aviso prematuro de Guardado durante esa confirmación. No se omite el requisito de tener al menos una línea ni se inventan productos/cantidades.

Regresiones: formulario abierto antes de crear línea desde la relación, notificación al padre, protección frente a una petición desactualizada, conservación de filas locales pendientes, guardado de líneas nuevas directamente desde el formulario, rollback de una recepción fallida y reintento tras corregir su fecha. Resultado: 37 pruebas correctas y 302 aserciones (CostingResourcesTest, KeyboardDocumentsTest y ProjectCostingTest), en SQLite :memory:. Pint aplicado y caché de vistas limpiada.

Comprobación de solo lectura posterior en la instalación operativa: el albarán 1 ya consta received con una línea guardada. La confirmación no se ejecutó desde el diagnóstico ni se alteraron sus datos manualmente.

### Ausencias: listado y edición de referencia/motivo — 5 de noviembre de 2026

El listado de Ausencias y calendario deja de utilizar solo las columnas genéricas: muestra empleado, referencia, descripción/motivo completa con ajuste de línea, desde, hasta y estado. Se carga employee de forma anticipada y se permite buscar por empleado y descripción. Se mantienen las columnas redimensionables, reordenables y fijables, los filtros y las acciones.

Se comprueba que la ausencia 1 está confirmada; el bloqueo anterior de todo registro confirmado impedía guardar incluso su referencia y descripción. Se permite corregir exclusivamente esos metadatos en ausencias confirmadas. El selector de empleado y las fechas se deshabilitan en ese estado, y la capa de persistencia también rechaza su cambio, incluido un intento directo que evite el formulario. No se aplica esta excepción a los documentos económicos ni se permite editar ausencias canceladas. La auditoría conserva las correcciones.

Las acciones compartidas de edición y consulta reciben autorización explícita del recurso. Confirmar y Cancelar pendiente exigen estado draft para no ofrecer reconfirmación/cancelación de una ausencia ya aprobada. La guía del dashboard explica la excepción de referencia/motivo.

Pruebas: listado con empleado/motivo/fechas y búsqueda, edición real desde el modal en borrador y confirmada, protección de fechas y ocultación de acciones no válidas. Resultado: 14 pruebas correctas, 108 aserciones (EmployeeAbsenceResourceTest, OperationsManagementTest y PurchaseDocumentLinesTest). Pint aplicado y caché de vistas limpiada. Sin migraciones ni modificación manual de ausencias reales.
