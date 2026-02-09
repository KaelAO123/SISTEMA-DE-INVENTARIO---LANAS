-- Backup de Base de Datos
-- Sistema de Inventario Lanas
-- Fecha: 2026-02-07 02:26:38
-- Generado por: Administrador Principal

--
-- Estructura de tabla para `categorias`
--
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_categoria_proveedor` (`proveedor_id`),
  CONSTRAINT `categorias_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--
INSERT INTO `categorias` VALUES
('1','1','Lana Merino','Lana fina de alta calidad para prendas delicadas'),
('2','1','Lana Acrílica','Lana sintética económica y lavable'),
('3','2','Hilo Algodón','Hilo de algodón para tejidos y bordados'),
('4','2','Lana Bebé','Lana suave especial para bebés'),
('5','3','Lana Gruesa','Lana gruesa para abrigos y mantas'),
('6','3','Hilo Fantasía','Hilos con efectos especiales'),
('7','4','Lana Estambre','Lana tradicional para todo tipo de tejidos'),
('8','4','Mezclas','Mezclas de lana con otros materiales'),
('9','5','Lana Premium','Lana de la mejor calidad'),
('10','5','Lana Económica','Lana de precio accesible'),
('11','6','Acuarela','Lana para tejidos artísticos'),
('12','6','Bebé','Lanas suaves para bebés'),
('13','7','Claudia','Lana de marca Claudia'),
('14','7','Ardilla','Lana de marca Ardilla'),
('15','8','Premium','Lana de alta gama'),
('16','8','Económico','Lana de bajo costo'),
('17','9','Deportiva','Lana para prendas deportivas'),
('18','9','Invierno','Lana extra abrigada'),
('19','10','Primavera','Lana ligera para primavera'),
('20','10','Verano','Lana fresca para verano');

