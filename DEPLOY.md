# Despliegue de IVR Web DINNERS

## Fuente de verdad

- Repositorio: `git@github.com:miguelleonh0331/ivr-web.git`
- Rama: `main`
- Copia local: `E:\Proyectos\ivr demo`
- VPS Legacy: `/var/www/html/ivr-web`
- URL: `http://192.175.22.83/ivr-web/`

## Actualizar la VPS

```bash
git -C /var/www/html/ivr-web pull --ff-only
```

## Validar el backend

```bash
php -l /var/www/html/ivr-web/web/api_chat.php
php -l /var/www/html/ivr-web/web/api_transcribe.php
```

## Seguridad

- No guardar claves de Groq en archivos versionados ni en el navegador.
- La transcripcion de voz usa `web/api_transcribe.php` y requiere `GROQ_API_KEY` configurada en el entorno del servidor.
- El chat comercial funciona sin exponer claves y conserva su estado en archivos temporales del servidor.
