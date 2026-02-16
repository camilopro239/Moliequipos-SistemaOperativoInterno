CREATE TABLE IF NOT EXISTS auditoria_descargas_documentos (
  id INT(11) NOT NULL AUTO_INCREMENT,
  usuario_id INT(11) NOT NULL,
  documento_id INT(11) NOT NULL,
  empleado_id INT(11) NOT NULL,
  tipo_documento VARCHAR(30) NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  fecha_descarga TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auditoria_usuario_id (usuario_id),
  KEY idx_auditoria_documento_id (documento_id),
  KEY idx_auditoria_empleado_id (empleado_id),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_auditoria_documento FOREIGN KEY (documento_id) REFERENCES documentos_empleado(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_auditoria_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE ON UPDATE CASCADE
);
