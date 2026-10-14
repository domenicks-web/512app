<?php

declare(strict_types=1);

use App\Mail\ConfirmNewEmailMail;
use App\Mail\EmailChangedMail;
use App\Mail\PasswordChangedMail;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

/**
 * Espécies de avatar do config criadas no banco de teste.
 *
 * @return list<int>
 */
function seedAvatarSpecies(): array
{
    $ids = array_slice(config('game.onboarding.avatar_species_ids'), 0, 2);

    foreach ($ids as $id) {
        Species::factory()->create(['id' => $id]);
    }

    return $ids;
}

describe('tela', function () {
    it('exige login e perfil completo', function () {
        $this->get('/perfil')->assertRedirect('/login');
        $this->actingAs(User::factory()->withoutProfile()->create())->get('/perfil')->assertRedirect('/completar-perfil');
    });

    it('manda o estado de cada seção', function () {
        $this->travelTo(now()->setTime(12, 0));
        [$pikachu] = seedAvatarSpecies();
        $user = User::factory()->create([
            'nickname' => 'Lin',
            'tag' => '0741',
            'avatar_species_id' => $pikachu,
            'nickname_changed_at' => now()->subDays(3),
            'birthdate_corrected_at' => now(),
            'pending_email' => 'novo@512app.com',
        ]);

        $this->actingAs($user)->get('/perfil')
            ->assertInertia(fn (Assert $page) => $page
                ->component('profile/Edit')
                ->where('profile.nickname', 'Lin')
                ->where('profile.tag', '0741')
                ->where('profile.avatarSpeciesId', $pikachu)
                ->where('profile.nicknameChangeableAt', now()->addDays(12)->toDateString())
                ->where('profile.isBirthdateLocked', true)
                ->where('profile.pendingEmail', 'novo@512app.com')
                ->has('avatarOptions', 2)
                ->where('nicknameCooldownDays', 15));
    });
});

describe('avatar', function () {
    it('troca pra uma espécie da lista', function () {
        [, $second] = seedAvatarSpecies();
        $user = User::factory()->create();

        $this->actingAs($user)->from('/perfil')->put('/perfil/avatar', ['avatar_species_id' => $second])
            ->assertRedirect('/perfil')->assertSessionHasNoErrors();

        expect($user->refresh()->avatar_species_id)->toBe($second);
    });

    it('recusa espécie fora da lista', function () {
        $user = User::factory()->create();
        $outside = Species::factory()->create(['id' => 129]);

        $this->actingAs($user)->put('/perfil/avatar', ['avatar_species_id' => $outside->id])
            ->assertSessionHasErrors('avatar_species_id');
    });
});

describe('nickname', function () {
    it('a primeira troca é livre e grava a data', function () {
        $user = User::factory()->create(['nickname' => 'Lin', 'tag' => '0741', 'nickname_changed_at' => null]);

        $this->actingAs($user)->from('/perfil')->put('/perfil/nickname', ['nickname' => 'Lina'])
            ->assertRedirect('/perfil')->assertSessionHasNoErrors();

        expect($user->refresh()->nickname)->toBe('Lina')
            ->and($user->tag)->toBe('0741')
            ->and($user->nickname_changed_at)->not->toBeNull();
    });

    it('bloqueia antes dos 15 dias e diz quando libera', function () {
        $this->travelTo(now()->setTime(12, 0));
        $user = User::factory()->create(['nickname' => 'Lin', 'nickname_changed_at' => now()->subDays(14)]);

        $this->actingAs($user)->put('/perfil/nickname', ['nickname' => 'Lina'])
            ->assertSessionHasErrors(['nickname' => 'Você poderá trocar de novo em '.now()->addDay()->format('d/m').'.']);
    });

    it('libera no 15º dia', function () {
        $user = User::factory()->create(['nickname' => 'Lin', 'nickname_changed_at' => now()->subDays(15)]);

        $this->actingAs($user)->put('/perfil/nickname', ['nickname' => 'Lina'])->assertSessionHasNoErrors();
    });

    it('recusa o mesmo nickname', function () {
        $user = User::factory()->create(['nickname' => 'Lin']);

        $this->actingAs($user)->put('/perfil/nickname', ['nickname' => 'Lin'])
            ->assertSessionHasErrors(['nickname' => 'Esse já é o seu nickname.']);
    });

    it('sorteia outra tag quando a atual já existe com o nickname novo', function () {
        User::factory()->create(['nickname' => 'Lina', 'tag' => '0741']);
        $user = User::factory()->create(['nickname' => 'Lin', 'tag' => '0741']);

        $this->actingAs($user)->put('/perfil/nickname', ['nickname' => 'Lina'])->assertSessionHasNoErrors();

        expect($user->refresh()->tag)->not->toBe('0741')->toMatch('/^\d{4}$/');
    });

    it('valida o tamanho como no onboarding', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/perfil/nickname', ['nickname' => 'L'])->assertSessionHasErrors('nickname');
    });
});

