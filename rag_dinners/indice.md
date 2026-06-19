# RAG DINNERS - Índice de Secciones

## Archivos del Sistema RAG

| Archivo | Secciones | Tags |
|---------|-----------|------|
| **producto.md** | tipos_tarjeta, puntos_rewards, limites_credito, seguros, tecnologia, app | producto, tarjetas, puntos |
| **tasas.md** | tea, tem, comisiones, cuotas_sin_interes, diferido, periodo_gracia, pago_minimo | tasas, comisiones, financiamiento |
| **requisitos.md** | requisitos_generales, documentacion, proceso_solicitud, score, entrega, activacion | requisitos, solicitud, proceso |
| **beneficios.md** | classic, gold, platinum, black, comparativa, app, seguridad | beneficios, comparativa, caracteristicas |
| **promociones.md** | bienvenida, temporada, permanentes, restaurantes, cines, supermercados, combustible, tecnologia, viajes, experiencias | promociones, descuentos, cuotas |
| **faq.md** | general, solicitud, puntos, tarjeta, pagos, linea_credito, promociones | faq, preguntas, respuestas |
| **script_ventas.md** | saludo, apertura, descubrimiento, presentacion, objeciones, cierre, seguimiento | ventas, script, conversacion |

## Tags por Intención

| Intención del Usuario | Tags a Buscar | Archivos Primarios |
|-----------------------|---------------|-------------------|
| "Hola" / Saludo | saludo, apertura | script_ventas.md |
| "¿Qué es DINNERS?" | descripcion, tipos_tarjeta | producto.md |
| "¿Qué tarjeta me conviene?" | tipos_tarjeta, comparativa, ingreso_minimo | producto.md, beneficios.md |
| "¿Cuánto cuesta?" | cuota_anual, comisiones | tasas.md, beneficios.md |
| "¿Qué beneficios tiene?" | beneficios, puntos, seguros | beneficios.md, producto.md |
| "¿Cómo solicito?" | proceso_solicitud, requisitos, documentacion | requisitos.md |
| "¿Cuáles son los requisitos?" | requisitos_generales, documentacion | requisitos.md |
| "¿Qué tasa de interés tiene?" | tea, tem | tasas.md |
| "¿Hay cuotas sin interés?" | cuotas_sin_interes, comercios | tasas.md, promociones.md |
| "¿Dónde puedo usarla?" | uso, comercios | producto.md, faq.md |
| "¿Cómo acumulo puntos?" | puntos_rewards, acumulacion | producto.md |
| "¿Dónde canjeo puntos?" | canje, puntos | producto.md |
| "¿Tiene seguro?" | seguros, cobertura | producto.md, beneficios.md |
| "¿Qué promociones hay?" | promociones, descuentos, cuotas | promociones.md |
| "¿Hay promociones en restaurantes?" | restaurantes, descuentos | promociones.md |
| "¿Puedo usarla en el extranjero?" | extranjero, uso | faq.md |
| "¿Cómo pago?" | pagos, metodos | faq.md |
| "¿Qué pasa si no pago?" | pago_tardio, mora | tasas.md, faq.md |
| "¿Puedo aumentar mi línea?" | linea_credito, aumento | producto.md, faq.md |
| "¿Es segura?" | seguridad, fraude | beneficios.md, faq.md |
| "No me interesa" / Rechazo | objeciones, seguimiento | script_ventas.md |
| "Dame más información" | seguimiento, informacion | script_ventas.md |

## Reglas de Búsqueda RAG

1. **Múltiples tags**: Si el usuario pregunta algo complejo, buscar en múltiples archivos
2. **Prioridad**: script_ventas.md > producto.md > beneficios.md > tasas.md > requisitos.md > promociones.md > faq.md
3. **Contexto**: Mantener historial de conversación para no repetir información
4. **Transparencia**: Siempre mostrar qué secciones del RAG se usaron para responder

## Ejemplos de Uso

### Ejemplo 1: Saludo
**Usuario**: "Hola"
**Tags**: `saludo, apertura`
**Archivos**: `script_ventas.md`
**Secciones**: `saludo_inicial, apertura_conversacion`

### Ejemplo 2: Pregunta sobre tarjeta
**Usuario**: "¿Qué tarjeta me recomiendas si gano 8000 soles?"
**Tags**: `tipos_tarjeta, comparativa, ingreso_minimo`
**Archivos**: `producto.md, beneficios.md`
**Secciones**: `tipos_tarjeta, comparativa_completa`

### Ejemplo 3: Pregunta compleja
**Usuario**: "¿Cuánto me cuesta y qué beneficios tengo?"
**Tags**: `cuota_anual, comisiones, beneficios, puntos`
**Archivos**: `tasas.md, beneficios.md, producto.md`
**Secciones**: `comisiones, comparativa, puntos_rewards`

---

*Índice RAG DINNERS. Usar para búsquedas y transparencia.*