--
-- Estructura de tabla para `clientes`
--
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` enum('DNI','RUC','Cedula') DEFAULT 'DNI',
  `numero_documento` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `saldo_deuda` decimal(10,2) DEFAULT 0.00,
  `limite_credito` decimal(10,2) DEFAULT 0.00,
  `historial_compras` int(11) DEFAULT 0,
  `total_comprado` decimal(15,2) DEFAULT 0.00,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--
INSERT INTO `clientes` VALUES
('1','María López','DNI','0101010101','0991234567','maria@email.com','Calle Principal 123','250.50','1000.00','15','12500.75','1','2024-01-10',NULL,'2026-02-06 20:18:30'),
('2','Carlos Rodríguez','RUC','0101010101001','0987654321','carlos@email.com','AAAAaaaaa','0.00','5000.00','8','8500.25','1','2024-01-15','','2026-02-06 20:18:30'),
('3','Ana García','DNI','0202020202','0976543210','ana@email.com','Calle Secundaria 789','150.00','800.00','13','9922.02','1','2024-01-20',NULL,'2026-02-06 20:18:30'),
('4','Pedro Martínez','Cedula','0303030303','0965432109','pedro@email.com','Av. Norte 321','500.75','2000.00','5','4500.00','1','2024-02-01',NULL,'2026-02-06 20:18:30'),
('5','Lucía Fernández','DNI','0404040404','0954321098','lucia@email.com','Calle Sur 654','0.00','3000.00','20','21500.00','1','2024-02-05',NULL,'2026-02-06 20:18:30'),
('6','Jorge Silva','RUC','0505050505001','0943210987','jorge@email.com','Av. Oeste 987','320.25','1500.00','10','11200.75','1','2024-02-10',NULL,'2026-02-06 20:18:30'),
('7','Sofía Ramírez','DNI','0606060606','0932109876','sofia@email.com','Calle Este 147','0.00','1200.00','7','6800.50','1','2024-02-15',NULL,'2026-02-06 20:18:30'),
('8','Miguel Torres','Cedula','0707070707','0921098765','miguel@email.com','Av. Principal 258','180.00','2500.00','18','18900.25','1','2024-02-20',NULL,'2026-02-06 20:18:30'),
('9','Carmen Vargas','DNI','0808080808','0910987654','carmen@email.com','Calle Nueva 369','0.00','1800.00','9','9200.00','1','2024-03-01',NULL,'2026-02-06 20:18:30'),
('10','Roberto Castro','RUC','0909090909001','0909876543','roberto@email.com','Av. Comercial 753','420.50','3500.00','14','14300.75','1','2024-03-05',NULL,'2026-02-06 20:18:30'),
('11','Elena Ruiz','DNI','1010101010','0898765432','elena@email.com','Calle Vieja 852','0.00','900.00','11','7800.50','1','2024-03-10',NULL,'2026-02-06 20:18:30'),
('12','Fernando Díaz','Cedula','1111111111','0887654321','fernando@email.com','Av. Nueva 159','0.00','1200.00','6','5200.00','1','2024-03-15',NULL,'2026-02-06 20:18:30'),
('13','Patricia Morales','DNI','1212121212','0876543210','patricia@email.com','Calle Antigua 357','0.00','2000.00','16','14500.25','1','2024-03-20',NULL,'2026-02-06 20:18:30'),
('14','Roberto Silva','RUC','1313131313001','0865432109','robertos@email.com','Av. Moderna 753','600.00','4000.00','12','13200.00','1','2024-03-25',NULL,'2026-02-06 20:18:30'),
('15','Lucía Ortega','DNI','1414141414','0854321098','luciao@email.com','Calle Actual 951','0.00','1500.00','8','9200.75','1','2024-04-01',NULL,'2026-02-06 20:18:30'),
('16','Daniel Navarro','Cedula','1515151515','0843210987','daniel@email.com','Av. Futura 147','120.50','1800.00','9','8500.50','1','2024-04-05',NULL,'2026-02-06 20:18:30'),
('17','Isabel Romero','DNI','1616161616','0832109876','isabel@email.com','Calle Pasada 258','0.00','2200.00','14','12800.25','1','2024-04-10',NULL,'2026-02-06 20:18:30'),
('18','Gabriel Molina','RUC','1717171717001','0821098765','gabriel@email.com','Av. Presente 369','350.75','2800.00','7','7400.00','1','2024-04-15',NULL,'2026-02-06 20:18:30'),
('19','Mónica Herrera','DNI','1818181818','0810987654','monica@email.com','Calle Futuro 753','0.00','1600.00','10','9800.50','1','2024-04-20',NULL,'2026-02-06 20:18:30'),
('20','Rafael González','Cedula','1919191919','0809876543','rafael@email.com','Av. Eterna 852','0.00','1100.00','5','4800.25','1','2024-04-25',NULL,'2026-02-06 20:18:30'),
('21','JOE','DNI','111111111222','','','','0.00','1000.00','0','0.00','1','2026-02-06','','2026-02-06 20:33:02');

--
-- Estructura de tabla para `compras_proveedores`
--
CREATE TABLE `compras_proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `numero_factura` varchar(100) DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `saldo_pendiente` decimal(10,2) DEFAULT 0.00,
  `comprobante` varchar(100) DEFAULT NULL,
  `fecha_compra` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` enum('pendiente','pagada','parcial') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_compras_proveedor` (`proveedor_id`),
  KEY `idx_compras_estado` (`estado`),
  CONSTRAINT `compras_proveedores_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `compras_proveedores_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `compras_proveedores`
--
--
-- Estructura de tabla para `configuraciones`
--
CREATE TABLE `configuraciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo` enum('texto','numero','booleano','json') DEFAULT 'texto',
  `descripcion` text DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuraciones`
--
INSERT INTO `configuraciones` VALUES
('1','nombre_empresa','Lanas y Textiles S.A.','texto','Nombre de la empresa','2026-02-06 20:18:30'),
('2','telefono_empresa','022222222','texto','Teléfono de la empresa','2026-02-06 20:18:30'),
('3','direccion_empresa','Av. Principal 123, Quito - Ecuador','texto','Dirección de la empresa','2026-02-06 20:18:30'),
('4','iva_porcentaje','12','numero','Porcentaje de IVA','2026-02-06 20:18:30'),
('5','moneda','$','texto','Símbolo de moneda','2026-02-06 20:18:30'),
('6','stock_minimo_alerta','5','numero','Stock mínimo para alertas','2026-02-06 20:18:30'),
('7','logo_url','assets/img/logo.png','texto','URL del logo','2026-02-06 20:18:30'),
('8','email_empresa','info@lanasytextiles.com','texto','Email de la empresa','2026-02-06 20:18:30'),
('9','notificaciones_activas','1','booleano','Activar notificaciones','2026-02-06 20:18:30'),
('10','backup_automatico','1','booleano','Backup automático diario','2026-02-06 20:18:30');

--
-- Estructura de tabla para `cuentas_cobrar`
--
CREATE TABLE `cuentas_cobrar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) DEFAULT NULL,
  `venta_id` int(11) DEFAULT NULL,
  `tipo` enum('venta','pago','abono') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `saldo_anterior` decimal(10,2) NOT NULL,
  `saldo_nuevo` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','transferencia','tarjeta') DEFAULT NULL,
  `referencia_pago` varchar(100) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_cobrar_cliente` (`cliente_id`),
  KEY `idx_cobrar_fecha` (`fecha_hora`),
  CONSTRAINT `cuentas_cobrar_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `cuentas_cobrar_ibfk_2` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  CONSTRAINT `cuentas_cobrar_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cuentas_cobrar`
