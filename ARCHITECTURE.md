# Arquitectura conversacional

## Fuente de conocimiento

Los archivos de `rag_dinners/` son la fuente editable de producto. `KnowledgeBase` los consulta en cada pregunta y devuelve solo fragmentos relevantes. El bot debe reconocer cuando el RAG no contiene un dato y no inventarlo.

## Motor conversacional

`IntentAnalyzer` permite varias intenciones por turno y extrae entidades como preferencia de viaje o tarjeta. Si el entorno contiene `GROQ_API_KEY`, usa Groq con salida JSON; si no, aplica una clasificacion local para mantener el servicio operativo.

`ConversationEngine` conserva memoria de sesion, decide la prioridad de la respuesta y solo inicia el registro de datos despues de una intencion explicita de solicitud.

## Verificacion

`tests/conversation_engine_test.php` cubre el caso de una preferencia y una consulta de tasas en el mismo turno, ademas del cierre respetuoso ante rechazo.

## Voz

La futura capa STT/TTS debe enviar su transcripcion a `api_chat.php` y reproducir el campo `response`. La logica, memoria y RAG son compartidos por texto y voz.
