<?php

// En las consultas del catálogo y las reservas, "a" es el alojamiento.
const PORTADA_ALOJAMIENTO_SQL = <<<'SQL'
    COALESCE(
        (SELECT NULLIF(i.ruta_o_url, '') FROM alojamiento_imagenes i
         WHERE i.alojamiento_id = a.id_alojamiento AND i.ruta_o_url <> ''
         ORDER BY i.es_portada DESC, i.orden ASC, i.id ASC LIMIT 1),
        (SELECT NULLIF(f.ruta_archivo, '') FROM fotos_alojamiento f
         WHERE f.id_alojamiento = a.id_alojamiento AND f.ruta_archivo <> ''
         ORDER BY f.posicion ASC LIMIT 1)
    )
    SQL;
