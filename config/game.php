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
    | Perfil
    |--------------------------------------------------------------------------
    |
    | `nickname_cooldown_days`: depois de trocar o nickname, quantos dias até
    | poder trocar de novo (o nickname do onboarding não conta).
    | `email_change_link_minutes`: validade do link de confirmação mandado
    | pro email novo.
    |
    */

    'profile' => [
        'nickname_cooldown_days' => 15,
        'email_change_link_minutes' => 60,
    ],

    // Fuso usado em datas e horas mostradas pro jogador (o app roda em UTC).
    'display_timezone' => 'America/Sao_Paulo',

    /*
    |--------------------------------------------------------------------------
    | Grupo
    |--------------------------------------------------------------------------
    |
    | `online_window_minutes`: quem teve atividade na sessão dentro dessa
    | janela conta como online (tabela `sessions`, sem realtime).
    | `presence_poll_seconds`: de quanto em quanto tempo Home e Amigos
    | recarregam a presença — a própria recarga mantém a sessão ativa.
    | `feed_highlight_tiers`: tiers que entram no feed do grupo além de
    | todo shiny, que entra sempre.
    |
    */

    'group' => [
        'online_window_minutes' => 2,
        'presence_poll_seconds' => 60,
        'feed_highlight_tiers' => [
            RarityTier::Rare->value,
            RarityTier::Legendary->value,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Convites
    |--------------------------------------------------------------------------
    |
    | Formato do código ("RAMON-7X2K"): prefixo digitado pelo admin (padrão:
    | o nickname dele) + sufixo sorteado no servidor. O alfabeto do sufixo
    | não tem caracteres ambíguos (I, O, 0, 1), pra dar pra ditar o código.
    | `default_quota` é a cota de convites de um jogador comum (admin não
    | tem cota). Usos, validade e cota são as faixas do painel admin.
    |
    */

    'invites' => [
        'code_alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        'code_suffix_length' => 4,
        'code_prefix_max_length' => 8,
        'code_fallback_prefix' => 'CONVITE',
        'max_uses' => ['min' => 1, 'max' => 10],
        'validity_days' => ['7' => 7, '30' => 30, 'never' => null],
        'default_validity' => '30',
        'default_quota' => 2,
        'quota' => ['min' => 0, 'max' => 9],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verificação do espécime
    |--------------------------------------------------------------------------
    */

    'specimen_secret' => env('GAME_SPECIMEN_SECRET'),
];