describe('data de nascimento', function () {
    it('corrige uma vez e trava', function () {
        $user = User::factory()->create(['birthdate' => '2000-01-01', 'birthdate_corrected_at' => null]);

        $this->actingAs($user)->put('/perfil/nascimento', ['birthdate' => '1999-05-20'])->assertSessionHasNoErrors();
        expect($user->refresh()->birthdate->toDateString())->toBe('1999-05-20')
            ->and($user->birthdate_corrected_at)->not->toBeNull();

        $this->actingAs($user)->put('/perfil/nascimento', ['birthdate' => '1998-01-01'])
            ->assertSessionHasErrors(['birthdate' => 'Você já corrigiu a data de nascimento. Fale com um admin pra liberar outra correção.']);
        expect($user->refresh()->birthdate->toDateString())->toBe('1999-05-20');
    });

    it('recusa data no futuro', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/perfil/nascimento', ['birthdate' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('birthdate');
    });

    it('admin libera outra correção; jogador comum não', function () {
        $user = User::factory()->create(['birthdate_corrected_at' => now()]);

        $this->actingAs(User::factory()->create())->post("/admin/jogadores/{$user->id}/liberar-nascimento")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post("/admin/jogadores/{$user->id}/liberar-nascimento")->assertRedirect();

        expect($user->refresh()->birthdate_corrected_at)->toBeNull();
    });
});

