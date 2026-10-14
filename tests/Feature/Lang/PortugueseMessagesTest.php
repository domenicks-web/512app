<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

it('mostra as mensagens da regra de senha forte em português', function () {
    $validator = Validator::make(
        ['password' => 'abcdefghijkl'],
        ['password' => [Password::min(12)->mixedCase()->numbers()]],
    );

    expect($validator->errors()->get('password'))->toBe([
        'A senha precisa ter pelo menos uma letra maiúscula e uma minúscula.',
        'A senha precisa ter pelo menos um número.',
    ]);
});
