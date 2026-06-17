# Manual de usuario

## Iniciar sesión

Abra `frontend/login.html`, escriba email y password, y presione `Iniciar sesión`. El sistema guardará el token en el navegador.

## Registrar solicitud

Entre a `Solicitudes`, use el asistente de tres pasos, seleccione área, fecha, hora, destino, cantidad de personas y motivo. El sistema validará que la fecha sea desde mañana y que la hora esté entre 08:00 y 16:00.

## Registrar pedido especial

Entre a `Solicitudes`, seleccione `Pedido especial` en el tipo de solicitud y complete fecha, hora y cantidad de personas. Estos pedidos pueden ser para el mismo día.

Presione `Evaluar` para revisar disponibilidad. Si hay vehículo en cola con asientos suficientes, el sistema mostrará `Sí - Atiendo` y la solicitud quedará pendiente para programación. Si no hay vehículo o capacidad suficiente, mostrará `No - Rechazo`, registrará la solicitud como rechazada y guardará el motivo.

## Programar atención

Entre a `Programación`, seleccione una solicitud pendiente, presione `Sugerir vehículo` o elija manualmente vehículo y conductor. Al guardar, la solicitud pasa a programada y el vehículo sale de la cola.

## Usar cola

Entre a `Cola`, revise el orden FIFO de unidades disponibles. Puede agregar vehículos disponibles, retirar unidades y reordenar según criterio operativo.

## Registrar kilometraje

Entre a `Kilometraje`. Primero registre el kilometraje inicial de una programación. Después de iniciar ruta, registre el kilometraje final; el sistema calcula los kilómetros recorridos.

## Registrar retorno

Entre a `Retornos`, seleccione una programación en ruta y confirme el retorno. El sistema finaliza la atención, marca la solicitud como atendida, cambia el vehículo a disponible y lo agrega nuevamente a cola.

## Consultar estadísticas

Entre a `Estadísticas` para ver gráficos de solicitudes, vehículos, áreas, conductores y kilometraje acumulado.

## Panel público

Abra `Panel público`. Antes de las 5:00 PM muestra un aviso. Desde las 5:00 PM publica la programación del día siguiente y se actualiza cada 5 segundos.
