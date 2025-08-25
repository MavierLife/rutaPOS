-- Agregar configuración para terminal EQP02
INSERT INTO `tblconfiguraciones` (`IDConfiguracion`, `NombreComercial`, `Sucursal`, `TipoSucursal`, `RazonSocial`, `Direccion`, `Departamento`, `Municipio`, `Telefono`, `NIT`, `NRC`, `GiroComercial`, `ClaveAutorizacion`, `UltimaUpdate`, `UltimaUpdateProductos`, `UltimaUpdateCategorias`, `UltimaUpdateEmpleados`, `UltimaUpdateClientes`, `UltimaUpdateRutas`, `UltimaUpdateCuentas`, `UsuarioAsigna`, `EquipoAsigna`) VALUES
('2', 'Tienda Benavides', 'SUCURSAL EQP02', 'SUCURSAL', 'EDWIN ANTONIO COTO BENAVIDES', 'Barrio El Calvario, al sur del Parque Zaldivar', 'San Miguel', 'San Rafael Oriente', '', '1217-130986-101-3', '175781-1', 'VENTA EN SUPERMERCADO', 28285, '8/21/25', '8/21/25', '8/21/25', '8/21/25', '8/21/25', '8/21/25', '8/21/25', 242, 'EQP02');

-- Actualizar configuración existente para que tenga formato EQP01
UPDATE `tblconfiguraciones` SET `EquipoAsigna` = 'EQP01' WHERE `IDConfiguracion` = '1';