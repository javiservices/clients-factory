# Modo Oculto del Sistema

## Descripción
El sistema incluye un modo oculto diseñado para situaciones de inspección laboral. Cuando se activa, el sistema solo muestra clientes marcados como "facturables", ocultando cualquier cliente que no cumpla con este criterio.

## Cómo activar el modo oculto

### Método 1: URL directa
- **Activar modo oculto**: `http://tu-dominio/client/system/mode/hidden`
- **Volver al modo normal**: `http://tu-dominio/client/system/mode/normal`

### Método 2: Enlace oculto
En las páginas de Dashboard y Gestión de Clientes, hay un pequeño icono de engranaje (⚙️) en la esquina inferior izquierda de la pantalla. Este enlace permite alternar entre modos.

## Qué cambia en modo oculto

### Dashboard
- Las estadísticas de "Total Clientes" solo cuentan clientes facturables
- La lista de "Clientes Recientes" solo muestra clientes facturables
- Se muestra un indicador visual "(Modo Restringido)" en el título

### Gestión de Clientes
- Solo se muestran clientes marcados como facturables
- Se muestra un indicador visual "(Modo Restringido)" en el título
- Aparece una alerta informativa sobre el modo activo

## Funcionalidad preservada
- La facturación automática sigue funcionando normalmente
- Los clientes no facturables permanecen en la base de datos
- Todas las operaciones de creación, edición y renovación funcionan igual

## Seguridad
- El modo se almacena en la sesión del usuario actual
- No afecta a otros usuarios conectados
- Se puede desactivar fácilmente volviendo al modo normal

## Notas importantes
- Este modo está diseñado para ser discreto y no aparecer en menús visibles
- Los datos no se eliminan, solo se filtran en la presentación
- La funcionalidad completa se restaura al volver al modo normal
