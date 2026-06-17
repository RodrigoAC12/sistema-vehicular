# Instalación en XAMPP

1. Instale XAMPP.
2. Copie `sistema-vehicular` en `C:\xampp\htdocs`.
3. Inicie Apache y MySQL desde el panel de XAMPP.
4. Entre a `http://localhost/phpmyadmin`.
5. Importe `database/db_sistema_vehicular.sql`.
6. Revise `services/shared/database.php`:

```php
$host = 'localhost';
$database = 'db_sistema_vehicular';
$user = 'root';
$password = '';
```

7. Abra:

```text
http://localhost/sistema-vehicular/frontend/login.html
```

8. Inicie sesión con:

```text
admin@sistema.com / admin123
```

## Pruebas básicas

1. Login correcto.
2. Login incorrecto.
3. Registro de solicitud válida.
4. Registro con fecha inválida.
5. Registro fuera de horario.
6. Programar solicitud pendiente.
7. Intentar programar vehículo no disponible.
8. Sugerir vehículo.
9. Registrar kilometraje inicial.
10. Iniciar ruta.
11. Registrar kilometraje final.
12. Registrar retorno.
13. Verificar vehículo en cola.
14. Consultar estadísticas.
15. Ver panel público antes de las 5 PM.
16. Ver panel público después de las 5 PM.
