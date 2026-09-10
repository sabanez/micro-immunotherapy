# Changelog

Todos los cambios relevantes de los mu-plugins de micro-immunotherapy.com se documentan en este fichero.

## [Unreleased]

### Added
- Redirección automática al recurso solicitado tras iniciar sesión o registrarse: cuando se intenta acceder a un documento protegido sin sesión activa, `swpm-protected-docs.php` redirige a la página de login (`/professional-area/`) con el documento original en el parámetro `swpm_redirect_to`.
  - Tras un login correcto, el addon "After Login Redirection" de SWPM lee ese parámetro y lleva al socio directo al documento.
  - Tras un registro correcto (sin activación por email), un nuevo filtro sobre `swpm_after_registration_redirect_url` usa `swpm_redirect_to` en vez de la URL fija configurada en los ajustes de SWPM.
  - El botón "Register" de la página de login (enlace estático a la página de registro) se reescribe por JS en `wp_footer` para propagar `swpm_redirect_to`, de modo que también funcione para usuarios que aún no tienen cuenta.
