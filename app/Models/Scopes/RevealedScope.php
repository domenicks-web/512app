<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Specimen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Espécime de bola ainda fechada não existe pro resto do jogo (coleção,
 * ranking, feed, trocas, admin). Só a tela do pack enxerga, via
 * `withoutGlobalScope` ou `Opening::specimens()`.
 */
/**
 * @implements Scope<Specimen>
 */
class RevealedScope implements Scope
{
    /**
     * @param  Builder<covariant Specimen>  $builder
     * @param  Specimen  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNotNull($model->qualifyColumn('revealed_at'));
    }
}
