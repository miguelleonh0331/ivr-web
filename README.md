# IVR Demo - Proyecto de Agente de Voz Autónomo para Préstamos

## Información General

- **Nombre del proyecto**: `ivr-demo`
- **Descripción**: Agente de voz autónomo para atención de préstamos con voz bidireccional (STT/TTS) usando Groq
- **Fecha de creación**: 2026-06-18
- **Autor**: Diego

---

## Rutas del Proyecto

### Local (Windows)

| Ruta | Descripción |
|------|-------------|
| `E:\Proyectos\ivr demo\` | Carpeta raíz del proyecto local |
| `E:\Proyectos\ivr demo\base_conocimiento.md` | Script de venta, tasas, FAQ |
| `E:\Proyectos\ivr demo\flujo_conversacion.md` | Árbol de decisiones del agente |
| `E:\Proyectos\ivr demo\datos_clientes\` | Registros de interacciones con clientes |
| `E:\Proyectos\ivr demo\config.md` | API keys, modelos, parámetros |
| `E:\Proyectos\ivr demo\src\` | Código fuente del agente |
| `E:\Proyectos\ivr demo\web\` | Archivos web (HTML, CSS, JS) |

### VPS (Servidor Legacy)

| Ruta | Descripción |
|------|-------------|
| `/var/www/html/ivr-web/` | Carpeta raíz del proyecto en VPS |
| `/var/www/html/ivr-web/index.html` | Interfaz web principal |
| `/var/www/html/ivr-web/api/` | Endpoints del backend |
| `/var/www/html/ivr-web/assets/` | Recursos estáticos |

---

## Servidor VPS

| Parámetro | Valor |
|-----------|-------|
| **Nombre** | legacy |
| **IP** | 192.175.22.83 |
| **Usuario SSH** | root |
| **Autenticación** | Llave SSH |
| **Sistema** | Ubuntu |
| **Apache user** | www-data:www-data |
| **Ruta web** | `/var/www/html/` |

---

## Archivos del Proyecto

### Estructura Local (`E:\Proyectos\ivr demo\`)

```
ivr demo/
├── base_conocimiento.md          # Script de venta + tasas + FAQ
├── flujo_conversacion.md         # Árbol de decisiones del agente
├── config.md                     # API keys, modelos, parámetros
├── README.md                     # Este archivo
├── datos_clientes/
│   └── .gitkeep                  # Clientes: cliente_001.md, etc.
├── src/
│   ├── agente.py                 # Lógica principal del agente
│   ├── stt_groq.py               # Transcripción voz→texto (Groq Whisper)
│   ├── tts_engine.py             # Texto→voz (edge-tts u otro)
│   ├── base_md.py                # Lector/escritor de base de conocimiento MD
│   └── utils.py                  # Funciones auxiliares
└── web/
    ├── index.html                # Interfaz web principal
    ├── css/
    │   └── styles.css
    └── js/
        ├── app.js                # Lógica frontend
        └── audio.js              # Manejo de micrófono y audio
```

### Estructura VPS (`/var/www/html/ivr-web/`)

```
ivr-web/
├── index.html                    # Interfaz web principal
├── api/
│   ├── chat.php                  # Endpoint de conversación
│   ├── guardar_cliente.php       # Guardar datos de cliente
│   └── obtener_tasas.php         # Endpoint para tasas actuales
├── assets/
│   ├── css/
│   │   └── styles.css
│   ├── js/
│   │   ├── app.js
│   │   └── audio.js
│   └── audio/
│       └── .gitkeep              # Archivos de audio generados
└── base/
    └── base_conocimiento.md      # Copia sincronizada de la base de conocimiento
```

---

## Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| **STT (Voz → Texto)** | Groq API (Whisper) |
| **LLM (Cerebro)** | Groq API (Llama 3 / Mixtral) |
| **TTS (Texto → Voz)** | edge-tts (Microsoft Edge) o alternativa |
| **Backend** | Python (Flask/FastAPI) o PHP |
| **Frontend** | HTML5 + JavaScript (WebRTC para micrófono) |
| **Base de conocimiento** | Markdown local (sincronizado) |
| **Datos clientes** | Markdown / JSON |

---

## Comandos de Sincronización

### Subir cambios local → VPS

```bash
cd "C:\Users\Diego\Documents\herramientas\codex multi"
python sync_project.py --local "E:/Proyectos/ivr demo" --remote "/var/www/html/ivr-web" --server legacy --dry-run
python sync_project.py --local "E:/Proyectos/ivr demo" --remote "/var/www/html/ivr-web" --server legacy
```

### Crear carpeta en VPS (primera vez)

```bash
cd "C:\Users\Diego\Documents\herramientas\codex multi"
python ssh_cmd.py "mkdir -p /var/www/html/ivr-web && chown -R www-data:www-data /var/www/html/ivr-web"
```

---

## Flujo de Trabajo

1. **Desarrollar localmente** en `E:\Proyectos\ivr demo\`
2. **Probar** en entorno local
3. **Sincronizar** al VPS con `sync_project.py`
4. **Verificar** en web: `http://192.175.22.83/ivr-web/`
5. **Ajustar permisos** si es necesario: `chown -R www-data:www-data /var/www/html/ivr-web`

---

## Notas

- La base de conocimiento en Markdown es **viva**: el agente la consulta y puede actualizarla con datos de clientes
- Los datos de clientes se guardan en archivos `.md` individuales en `datos_clientes/`
- La sincronización debe mantener la base de conocimiento actualizada en ambos lados
- **Importante**: No exponer API keys en el frontend. Usar backend como proxy.

---

*Última actualización: 2026-06-18*
