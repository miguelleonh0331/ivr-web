# Flujo de Conversación - IVR Demo

## Diagrama de Decisiones del Agente

```
INICIO
  │
  ▼
┌─────────────────────┐
│   SALUDO + MENÚ     │
│  "Hola, bienvenido" │
└─────────────────────┘
  │
  ▼
┌─────────────────────────────────────┐
│  ¿El cliente quiere préstamo?       │
│  (detectar intención: "préstamo",   │
│   "dinero", "crédito", "solicitar") │
└─────────────────────────────────────┘
  │ Sí                    │ No
  ▼                       ▼
┌─────────────┐    ┌─────────────────────┐
│  PASO 1:    │    │  ¿Otra consulta?    │
│ IDENTIFICAR │    │  (tasas, estado,     │
│   CLIENTE   │    │   asesor humano)    │
└─────────────┘    └─────────────────────┘
  │                       │
  ▼                       │
┌─────────────────────┐   │
│ Solicitar datos:    │   │
│ - Nombre            │   │
│ - DNI               │   │
│ - Celular           │   │
│ - Correo            │   │
└─────────────────────┘   │
  │                       │
  ▼                       │
┌─────────────────────┐   │
│  ¿Datos completos?  │   │
└─────────────────────┘   │
  │ Sí        │ No      │
  ▼           ▼           │
┌────────┐  ┌──────────┐  │
│ PASO 2 │  │ Repetir  │  │
│ LABORAL│  │ pregunta │  │
└────────┘  │ faltante │  │
  │         └──────────┘  │
  ▼                       │
┌─────────────────────┐   │
│ Solicitar:          │   │
│ - Tipo trabajo      │   │
│ - Empresa           │   │
│ - Antigüedad        │   │
│ - Ingreso mensual   │   │
└─────────────────────┘   │
  │                       │
  ▼                       │
┌─────────────────────┐   │
│  ¿Ingreso >= S/1200?│   │
└─────────────────────┘   │
  │ Sí        │ No      │
  ▼           ▼           │
┌────────┐  ┌──────────┐  │
│ PASO 3 │  │ "Lamento,│  │
│PRÉSTAMO│  │ mínimo   │  │
└────────┘  │ S/1,200" │  │
  │         └──────────┘  │
  ▼                       │
┌─────────────────────┐   │
│ Solicitar:          │   │
│ - Monto deseado     │   │
│ - Plazo deseado     │   │
│ - Destino (opcional)│   │
└─────────────────────┘   │
  │                       │
  ▼                       │
┌─────────────────────┐   │
│  CALCULAR CUOTA     │   │
│  (usar tasas del MD)│   │
└─────────────────────┘   │
  │                       │
  ▼                       │
┌─────────────────────┐   │
│ "Su cuota sería     │   │
│  aproximadamente    │   │
│  S/XXX al mes"      │   │
└─────────────────────┘   │
  │                       │
  ▼                       │
┌─────────────────────┐   │
│  ¿Cliente acepta?   │   │
└─────────────────────┘   │
  │ Sí        │ No      │
  ▼           ▼           │
┌────────┐  ┌──────────┐  │
│GUARDAR │  │ "¿Desea  │  │
│DATOS   │  │ ajustar  │  │
│+ CIERRE│  │ monto/   │  │
│        │  │ plazo?"  │  │
└────────┘  └──────────┘  │
  │                       │
  ▼                       │
┌─────────────────────┐   │
│ "¡Preliminarmente   │   │
│  aprobado! Asesor   │   │
│  le contactará"     │   │
└─────────────────────┘   │
  │                       │
  ▼                       │
┌─────────────────────┐   │
│      FIN            │◄──┘
│  "¿Algo más?"       │
└─────────────────────┘
```

---

## Estados del Agente

| Estado | Descripción | Próximo paso |
|--------|-------------|--------------|
| `inicio` | Saludo + presentación menú | Esperar intención |
| `identificacion` | Solicitando datos personales | Guardar en MD cliente |
| `laboral` | Solicitando info laboral | Validar ingreso mínimo |
| `prestamo` | Solicitando monto/plazo | Calcular cuota estimada |
| `confirmacion` | Presentando cuota, esperando sí/no | Guardar o ajustar |
| `cierre` | Mensaje final + despedida | Fin o reinicio |
| `escalado` | Transferir a humano | Fin |

---

## Intenciones Detectables

| Intención | Palabras clave | Acción |
|-----------|---------------|--------|
| `solicitar_prestamo` | préstamo, crédito, dinero, solicitar, pedir | Ir a paso 1 |
| `consultar_tasas` | tasa, interés, cuota, porcentaje, comisión | Leer `base_conocimiento.md` |
| `estado_solicitud` | estado, seguimiento, dónde va, aprobado | Pedir DNI para buscar |
| `hablar_asesor` | humano, persona, asesor, agente, real | Escalar |
| `despedida` | chau, adiós, gracias, hasta luego | Cierre amable |

---

## Reglas de Negocio

1. **Ingreso mínimo**: Si ingreso < S/ 1,200 → mensaje de rechazo amable + ofrecer asesor
2. **Monto máximo**: Si monto > S/ 100,000 → "Monto máximo es S/ 100,000. ¿Desea ese monto?"
3. **Plazo mínimo**: 6 meses. Si plazo < 6 → "Plazo mínimo es 6 meses"
4. **Plazo máximo**: 60 meses. Si plazo > 60 → "Plazo máximo es 60 meses"
5. **Scoring automático**: Ingreso / 3 = cuota máxima aceptable. Si cuota calculada > cuota máxima → "Para ese monto/plazo necesitaríamos evaluar adicionalmente"

---

*Flujo vivo. Ajustar según evolución del producto.*
