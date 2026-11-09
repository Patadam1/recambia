# Recambia · web

Web de solicitud de recambios. HTML, CSS y JS en el navegador; PHP 8 y MySQL en Hostinger.

## Qué hay en cada carpeta

| Ruta | Qué es |
|---|---|
| `index.html` | Portada con el formulario de 3 pasos |
| `gracias.html` | Página de confirmación, solo si el navegador no tiene JavaScript |
| `aviso-legal.html`, `privacidad.html`, `cookies.html`, `condiciones.html` | Páginas legales con huecos `[EN AMARILLO]` por rellenar |
| `assets/` | Estilos, JavaScript e icono |
| `api/solicitud.php` | Recibe el formulario, guarda la solicitud y envía los dos correos |
| `app/` | Configuración y funciones de PHP (bloqueada al público) |
| `sql/schema.sql` | Tabla de la base de datos (bloqueada al público) |
| `uploads/` | Fotos que suben los clientes (aquí no se ejecuta nada) |
| `dev/` | Servidor de prueba local. **No se sube a Hostinger.** |

## Subirla a Hostinger (una vez)

1. **Correo**: en Hostinger → Emails, crea el buzón `hola@recambia.es`.
2. **Base de datos**: en Hostinger → Bases de datos → MySQL, crea una base de datos con su usuario. Abre phpMyAdmin, pestaña SQL, pega el contenido de `sql/schema.sql` y ejecútalo.
3. **Configuración**: copia `app/config.example.php` como `app/config.php` y rellena:
   - los datos de la base de datos,
   - `plazo_horas`,
   - `ip_salt` con una frase larga inventada.
4. **Archivos**: sube todo menos `dev/` a `public_html` con el Administrador de archivos o por FTP.
5. **PHP**: en Hostinger → Avanzado → Configuración de PHP, elige PHP 8.2 o superior.
6. **Prueba**: haz una solicitud real y comprueba que llegan los dos correos (mira también en spam).

## Lo que falta rellenar

- `[PLAZO]`: en `assets/js/app.js` (`plazoHoras`) y en `app/config.php` (`plazo_horas`).
- `[PRECIO]` y `[MODELO DE COCHE]`: en el bloque de ahorro de `index.html`.
- Los datos del titular, NIF y dirección: en el pie de `index.html`, en las 4 páginas legales y en `app/lib.php` (pie del correo).
- Las fechas de "Última actualización" de las páginas legales.
- Las páginas legales son una base: **que las revise un gestor o abogado antes de publicar.**

## Probar en local

```bash
node dev/server.mjs
```

Abre http://localhost:5173. El servidor de prueba simula el envío del formulario y devuelve un número de solicitud falso. No guarda nada ni envía correos.

## Notas

- Los correos salen con `mail()` de PHP desde `hola@recambia.es`. Si acaban en spam, el siguiente paso es enviarlos por SMTP.
- Cuando el dueño pulsa **Responder** en el aviso de una solicitud nueva, contesta directamente al cliente.
- Las tipografías se cargan desde Google Fonts. Por privacidad, conviene alojarlas en el propio servidor antes de publicar.
