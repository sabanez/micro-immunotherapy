# micro-immunotherapy

Mu-plugins del sitio WordPress [micro-immunotherapy.com](https://www.micro-immunotherapy.com), la web internacional de INT2020.

Este repositorio no contiene la instalación completa de WordPress (core, tema, uploads, etc.), solo los `mu-plugins` (must-use plugins) propios del proyecto, que es lo único de la instalación que requiere control de versiones y despliegue manual.

## Estructura

```
wp-content/mu-plugins/
├── 0-worker.php          # Loader de ManageWP Worker (generado automáticamente)
├── rw-swpm-sync.php      # Sincroniza los socios de SWPM con los usuarios/meta de WooCommerce
└── swpm-protected-docs.php  # Protege documentos (PDF) bajo wp-content/uploads exigiendo sesión de socio SWPM
```

## swpm-protected-docs.php

Restringe la descarga de una lista de documentos (definida en `labolife_swpm_protected_documents()`) alojados en `wp-content/uploads/` sin necesidad de moverlos fuera de la carpeta de subidas habitual.

Si un visitante sin sesión de socio (Simple WordPress Membership) intenta acceder a uno de esos documentos:

1. Se le redirige a la página de login (`/professional-area/`) con el documento original codificado en el parámetro `swpm_redirect_to`.
2. Si ya tiene cuenta y se loguea, el addon "After Login Redirection" de SWPM lee ese parámetro y le lleva directo al documento.
3. Si no tiene cuenta, el botón "Register" de esa página (enlace estático) se reescribe por JS para propagar `swpm_redirect_to`; al completar el registro, un filtro sobre `swpm_after_registration_redirect_url` le lleva igualmente al documento en vez de a la URL fija configurada en los ajustes de SWPM.

## rw-swpm-sync.php

Puente entre Simple Membership (SWPM) y WooCommerce: cuando se crea o actualiza un socio en SWPM, crea (si hace falta) el usuario de WordPress correspondiente y sincroniza sus datos de facturación (`billing_*`) en los user meta de WooCommerce.

## Despliegue

Los ficheros de `wp-content/mu-plugins/` se despliegan copiándolos tal cual a la carpeta `wp-content/mu-plugins/` del servidor. WordPress carga automáticamente cualquier `.php` en el nivel raíz de esa carpeta, sin necesidad de activación.

## Ramas

- `main`: versión estable, desplegada en producción.
- `develop`: rama de desarrollo.

Ver [CHANGELOG.md](./CHANGELOG.md) para el historial de cambios.
