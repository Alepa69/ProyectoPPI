
-- ejecuta este script para crear el usuario administrador

UPDATE usuarios
SET rol = 'administrador', estado = 'activo'
WHERE correo = 'administradorHaloja@admin.com';

-- correo : administradorHaloja@admin.com
-- clave : Admin123 