--
INSERT INTO `cuentas_cobrar` VALUES
('1','20',NULL,'pago','90.00','90.00','0.00','efectivo','','2026-02-06 20:49:11','1',NULL),
('2','12',NULL,'pago','75.00','75.00','0.00','efectivo','','2026-02-06 20:49:18','1',NULL);

--
-- Estructura de tabla para `cuentas_pagar`
--
CREATE TABLE `cuentas_pagar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) DEFAULT NULL,
  `tipo` enum('compra','pago','abono') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `saldo_anterior` decimal(10,2) NOT NULL,
  `saldo_nuevo` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','transferencia','cheque') DEFAULT NULL,
  `referencia_pago` varchar(100) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_pagar_proveedor` (`proveedor_id`),
  KEY `idx_pagar_fecha` (`fecha_hora`),
  CONSTRAINT `cuentas_pagar_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `cuentas_pagar_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cuentas_pagar`
--
INSERT INTO `cuentas_pagar` VALUES
('1','11','pago','300.00','300.00','0.00','transferencia','','2026-02-06 20:49:41','1',NULL),
('2','15','pago','420.00','420.00','0.00','transferencia','','2026-02-06 20:56:28','1',NULL);

--
-- Estructura de tabla para `movimientos_stock`
--
CREATE TABLE `movimientos_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subpaquete_id` int(11) DEFAULT NULL,
  `tipo` enum('ingreso','venta','ajuste','devolucion') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `stock_anterior` int(11) NOT NULL,
  `stock_nuevo` int(11) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_movimientos_producto` (`subpaquete_id`),
  KEY `idx_movimientos_fecha` (`fecha_hora`),
  KEY `idx_movimientos_tipo` (`tipo`),
  CONSTRAINT `movimientos_stock_ibfk_1` FOREIGN KEY (`subpaquete_id`) REFERENCES `subpaquetes` (`id`),
  CONSTRAINT `movimientos_stock_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `movimientos_stock`
--
INSERT INTO `movimientos_stock` VALUES
('1','12','venta','1','38','37',NULL,'1','2026-02-06 21:02:30','Venta #V-20260207-8648');

--
-- Estructura de tabla para `notificaciones`
--
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('stock','pago','venta','sistema') NOT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `url` varchar(255) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notificaciones_usuario` (`usuario_id`),
  KEY `idx_notificaciones_leida` (`leida`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--
--
-- Estructura de tabla para `paquetes`
--
CREATE TABLE `paquetes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `subpaquetes_por_paquete` int(11) DEFAULT 10,
  `cantidad_paquetes` int(11) DEFAULT 0,
  `cantidad_subpaquetes` int(11) DEFAULT 0,
  `costo` decimal(10,2) DEFAULT NULL,
  `precio_venta_sugerido` decimal(10,2) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `ubicacion` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_paquete_proveedor` (`proveedor_id`),
  KEY `idx_paquete_categoria` (`categoria_id`),
  CONSTRAINT `paquetes_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `paquetes_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `paquetes`
--
INSERT INTO `paquetes` VALUES
('1','PKG-001','1','1','Paquete Merino Supreme',NULL,'10','5','50','250.00','350.00','2024-01-15','Estante A1'),
('2','PKG-002','1','2','Paquete Acrílico Básico',NULL,'10','8','80','120.00','180.00','2024-01-20','Estante A2'),
('3','PKG-003','2','3','Paquete Algodón Premium',NULL,'12','3','36','300.00','450.00','2024-02-05','Estante B1'),
('4','PKG-004','2','4','Paquete Bebé Suave',NULL,'8','6','48','180.00','280.00','2024-02-10','Estante B2'),
('5','PKG-005','3','5','Paquete Grueso Invierno',NULL,'15','4','60','400.00','600.00','2024-02-15','Estante C1'),
('6','PKG-006','3','6','Paquete Fantasía Especial',NULL,'10','5','50','220.00','320.00','2024-02-20','Estante C2'),
('7','PKG-007','4','7','Paquete Estambre Clásico',NULL,'10','7','70','150.00','220.00','2024-03-01','Estante D1'),
('8','PKG-008','4','8','Paquete Mezcla Moderna',NULL,'12','4','48','280.00','380.00','2024-03-05','Estante D2'),
('9','PKG-009','5','9','Paquete Premium Plus',NULL,'10','3','30','500.00','750.00','2024-03-10','Estante E1'),
('10','PKG-010','5','10','Paquete Económico Familiar',NULL,'20','6','120','200.00','280.00','2024-03-15','Estante E2'),
('11','PKG-011','6','11','Paquete Acuarela Art',NULL,'10','4','40','180.00','260.00','2024-03-20','Estante F1'),
('12','PKG-012','6','12','Paquete Bebé Premium',NULL,'8','5','40','220.00','320.00','2024-03-25','Estante F2'),
('13','PKG-013','7','13','Paquete Claudia',NULL,'10','6','60','240.00','340.00','2024-04-01','Estante G1'),
('14','PKG-014','7','14','Paquete Ardilla',NULL,'10','4','40','200.00','290.00','2024-04-05','Estante G2'),
('15','PKG-015','8','15','Paquete Premium Gold',NULL,'10','3','30','450.00','650.00','2024-04-10','Estante H1'),
('16','PKG-016','8','16','Paquete Económico Plus',NULL,'15','8','120','150.00','220.00','2024-04-15','Estante H2'),
('17','PKG-017','9','17','Paquete Deportivo',NULL,'10','5','50','280.00','400.00','2024-04-20','Estante I1'),
('18','PKG-018','9','18','Paquete Invierno Plus',NULL,'12','6','72','320.00','460.00','2024-04-25','Estante I2'),
('19','PKG-019','10','19','Paquete Primavera',NULL,'10','7','70','190.00','270.00','2024-05-01','Estante J1'),
('20','PKG-020','10','20','Paquete Verano Fresh',NULL,'8','5','40','170.00','240.00','2024-05-05','Estante J2');

--
-- Estructura de tabla para `proveedores`
--
CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `ciudad` varchar(50) DEFAULT NULL,
  `saldo_deuda` decimal(10,2) DEFAULT 0.00,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `proveedores`
--
INSERT INTO `proveedores` VALUES
('1','Lanas del Norte S.A.','2012345678901','0987654321','ventas@lanasdelnorte.com','Av. Principal 123','Quito','1500.00','1','2026-02-06 20:18:30'),
('2','Textiles Andinos','1012345678902','0976543210','info@textilesandinos.com','Calle Bolívar 456','Ambato','0.00','1','2026-02-06 20:18:30'),
('3','Hilados Premium','3012345678903','0965432109','contacto@hiladospremium.com','Av. Amazonas 789','Guayaquil','2500.50','1','2026-02-06 20:18:30'),
('4','Lanas Express','4012345678904','0954321098','ventas@lanasexpress.com','Calle Flores 321','Cuenca','0.00','1','2026-02-06 20:18:30'),
('5','Distribuidora Textil','5012345678905','0943210987','distribuidora@textil.com','Av. 6 de Diciembre 654','Quito','1200.75','1','2026-02-06 20:18:30'),
('6','Importadora de Lanas','6012345678906','0932109876','importadora@lanas.com','Calle Sucre 987','Manta','0.00','1','2026-02-06 20:18:30'),
('7','Comercializadora Textil','7012345678907','0921098765','comercial@textil.com','Av. Kennedy 147','Guayaquil','500.25','1','2026-02-06 20:18:30'),
('8','Hilados del Sur','8012345678908','0910987654','hilados@surt.com','Calle Lojana 258','Loja','0.00','1','2026-02-06 20:18:30'),
('9','Lanas Selectas','9012345678909','0909876543','selectas@lanas.com','Av. Colón 369','Quito','800.00','1','2026-02-06 20:18:30'),
('10','Proveedor Textil Nacional','0012345678910','0898765432','nacional@textil.com','Calle Pichincha 753','Ibarra','0.00','1','2026-02-06 20:18:30'),
('11','Textiles Santa Cruz','1112345678911','0887654321','santacruz@textiles.com','Av. Oriental 951','Santo Domingo','0.00','1','2026-02-06 20:18:30'),
('12','Lanas Copacabana','1212345678912','0876543210','copacabana@lanas.com','Calle Mercado 357','Riobamba','0.00','1','2026-02-06 20:18:30'),
('13','Industriales Colibrí','1312345678913','0865432109','colibri@industriales.com','Av. Mariscal 159','Machala','950.00','1','2026-02-06 20:18:30'),
('14','Hilados Ecuador','1412345678914','0854321098','hilados@ecuador.com','Calle Rocafuerte 753','Portoviejo','0.00','1','2026-02-06 20:18:30'),
('15','Textiles del Pacífico','1512345678915','0843210987','pacifico@textiles.com','Av. 9 de Octubre 456','Esmeraldas','0.00','1','2026-02-06 20:18:30'),
('16','Lanas Andinas','1612345678916','0832109876','andinas@lanas.com','Calle Amazonas 987','Latacunga','0.00','1','2026-02-06 20:18:30'),
('17','Distribuidora Lanasur','1712345678917','0821098765','lanasur@distribuidora.com','Av. de los Shyris 654','Salinas','1100.00','1','2026-02-06 20:18:30'),
('18','Importadora Hilamax','1812345678918','0810987654','hilamax@importadora.com','Calle Universitaria 321','Babahoyo','0.00','1','2026-02-06 20:18:30'),
('19','Textiles Quito','1912345678919','0809876543','quito@textiles.com','Av. América 147','Quito','650.00','1','2026-02-06 20:18:30'),
('20','Lanas Internacional','2012345678920','0798765432','internacional@lanas.com','Calle Venezuela 852','Guayaquil','0.00','1','2026-02-06 20:18:30'),
('21','AAAAAAAAAAAAAAAAAAAAAAAAA','','','','aaaaaaaaaaa','','0.00','1','2026-02-06 20:33:21');

--
-- Estructura de tabla para `subpaquetes`
--
CREATE TABLE `subpaquetes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paquete_id` int(11) DEFAULT NULL,
  `codigo_color` varchar(50) DEFAULT NULL,
  `nombre_color` varchar(100) DEFAULT NULL,
  `descripcion_color` text DEFAULT NULL,
  `precio_venta` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 5,
  `max_stock` int(11) DEFAULT 100,
  `ubicacion` varchar(50) DEFAULT NULL,
  `fecha_ultima_venta` date DEFAULT NULL,
  `vendido_total` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_subpaquete_paquete` (`paquete_id`),
  KEY `idx_subpaquete_stock` (`stock`),
  KEY `idx_subpaquetes_activo` (`activo`),
  CONSTRAINT `subpaquetes_ibfk_1` FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `subpaquetes`
--
INSERT INTO `subpaquetes` VALUES
('1','1','M001','Blanco Nieve',NULL,'35.00','45','5','50','A1-01',NULL,'5','1'),
('2','2','A001','Blanco Perla',NULL,'18.00','58','5','80','A2-01',NULL,'22','1'),
('3','3','AL001','Blanco Natural',NULL,'45.00','28','3','36','B1-01',NULL,'8','1'),
('4','4','B001','Celeste Suave',NULL,'28.00','39','5','48','B2-01',NULL,'9','1'),
('5','5','G001','Café Oscuro',NULL,'60.00','50','5','60','C1-01',NULL,'10','1'),
('6','6','F001','Plateado Brillante',NULL,'32.00','45','5','50','C2-01',NULL,'5','1'),
('7','7','E001','Rojo Clásico',NULL,'22.00','65','5','70','D1-01',NULL,'5','1'),
('8','8','MX001','Gris Antracita',NULL,'38.00','48','5','48','D2-01',NULL,'0','1'),
('9','9','PP001','Dorado Premium',NULL,'75.00','25','3','30','E1-01',NULL,'5','1'),
('10','10','EC001','Azul Marino',NULL,'28.00','100','10','120','E2-01',NULL,'20','1'),
('11','11','AC001','Amarillo Sol',NULL,'26.00','35','5','40','F1-01',NULL,'5','1'),
('12','12','BP001','Rosa Bebé',NULL,'32.00','37','5','40','F2-01','2026-02-06','3','1'),
('13','13','CL001','Verde Esmeralda',NULL,'34.00','52','5','60','G1-01',NULL,'8','1'),
('14','14','AR001','Marrón Chocolate',NULL,'29.00','40','5','40','G2-01',NULL,'0','1'),
('15','15','PG001','Blanco Diamante',NULL,'65.00','28','3','30','H1-01',NULL,'2','1'),
('16','16','EP001','Gris Humo',NULL,'22.00','110','10','120','H2-01',NULL,'10','1'),
('17','17','DP001','Negro Deportivo',NULL,'40.00','45','5','50','I1-01',NULL,'5','1'),
('18','18','IP001','Azul Polar',NULL,'46.00','65','5','72','I2-01',NULL,'7','1'),
('19','19','PR001','Verde Primavera',NULL,'27.00','63','5','70','J1-01',NULL,'7','1'),
('20','20','VF001','Blanco Verano',NULL,'24.00','36','5','40','J2-01',NULL,'4','1');

--
-- Estructura de tabla para `usuarios`
--
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rol` enum('admin','vendedor') DEFAULT 'vendedor',
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `foto_perfil` varchar(255) DEFAULT NULL,
  `ultimo_login` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--
INSERT INTO `usuarios` VALUES
('1','admin','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Administrador Principal','admin@lanas.com','admin','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('2','vendedor1','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Juan Pérez','juan@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('3','vendedor2','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','María García','maria@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('4','vendedor3','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Carlos López','carlos@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('5','vendedor4','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Ana Martínez','ana@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('6','vendedor5','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Pedro Rodríguez','pedro@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('7','vendedor6','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Laura Sánchez','laura@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('8','vendedor7','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Miguel Torres','miguel@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('9','vendedor8','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Sofía Ramírez','sofia@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('10','vendedor9','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Jorge Fernández','jorge@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('11','vendedor10','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Carmen Vargas','carmen@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('12','vendedor11','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Ricardo Castro','ricardo@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('13','vendedor12','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Elena Ruiz','elena@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('14','vendedor13','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Fernando Díaz','fernando@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('15','vendedor14','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Patricia Morales','patricia@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('16','vendedor15','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Roberto Silva','roberto@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('17','vendedor16','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Lucía Ortega','lucia@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('18','vendedor17','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Daniel Navarro','daniel@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('19','vendedor18','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Isabel Romero','isabel@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('20','vendedor19','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Gabriel Molina','gabriel@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30'),
('21','vendedor20','$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.','Mónica Herrera','monica@lanas.com','vendedor','activo',NULL,NULL,'2026-02-06 20:18:30','2026-02-06 20:18:30');

--
-- Estructura de tabla para `venta_detalles`
--
CREATE TABLE `venta_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) DEFAULT NULL,
  `subpaquete_id` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `descuento_unitario` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  `hora_extraccion` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_detalle_venta` (`venta_id`),
  KEY `idx_detalle_subpaquete` (`subpaquete_id`),
  CONSTRAINT `venta_detalles_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venta_detalles_ibfk_2` FOREIGN KEY (`subpaquete_id`) REFERENCES `subpaquetes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `venta_detalles`
--
INSERT INTO `venta_detalles` VALUES
('1','1','12','1','32.00','0.00','32.00','21:02:30');

--
-- Estructura de tabla para `ventas`
--
CREATE TABLE `ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_venta` varchar(50) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `iva` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `pagado` decimal(10,2) DEFAULT 0.00,
  `debe` decimal(10,2) DEFAULT 0.00,
  `tipo_pago` enum('contado','credito') NOT NULL,
  `estado` enum('pendiente','pagada','cancelada') DEFAULT 'pendiente',
  `fecha_hora` datetime NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `impreso` tinyint(1) DEFAULT 0,
  `anulado` tinyint(1) DEFAULT 0,
  `motivo_anulacion` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_venta` (`codigo_venta`),
  KEY `idx_ventas_fecha` (`fecha_hora`),
  KEY `idx_ventas_cliente` (`cliente_id`),
  KEY `idx_ventas_vendedor` (`vendedor_id`),
  KEY `idx_ventas_estado` (`estado`),
  KEY `idx_ventas_codigo` (`codigo_venta`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ventas`
--
INSERT INTO `ventas` VALUES
('1','V-20260207-8648',NULL,'1','32.00','0.00','3.84','35.84','35.84','0.00','contado','pagada','2026-02-06 21:02:30',NULL,'0','0',NULL,'');

