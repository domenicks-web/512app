<?php

declare(strict_types=1);

use App\Enums\RarityTier;

return [

    /*
    |--------------------------------------------------------------------------
    | Pack
    |--------------------------------------------------------------------------
    |
    | Tamanho do pack, cooldown entre aberturas e qual slot (1-indexado)
    | garante incomum ou acima.
    |
    */

    'pack' => [
        'slots' => 5,
        'cooldown_hours' => 24,
        'guaranteed_slot' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Raridade
    |--------------------------------------------------------------------------
    |
    | Pesos por tier (somam 100) usados no sorteio livre. No slot garantido,
    | o tier "comum" é removido e os pesos dos demais são redistribuídos
    | proporcionalmente.
    |
    | `legendary_dex_numbers` e `overrides` alimentam a classificação das 151
    | feita pelo seeder/RarityTable::classify(). Overrides tem prioridade
    | sobre a regra base (evolution_stage / base_stat_total).
    |
    */

    'rarity' => [
        'weights' => [
            RarityTier::Common->value => 60.0,
            RarityTier::Uncommon->value => 28.0,
            RarityTier::Rare->value => 10.5,
            RarityTier::Legendary->value => 1.5,
        ],

        'legendary_dex_numbers' => [144, 145, 146, 150, 151],

        'overrides' => [
            83 => RarityTier::Uncommon->value,  // Farfetch'd
            113 => RarityTier::Rare->value,     // Chansey
            115 => RarityTier::Rare->value,     // Kangaskhan
            128 => RarityTier::Rare->value,     // Tauros
            131 => RarityTier::Rare->value,     // Lapras
            132 => RarityTier::Rare->value,     // Ditto
            133 => RarityTier::Uncommon->value, // Eevee
            137 => RarityTier::Uncommon->value, // Porygon
            142 => RarityTier::Rare->value,     // Aerodactyl
            143 => RarityTier::Rare->value,     // Snorlax
            147 => RarityTier::Rare->value,     // Dratini
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shiny
    |--------------------------------------------------------------------------
    */

    'shiny' => [
        'rate' => 1 / 512,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tamanho
    |--------------------------------------------------------------------------
    |
    | size_roll segue distribuição normal (mean, stddev), sempre limitada a
    | [0, 1]. As faixas abaixo classificam o roll final em size_class.
    |
    */

    'size' => [
        'mean' => 0.5,
        'stddev' => 0.17,
        'ranges' => [
            'xxs' => [0.0, 0.02],
            'xs' => [0.02, 0.20],
            'm' => [0.20, 0.80],
            'xl' => [0.80, 0.98],
            'xxl' => [0.98, 1.0],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding
    |--------------------------------------------------------------------------
    |
    | Espécies disponíveis como avatar no wizard pós-cadastro. Ids batem com
    | `species.id` (dex number, 1 a 151).
    |
    */

    'onboarding' => [
        'avatar_species_ids' => [
            25,  // Pikachu
            1,   // Bulbasaur
            4,   // Charmander
            7,   // Squirtle
            6,   // Charizard
            133, // Eevee
            143, // Snorlax
            94,  // Gengar
            150, // Mewtwo
            151, // Mew
            39,  // Jigglypuff
            54,  // Psyduck
            130, // Gyarados
            149, // Dragonite
            52,  // Meowth
        ],

        'nickname_min_length' => 2,
        'nickname_max_length' => 16,

        // Primeiro ano da roda de data de nascimento (o último é o ano atual).
        'birth_year_min' => 1950,
    ],

    /*
    |--------------------------------------------------------------------------
    | Home
    |--------------------------------------------------------------------------
    |
    | Valores provisórios pro que a Home mostra mas ainda não existe no jogo
    | (ranking, trocas, feed, amigos online). Cada um sai daqui quando a
    | feature ganhar dado de verdade — ver pendências no CLAUDE.md.
    |
    */

    'home' => [
        'placeholders' => [
            'ranking_position' => 2,
            'trade_proposals' => 3,
            'feed' => [
                'is_shiny' => true,
                'headline' => 'Ramon abriu um Rattata',
                'meta' => 'há 12 min · #0004',
            ],
            'friends_online' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Convites
    |--------------------------------------------------------------------------
    |
    | Formato do código ("RAMON-7X2K"): prefixo com o nickname de quem criou
    | (cortado em `code_prefix_max_length`) + sufixo aleatório. O alfabeto
    | do sufixo não tem caracteres ambíguos (0/O, 1/I/L). Os limites
    | seguram o formulário do admin contra valor absurdo.
    |
    */

    'invites' => [
        'code_alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
        'code_suffix_length' => 4,
        'code_prefix_max_length' => 10,
        'code_fallback_prefix' => 'CONVITE',
        'max_uses_limit' => 1000,
        'expires_in_days_limit' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verificação do espécime
    |--------------------------------------------------------------------------
    */

    'specimen_secret' => env('GAME_SPECIMEN_SECRET'),
];
