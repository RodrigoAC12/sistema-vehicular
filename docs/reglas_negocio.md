# Reglas de negocio

## Solicitudes

- La fecha de servicio debe ser como mínimo para el día siguiente.
- No se aceptan solicitudes para el mismo día.
- La hora permitida es de 08:00 a 16:00.
- La cantidad de personas debe ser mayor que 0.
- Dirección, motivo y área son obligatorios.
- El área debe existir y estar activa.
- La solicitud queda registrada con fecha y hora de solicitud.
- Las solicitudes ingresadas después de las 4:00 p.m. se registran como `rechazada` con motivo de corte horario.
- Estados: `pendiente`, `programada`, `atendida`, `rechazada`, `cancelada`.

## Pedidos especiales

- Un pedido especial puede solicitarse para el mismo día.
- No se aceptan pedidos especiales con fecha anterior a hoy.
- Sigue aplicando el horario permitido de 08:00 a 16:00.
- La atención está sujeta a disponibilidad de vehículo en cola y asientos suficientes.
- Si existe vehículo disponible con capacidad suficiente, el resultado especial queda en `atender` y la solicitud queda `pendiente` para programación.
- Si no existe vehículo o los asientos son insuficientes, el resultado especial queda en `rechazar`, la solicitud queda `rechazada` y se registra el motivo.
- La programación vuelve a validar vehículo, capacidad y conductor antes de confirmar la atención.

## Programación

- Solo se programan solicitudes pendientes.
- El vehículo debe estar disponible.
- No se asignan vehículos en mantenimiento o fuera de servicio.
- El conductor debe estar activo.
- No se duplica vehículo o conductor en la misma fecha y hora.
- Al asignar vehículo, la unidad sale de la cola.
- El coordinador puede agrupar varios pedidos de la misma fecha en una ruta si los asientos del vehículo alcanzan.
- La ruta corta se calcula desde un origen usando una estimación local entre direcciones y ordena los pedidos por menor tiempo estimado.
- Una ruta agrupada comparte código de ruta, vehículo, conductor, fecha y hora de inicio.
- Al iniciar, cancelar o cerrar retorno de una ruta agrupada, el cambio se aplica a todos los pedidos del mismo código de ruta.
- Estados: `programada`, `en_ruta`, `finalizada`, `cancelada`.

## Panel público

- Antes de las 5:00 PM se informa que la programación está en proceso.
- Desde las 5:00 PM se muestra la programación del día siguiente.
- No se muestran datos sensibles.
- La pantalla se actualiza automáticamente cada 5 segundos.

## Cola vehicular

- Los vehículos disponibles ingresan a cola.
- La sugerencia de vehículo respeta FIFO, capacidad y disponibilidad.
- Al retornar, el vehículo vuelve a la cola.
- Estados: `en_cola`, `asignado`, `retirado`.

## Kilometraje

- El kilometraje inicial debe ser mayor o igual al kilometraje actual.
- El kilometraje final debe ser mayor que el inicial.
- El sistema calcula kilómetros recorridos.
- El kilometraje actual del vehículo se actualiza con el final.
- No se inicia ruta sin kilometraje inicial.
- No se registra retorno sin kilometraje final.

## Retornos

- Solo se registra retorno de programaciones en ruta.
- Al retornar, la programación pasa a finalizada.
- La solicitud pasa a atendida.
- El vehículo pasa a disponible.
- El vehículo reingresa a cola.

## Estados visuales

- Verde: disponible, atendida, finalizada.
- Amarillo: pendiente, programada, asignado.
- Azul: en ruta, iniciado.
- Rojo: rechazada, cancelada, fuera de servicio.
- Gris: mantenimiento, inactivo.
