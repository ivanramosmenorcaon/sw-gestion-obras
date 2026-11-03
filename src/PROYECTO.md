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
| Modelos y Filament | 25 modelos, 23 resources y 18 relation managers para las tablas existentes; interfaz en español. Véase apartado 12. |

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