describe('email', function () {
    it('pede a senha atual certa', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/perfil/email', ['email' => 'novo@512app.com', 'current_password' => 'errada'])
            ->assertSessionHasErrors('current_password');
        Mail::assertNothingSent();
    });

    it('guarda o email pendente e manda o link pro email novo', function () {
        $user = User::factory()->create(['email' => 'antigo@512app.com']);

        $this->actingAs($user)->post('/perfil/email', ['email' => 'novo@512app.com', 'current_password' => 'password'])
            ->assertSessionHasNoErrors();

        expect($user->refresh()->email)->toBe('antigo@512app.com')
            ->and($user->pending_email)->toBe('novo@512app.com');
        Mail::assertSent(ConfirmNewEmailMail::class, fn (ConfirmNewEmailMail $mail) => $mail->hasTo('novo@512app.com'));
    });

    it('recusa email já usado por outra conta e o próprio email atual', function (string $email) {
        User::factory()->create(['email' => 'outro@512app.com']);
        $user = User::factory()->create(['email' => 'antigo@512app.com']);

        $this->actingAs($user)->post('/perfil/email', ['email' => $email, 'current_password' => 'password'])
            ->assertSessionHasErrors('email');
    })->with(['outro@512app.com', 'antigo@512app.com']);

    it('o link confirma, marca como verificado e avisa o email antigo', function () {
        $user = User::factory()->unverified()->create(['email' => 'antigo@512app.com', 'pending_email' => 'novo@512app.com']);
        $url = URL::temporarySignedRoute('profile.email.confirm', now()->addHour(), ['email' => 'novo@512app.com']);

        $this->actingAs($user)->get($url)->assertRedirect('/perfil');

        expect($user->refresh()->email)->toBe('novo@512app.com')
            ->and($user->pending_email)->toBeNull()
            ->and($user->email_verified_at)->not->toBeNull();
        Mail::assertSent(EmailChangedMail::class, fn (EmailChangedMail $mail) => $mail->hasTo('antigo@512app.com'));
    });

    it('link de um pedido antigo não troca', function () {
        $user = User::factory()->create(['email' => 'antigo@512app.com', 'pending_email' => 'outro-pedido@512app.com']);
        $url = URL::temporarySignedRoute('profile.email.confirm', now()->addHour(), ['email' => 'novo@512app.com']);

        $this->actingAs($user)->get($url)->assertRedirect('/perfil');

        expect($user->refresh()->email)->toBe('antigo@512app.com');
        Mail::assertNothingSent();
    });

    it('link expirado ou adulterado dá 403', function () {
        $user = User::factory()->create(['pending_email' => 'novo@512app.com']);
        $expired = URL::temporarySignedRoute('profile.email.confirm', now()->subMinute(), ['email' => 'novo@512app.com']);

        $this->actingAs($user)->get($expired)->assertForbidden();
        $this->actingAs($user)->get('/perfil/email/confirmar?email=novo@512app.com&signature=x')->assertForbidden();
    });

    it('não troca se o email foi cadastrado por outra pessoa no meio-tempo', function () {
        $user = User::factory()->create(['email' => 'antigo@512app.com', 'pending_email' => 'novo@512app.com']);
        User::factory()->create(['email' => 'novo@512app.com']);
        $url = URL::temporarySignedRoute('profile.email.confirm', now()->addHour(), ['email' => 'novo@512app.com']);

        $this->actingAs($user)->get($url)->assertRedirect('/perfil');

        expect($user->refresh()->email)->toBe('antigo@512app.com')
            ->and($user->pending_email)->toBeNull();
    });

    it('cancela a troca pendente', function () {
        $user = User::factory()->create(['pending_email' => 'novo@512app.com']);

        $this->actingAs($user)->delete('/perfil/email')->assertRedirect();

        expect($user->refresh()->pending_email)->toBeNull();
    });
});

describe('senha', function () {
    it('exige senha atual, confirmação e senha diferente', function (array $payload, string $field) {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/perfil/senha', $payload)->assertSessionHasErrors($field);
    })->with([
        'senha atual errada' => [['current_password' => 'errada', 'password' => 'NovaSenha#2026', 'password_confirmation' => 'NovaSenha#2026'], 'current_password'],
        'sem confirmação' => [['current_password' => 'password', 'password' => 'NovaSenha#2026', 'password_confirmation' => 'outra'], 'password'],
        'igual à atual' => [['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'], 'password'],
    ]);

    it('troca, encerra as outras sessões e avisa por email', function () {
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => Str::random(40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()],
            ['id' => Str::random(40), 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);

        $this->actingAs($user)->put('/perfil/senha', [
            'current_password' => 'password',
            'password' => 'NovaSenha#2026',
            'password_confirmation' => 'NovaSenha#2026',
        ])->assertSessionHasNoErrors();

        expect(Hash::check('NovaSenha#2026', $user->refresh()->password))->toBeTrue()
            ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
            ->and(DB::table('sessions')->count())->toBe(1);
        Mail::assertSent(PasswordChangedMail::class, fn (PasswordChangedMail $mail) => $mail->hasTo($user->email));
    });

    it('limita as tentativas', function () {
        $user = User::factory()->create();

        foreach (range(1, 6) as $attempt) {
            $this->actingAs($user)->put('/perfil/senha', ['current_password' => 'errada']);
        }

        $this->actingAs($user)->put('/perfil/senha', ['current_password' => 'errada'])->assertTooManyRequests();
    });
});
