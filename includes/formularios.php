<?php

function tokenFormulario(): string
{
    return $_SESSION['token_formulario'] ??= bin2hex(random_bytes(32));
}

function tokenFormularioValido(): bool
{
    $token = $_POST['csrf'] ?? null;
    return is_string($token) && hash_equals(tokenFormulario(), $token);
}

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
