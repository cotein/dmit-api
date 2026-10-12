# `vouchers.inscription_id`: qué significa y qué agujero queda

Nota dejada al correr los seeders (ver `correr.php`, en este mismo directorio). No
se tocó el esquema ni se agregó ninguna tabla: esto es sólo la constancia que pidió
el dueño para poder decidir.

## Qué es la columna

`vouchers.inscription_id` dice **qué tipo de comprobante puede emitir cada tipo de
sujeto**: para qué condición frente al IVA del emisor es válido ese comprobante.
Texto del dueño:

> «esto vouchers.inscription_id era para saber qué tipo de vouchers cargar a qué
> tipo de sujeto. ¿para qué cargar un comprobante B si el emisor es monotributista,
> verdad?»

Apunta a `afip_inscriptions.id`, que es el **código de ARCA** de la condición
(`1` = IVA Responsable Inscripto, `6` = Responsable Monotributo, `5` = Consumidor
Final, y así con los 16 que carga `AfipInscriptionSeeder`).

**`NULL` hoy significa «sin restricción»**, no «no se sabe»: el consumidor de la
columna la lee como «este comprobante no tiene emisor restringido». Ese es el
agujero de fondo — ver más abajo.

## Qué quedó cargado, y la discrepancia

`DatabaseSeeder` llama a DOS seeders que escriben `vouchers`, en este orden:

1. `AfipVouchersTableSeeder` (línea 32) — `TRUNCATE` + 102 filas.
2. `VouchersTableSeeder` (línea 44) — `DELETE` + 102 filas.

**El segundo corre último y pisa al primero.** Los dos traen `inscription_id`
distinto para varios códigos, así que el estado final lo decide `VouchersTableSeeder`:

| | `AfipVouchersTableSeeder` | `VouchersTableSeeder` (el que queda) |
|---|---|---|
| A 001–003 | 1 | 1 |
| A 004–005 | 1 | `NULL` |
| B 006–008 | `NULL` | **5** |
| B 009–010 | `NULL` | `NULL` |
| C 011–013 | 6 | 6 |
| C 015–016 | 6 | `NULL` |

Los dos seeders difieren en **18 filas** (ids 4, 5, 6, 7, 8, 14, 15, 52, 53, 66, 75,
76, 78, 79, 81, 82, 83, 84).

La lectura del dueño («A = 1, C = 6, B en NULL») describe **sólo** a
`AfipVouchersTableSeeder`. En el estado final de `db:seed`, que es el que corre
`DatabaseSeeder`, las A 004–005 y las C 015–016 quedan en `NULL` y las B 006–008
quedan en **5** (Consumidor Final).

Ninguna de las dos lecturas es «la buena» por sí sola: la de `VouchersTableSeeder`
es más informativa (las B son las que se le emiten al consumidor final), pero deja
las A 004–005 y las C 015–016 sin restricción.

## El agujero

Una sola columna no puede expresar la regla completa:

- **La clase C la emiten tres condiciones distintas**: Responsable Monotributo
  (`6`), IVA Sujeto Exento (`4`) e IVA No Alcanzado (`15`). La columna guarda un
  único valor, así que hoy `vouchers.inscription_id = 6` dice «monotributista» y
  deja afuera a los otros dos, que también emiten C.
- **`NULL` es ambiguo**: significa «sin restricción», así que un emisor
  monotributista ve la Factura B (que quedó en 5, consumidor final) y las A 004–005
  y C 015–016 sin ninguna restricción.

## Opciones (para que decida el dueño)

| Opción | Qué implica | Costo |
|---|---|---|
| **A. Dejarlo como está** | La columna sigue siendo un valor único y `NULL` sigue significando «sin restricción». Es el estado actual. | No cierra el agujero: el emisor monotributista sigue viendo comprobantes que no le corresponden. |
| **B. Repetir el comprobante por condición** | Una fila de `vouchers` por cada condición que puede emitirlo (la C pasaría a tener tres filas, una con 6, una con 4 y una con 15). | Multiplica filas y **rompe el frontend**: `useComprobantesValidosComposable` (en `dmit-facturador`) resuelve por `afip_code` y habría códigos duplicados. Descartada. |
| **C. Tabla intermedia `voucher_inscripcion`** | Muchos-a-muchos: qué condiciones pueden emitir cada comprobante. La regla completa se expresa sin ambigüedad y `NULL` deja de hacer falta. | Requiere migración, y hay que migrar el código que hoy lee `vouchers.inscription_id`; `ConfiguracionDatosBase` deja `inscription_id` fuera de la lista blanca del importador, así que el importador no la tocaría. |
| **D. Lista de valores en la misma columna** | Guardar `6,4,15` como texto y que el consumidor lo parsee. | Convierte una columna entera en un campo de texto sin tipo y hay que tocar a todos los consumidores igual que en C. Peor que C. |

**Recomendación: C**, y no ahora. Es la única que expresa la regla sin romper el
frontend, pero no es una decisión que corresponda tomar desde acá: pide migración y
coordinar con `dmit-facturador` primero.

## Lo que NO se hizo

- No se cambió el esquema ni se agregó ninguna tabla.
- No se renumeraron ni se cambiaron los `id` de `vouchers` a mano: los que están
  son los que quedaron del seeder, y `orders.voucher_id` tiene **0 filas**, así que
  no hay ninguna referencia colgando.
