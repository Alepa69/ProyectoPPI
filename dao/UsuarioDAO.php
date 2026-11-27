<?php

class UsuarioDAO
{
    private mysqli $conexion;

    public function __construct(mysqli $conexion)
    {
        $this->conexion = $conexion;
    }

    public function buscarPorCorreo(string $correo): ?array
    {
        $sql = "SELECT id_usuario, nombre, apellido, correo, password_hash, rol, estado
                FROM usuarios
                WHERE correo = ?
                LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('s', $correo);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $fila ?: null;
    }

    public function registrar(string $nombre, string $apellido, string $correo, string $passwordHash): bool
    {
        $sql = "INSERT INTO usuarios (nombre, apellido, correo, password_hash) VALUES (?, ?, ?, ?)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('ssss', $nombre, $apellido, $correo, $passwordHash);
        try {
            $ok = $stmt->execute();
        } finally {
            $stmt->close();
        }

        return $ok;
    }

    public function obtenerPorId(int $idUsuario): ?array
    {
        $sql = "SELECT nombre, apellido, correo, telefono, biografia, foto_perfil, rol, estado, creado_en
                FROM usuarios
                WHERE id_usuario = ?
                LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $usuario ?: null;
    }

    public function obtenerEstadisticasHuesped(int $idUsuario): array
    {
        $stats = ['pendiente' => 0, 'aceptada' => 0, 'rechazada' => 0, 'cancelada' => 0];
        $sql = "SELECT estado, COUNT(*) AS total
                FROM reservas
                WHERE id_solicitante = ?
                GROUP BY estado";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($filas as $r) {
            $stats[$r['estado']] = (int) $r['total'];
        }
        return $stats;
    }

    public function obtenerEstadisticasAnfitrion(int $idUsuario): array
    {
        $sql = "SELECT COUNT(DISTINCT a.id_alojamiento) AS total_alojamientos,
                       COUNT(r.id_reserva) AS total_reservas
                FROM alojamientos a
                LEFT JOIN reservas r ON r.id_alojamiento = a.id_alojamiento
                WHERE a.id_anfitrion = ?";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $datos = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $datos ?: ['total_alojamientos' => 0, 'total_reservas' => 0];
    }

    public function obtenerPerfilPublicoAnfitrion(int $idAnfitrion): array
    {
        $sql = "SELECT u.nombre, u.apellido, u.biografia, u.foto_perfil, u.creado_en,
                       a.id_alojamiento, a.titulo,
                       COALESCE(NULLIF(a.ciudad, ''), a.municipio) AS municipio,
                       a.departamento
                FROM usuarios u
                JOIN alojamientos a ON a.id_anfitrion = u.id_usuario
                WHERE u.id_usuario = ? AND u.estado = 'activo' AND a.estado = 'publicado'
                ORDER BY a.creado_en DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $idAnfitrion);
        $stmt->execute();
        $anuncios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $anuncios;
    }
}
