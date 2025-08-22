-- Script para agregar campos de hibernación a la tabla sesiones_erp
-- Ejecutar en la base de datos listosof_listosoft

USE listosof_listosoft;

-- Agregar campo estado para controlar si la sesión está activa, hibernada, o cerrada
ALTER TABLE sesiones_erp 
ADD COLUMN estado CHAR(1) DEFAULT 'A' AFTER ultima_actividad;

-- Agregar campo fecha_hibernacion para registrar cuándo se hibernó la sesión
ALTER TABLE sesiones_erp 
ADD COLUMN fecha_hibernacion DATETIME NULL AFTER estado;

-- Agregar campo fecha_despertar para registrar cuándo se despertó la sesión
ALTER TABLE sesiones_erp 
ADD COLUMN fecha_despertar DATETIME NULL AFTER fecha_hibernacion;

-- Crear índice para mejorar el rendimiento de consultas por estado
CREATE INDEX idx_sesiones_estado ON sesiones_erp(estado);

-- Crear índice para consultas por fecha de hibernación
CREATE INDEX idx_sesiones_fecha_hibernacion ON sesiones_erp(fecha_hibernacion);

-- Actualizar registros existentes para que tengan estado 'A' (activa)
UPDATE sesiones_erp SET estado = 'A' WHERE estado IS NULL;

-- Verificar que los campos se agregaron correctamente
DESCRIBE sesiones_erp;
