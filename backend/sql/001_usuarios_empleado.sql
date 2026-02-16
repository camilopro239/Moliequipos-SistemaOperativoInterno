ALTER TABLE usuarios
ADD COLUMN empleado_id INT(11) NULL AFTER rol;

ALTER TABLE usuarios
ADD CONSTRAINT fk_usuarios_empleado
FOREIGN KEY (empleado_id) REFERENCES empleados(id)
ON DELETE SET NULL
ON UPDATE CASCADE;

ALTER TABLE usuarios
ADD UNIQUE KEY uk_usuarios_empleado_id (empleado_id);
