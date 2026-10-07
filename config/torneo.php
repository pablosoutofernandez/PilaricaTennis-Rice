<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Duración estimada de un partido según el marcador inicial del set
    |--------------------------------------------------------------------------
    |
    | Minutos de juego (con calentamiento) que ocupa una pista por partido.
    | La clave es el marcador inicial: 0 => 0-0, 1 => 1-1, ...
    |
    */
    'match_minutes' => [
        0 => 40,
        1 => 33,
        2 => 26,
        3 => 19,
        4 => 13,
    ],

    // Minutos entre un partido y el siguiente en la misma pista (salida y entrada de parejas).
    'changeover_minutes' => 5,

    // Margen sobre el tiempo de juego por organización: retrasos, avisar a las parejas, apuntar resultados...
    'organization_margin' => 0.10,

    // A mediodía se sigue jugando mientras la gente se turna para comer, pero habrá ratos con
    // pistas vacías. Minutos que se pierden por eso en jornadas de más de 'lunch_break_after_minutes'.
    'lunch_break_minutes' => 30,
    'lunch_break_after_minutes' => 300,

    // Marcador inicial máximo "razonable". Por encima solo se usa si no queda otra.
    'max_preferred_start' => 3,

    // Partidos que se intenta garantizar a cada pareja antes de alargar los sets.
    'target_matches_per_pair' => 4,

    // Número de partidos que retrocede un partido al aplazarlo.
    'postpone_steps' => 2,

    'min_pairs' => 4,
    'max_pairs' => 16,
    'max_courts' => 2,
];
