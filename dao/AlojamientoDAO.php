<?php

class AlojamientoDAO
{
    private mysqli $conexion;

    public function __construct(mysqli $conexion)
    {
        $this->conexion = $conexion;
    }

    public function listarPorAnfitrion(int $idAnfitrion): array
    {
        $sql = "SELECT a.id_alojamiento, a.titulo,
                       COALESCE(NULLIF(a.ciudad, ''), a.municipio) AS municipio,
                       a.departamento,
                       COALESCE(NULLIF(a.precio_noche, 0), a.precio_persona_noche) AS precio_persona_noche,
                       a.estado, a.motivo_bloqueo,
                       COALESCE(
                           (SELECT ai.ruta_o_url
                            FROM alojamiento_imagenes ai
                            WHERE ai.alojamiento_id = a.id_alojamiento
                              AND ai.es_portada = 1
                            ORDER BY ai.orden ASC, ai.id ASC
                            LIMIT 1),
                           (SELECT ai2.ruta_o_url
                            FROM alojamiento_imagenes ai2
                            WHERE ai2.alojamiento_id = a.id_alojamiento
                            ORDER BY ai2.orden ASC, ai2.id ASC
                            LIMIT 1),
                           f.ruta_archivo,
                           'https://picsum.photos/seed/casa/400/220'
                       ) AS foto_principal
                FROM alojamientos a
                LEFT JOIN fotos_alojamiento f ON a.id_alojamiento = f.id_alojamiento AND f.posicion = 1
                WHERE a.id_anfitrion = ?
                ORDER BY a.creado_en DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $idAnfitrion);
        $stmt->execute();
        $lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $lista;
    }

    public function cambiarEstadoPropio(int $idAlojamiento, int $idAnfitrion, string $nuevoEstado): bool
    {
        $sql = "UPDATE alojamientos
                SET estado = ?
                WHERE id_alojamiento = ?
                  AND id_anfitrion = ?
                  AND estado IN ('publicado', 'inactivo')";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('sii', $nuevoEstado, $idAlojamiento, $idAnfitrion);
        $stmt->execute();
        $cambiado = $stmt->affected_rows === 1;
        $stmt->close();

        return $cambiado;
    }
}
